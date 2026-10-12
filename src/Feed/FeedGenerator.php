<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Feed;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Model\ExchangeRateInterface;
use Sylius\Component\Currency\Repository\ExchangeRateRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssueInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FeedFormatInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\FeedIssueRepositoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\FeedStorageInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Gtin;
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

    /**
     * @param FactoryInterface<FeedIssueInterface> $issueFactory
     * @param ExchangeRateRepositoryInterface<ExchangeRateInterface> $exchangeRateRepository
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ItemBuilder $itemBuilder,
        private readonly FormatRegistry $formats,
        private readonly FeedStorageInterface $storage,
        private readonly FeedIssueRepositoryInterface $issueRepository,
        private readonly FactoryInterface $issueFactory,
        private readonly LoggerInterface $logger,
        private readonly ExchangeRateRepositoryInterface $exchangeRateRepository,
        private readonly int $batchSize = 100,
    ) {
    }

    /** @param bool $dryRun validate only: no file written, the feed and its report unchanged */
    public function generate(ProductFeedInterface $feed, bool $dryRun = false): GenerationResult
    {
        $started = hrtime(true);
        // Catalog changes from this second on make the feed stale again (see record()).
        $startedAt = new \DateTimeImmutable(date('Y-m-d H:i:s'));
        $feedClass = $feed::class;
        $feedId = $feed->getId();
        $code = (string) $feed->getCode();
        $result = new GenerationResult($code);
        $streams = [];

        try {
            $format = $this->formats->get((string) $feed->getFormat());
            $context = FeedContext::fromFeed($feed);
            $channelId = $context->channel->getId();
            $this->checkFeed($context, $format, $result);
            foreach ($format->getFiles() as $role => $suffix) {
                $stream = fopen('php://temp/maxmemory:8388608', 'w+b');
                if (false === $stream) {
                    throw new \RuntimeException('Cannot open a temporary stream.');
                }
                $streams[$role] = $stream;
            }
            $writer = $format->open($context, $streams);

            $gtins = [];
            foreach (array_chunk($this->variantIds($context->channel), max(1, $this->batchSize)) as $ids) {
                $this->itemBuilder->primeIdentifiers($ids);
                foreach ($this->variants($ids) as $variant) {
                    $item = $this->itemBuilder->build($variant, $context);
                    if (null === $item) {
                        $result->add(Issue::error(Issue::MISSING_PRICE, 'price'), (string) $variant->getCode(), $variant->getProduct()?->getCode());

                        continue;
                    }
                    // One GTIN per item: a duplicate is left out (Google: each variant has its own GTIN).
                    $gtin = Gtin::normalize($item->gtin);
                    if (null !== $gtin && isset($gtins[$gtin])) {
                        $result->add(Issue::warning(Issue::DUPLICATE_GTIN, 'gtin', ['%gtin%' => $gtin, '%item%' => $gtins[$gtin]]), $item->id, $item->productCode);
                        $item = $item->withoutGtin();
                    } elseif (null !== $gtin) {
                        $gtins[$gtin] = $item->id;
                    }
                    $issues = $format->validate($item);
                    if ($item->sharedIdentifierIgnored) {
                        $issues[] = Issue::warning(Issue::SHARED_IDENTIFIER, 'gtin');
                    }
                    if ($item->textCleaned) {
                        $issues[] = Issue::warning(Issue::INVALID_CHARACTERS, 'description');
                    }
                    if (!$item->taxIncluded && !\in_array($context->countryCode, FeedItem::TAX_EXCLUSIVE_COUNTRIES, true)) {
                        $issues[] = Issue::warning(Issue::PRICE_EXCLUDES_TAX, 'price');
                    }
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
                $context = new FeedContext($channel, $context->localeCode, $context->currencyCode, $context->countryCode, $context->format, $context->now, $context->shippingRules);
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
            $this->record($feedClass, $feedId, $result, $startedAt);
        }

        return $result;
    }

    /**
     * Feed-level checks: an enabled channel, an exchange rate for a foreign currency (Sylius would
     * otherwise publish unconverted amounts), the format requirements. Errors stop the generation
     * (the previous files are kept); warnings are reported.
     */
    private function checkFeed(FeedContext $context, FeedFormatInterface $format, GenerationResult $result): void
    {
        if (!$context->channel->isEnabled()) {
            throw new \RuntimeException(sprintf('The channel "%s" is disabled.', (string) $context->channel->getCode()));
        }
        $base = (string) $context->channel->getBaseCurrency()?->getCode();
        if ($base !== $context->currencyCode && null === $this->exchangeRateRepository->findOneWithCurrencyPair($base, $context->currencyCode)) {
            throw new \RuntimeException(sprintf('No exchange rate between %s and %s: prices cannot be converted.', $base, $context->currencyCode));
        }
        foreach ($format->validateFeed($context) as $issue) {
            if ($issue->isError()) {
                throw new \RuntimeException(sprintf('Feed configuration: %s (%s).', $issue->code, (string) $issue->field));
            }
            $result->add($issue, null, null);
        }
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
    private function record(string $feedClass, mixed $feedId, GenerationResult $result, \DateTimeImmutable $startedAt): void
    {
        $feed = $this->entityManager->find($feedClass, $feedId);
        if (!$feed instanceof ProductFeedInterface) {
            return;
        }
        $feed->recordGeneration(new \DateTime(), $result->status(), $result->items(), $result->errorCount(), $result->warningCount(), $result->durationMs(), $result->failure());
        if (!$result->failed()) {
            // A change during the generation (staleSince at or after the start) keeps it stale.
            if (null === $feed->getStaleSince() || $feed->getStaleSince() < $startedAt) {
                $feed->setStaleSince(null);
            }
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
