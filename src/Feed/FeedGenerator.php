<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Feed;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssueInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\FeedIssueRepositoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\FeedStorageInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;

/**
 * Generates the files of a feed: every enabled variant of the enabled products of the channel,
 * in batches (the entity manager is cleared between batches). Items with errors are left out
 * and reported; the files replace the previous ones only when the generation succeeds.
 */
final class FeedGenerator
{
    /** Issues stored for the admin report (the counts cover all of them). */
    private const STORED_ISSUES = 5000;

    /** @param FactoryInterface<FeedIssueInterface> $issueFactory */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemBuilder $itemBuilder,
        private readonly FormatRegistry $formats,
        private readonly FeedStorageInterface $storage,
        private readonly FeedIssueRepositoryInterface $issueRepository,
        private readonly FactoryInterface $issueFactory,
        private readonly LoggerInterface $logger,
        private readonly int $batchSize = 100,
    ) {
    }

    /** @param bool $dryRun validate only: no file written, the feed and its report unchanged */
    public function generate(ProductFeedInterface $feed, bool $dryRun = false): GenerationResult
    {
        $started = hrtime(true);
        $feedClass = $feed::class;
        $feedId = $feed->getId();
        $code = (string) $feed->getCode();
        $result = new GenerationResult($code);
        $streams = [];

        try {
            $format = $this->formats->get((string) $feed->getFormat());
            $context = FeedContext::fromFeed($feed);
            $channelId = $context->channel->getId();
            foreach ($format->getFiles() as $role => $suffix) {
                $stream = fopen('php://temp/maxmemory:8388608', 'w+b');
                if (false === $stream) {
                    throw new \RuntimeException('Cannot open a temporary stream.');
                }
                $streams[$role] = $stream;
            }
            $writer = $format->open($context, $streams);

            foreach (array_chunk($this->variantIds($context->channel), max(1, $this->batchSize)) as $ids) {
                foreach ($this->variants($ids) as $variant) {
                    $item = $this->itemBuilder->build($variant, $context);
                    if (null === $item) {
                        $result->add(Issue::error(Issue::MISSING_PRICE, 'price'), (string) $variant->getCode(), $variant->getProduct()?->getCode());

                        continue;
                    }
                    $issues = $format->validate($item);
                    foreach ($issues as $issue) {
                        $result->add($issue, $item->id, $item->productCode);
                    }
                    if ([] === array_filter($issues, static fn (Issue $issue): bool => $issue->isError())) {
                        $writer->write($item);
                        $result->itemWritten();
                    }
                }
                // Keep memory flat on large catalogs; the context gets fresh managed entities.
                $this->entityManager->clear();
                $channel = $this->entityManager->find(ChannelInterface::class, $channelId);
                if (!$channel instanceof ChannelInterface) {
                    throw new \RuntimeException('The channel of the feed was removed during the generation.');
                }
                // Writers only keep scalar data of their context (codes, names, dates).
                $context = new FeedContext($channel, $context->localeCode, $context->currencyCode, $context->countryCode, $context->format, $context->now);
            }
            $writer->finish();

            if (!$dryRun) {
                foreach ($format->getFiles() as $role => $suffix) {
                    $this->storage->write(FormatRegistry::fileName($code, $suffix), $streams[$role]);
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Feed generation failed.', ['feed' => $code, 'exception' => $exception]);
            $result->fail($exception->getMessage());
        } finally {
            foreach ($streams as $stream) {
                fclose($stream);
            }
        }
        $result->finish((int) ((hrtime(true) - $started) / 1_000_000));

        if (!$dryRun) {
            $this->record($feedClass, $feedId, $result);
        }

        return $result;
    }

    /** @return list<int> */
    private function variantIds(ChannelInterface $channel): array
    {
        /** @var list<array{id: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('variant.id')
            ->from(ProductVariantInterface::class, 'variant')
            ->innerJoin('variant.product', 'product')
            ->innerJoin('product.channels', 'channel')
            ->andWhere('channel = :channel')
            ->andWhere('product.enabled = true')
            ->andWhere('variant.enabled = true')
            ->setParameter('channel', $channel)
            ->addOrderBy('product.id', 'ASC')
            ->addOrderBy('variant.position', 'ASC')
            ->addOrderBy('variant.id', 'ASC')
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<ProductVariantInterface>
     */
    private function variants(array $ids): array
    {
        /** @var list<ProductVariantInterface> $variants */
        $variants = $this->entityManager->createQueryBuilder()
            ->select('variant', 'product')
            ->from(ProductVariantInterface::class, 'variant')
            ->innerJoin('variant.product', 'product')
            ->andWhere('variant.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->addOrderBy('product.id', 'ASC')
            ->addOrderBy('variant.position', 'ASC')
            ->addOrderBy('variant.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $variants;
    }

    /** @param class-string $feedClass */
    private function record(string $feedClass, mixed $feedId, GenerationResult $result): void
    {
        $feed = $this->entityManager->find($feedClass, $feedId);
        if (!$feed instanceof ProductFeedInterface) {
            return;
        }
        $feed->recordGeneration(new \DateTime(), $result->status(), $result->items(), $result->errorCount(), $result->warningCount(), $result->durationMs(), $result->failure());
        if (!$result->failed()) {
            $feed->setStaleSince(null);
            $this->issueRepository->deleteByFeed($feed);
            foreach (\array_slice($result->issues(), 0, self::STORED_ISSUES) as [$issue, $itemId, $productCode]) {
                $entity = $this->issueFactory->createNew();
                $entity->setFeed($feed);
                $entity->setSeverity($issue->severity);
                $entity->setCode($issue->code);
                $entity->setField($issue->field);
                $entity->setItemId($itemId);
                $entity->setProductCode($productCode);
                $entity->setParameters($issue->parameters);
                $this->entityManager->persist($entity);
            }
        }
        $this->entityManager->flush();
    }
}
