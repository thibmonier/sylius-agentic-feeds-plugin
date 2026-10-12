<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedGenerator;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\GenerationResult;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\FeedStorageInterface;

/**
 * Every test runs in a transaction rolled back at the end; the files of the feeds it creates
 * are removed. The generator clears the entity manager: entities created before a generation
 * are detached afterwards (reload them).
 */
trait AgenticFeedsTestTrait
{
    private TestCatalog $catalog;

    private function beginTestTransaction(): void
    {
        $this->connection()->beginTransaction();
        $this->catalog = new TestCatalog(self::getContainer());
    }

    private function rollBackTestTransaction(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
        $directory = self::getContainer()->getParameter('tmonier_agentic_feeds.storage.directory');
        foreach (glob(sprintf('%s/*%s*', \is_string($directory) ? $directory : '', strtolower($this->catalog->suffix))) ?: [] as $file) {
            unlink($file);
        }
    }

    private function generate(ProductFeedInterface $feed, bool $dryRun = false): GenerationResult
    {
        $generator = self::getContainer()->get('tmonier_agentic_feeds.generator');
        self::assertInstanceOf(FeedGenerator::class, $generator);

        return $generator->generate($feed, $dryRun);
    }

    /** Content of a generated file (gzip decoded), null when absent. */
    private function file(string $code, string $suffix): ?string
    {
        $storage = self::getContainer()->get('tmonier_agentic_feeds.storage');
        self::assertInstanceOf(FeedStorageInterface::class, $storage);
        $stream = $storage->read(FormatRegistry::fileName($code, $suffix));
        if (null === $stream) {
            return null;
        }
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return str_ends_with($suffix, '.gz') ? (string) gzdecode($content) : $content;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
