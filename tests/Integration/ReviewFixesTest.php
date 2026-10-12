<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Integration;

use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Repository\ExchangeRateRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Tmonier\SyliusAgenticFeedsPlugin\AgenticFeedsTestTrait;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssueInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\VariantIdentifiersInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedGenerator;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\ItemBuilder;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FeedFormatInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FeedWriterInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleXmlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\FeedIssueRepositoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\FeedStorageInterface;

final class ReviewFixesTest extends KernelTestCase
{
    use AgenticFeedsTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testAChangeDuringTheGenerationKeepsTheFeedStale(): void
    {
        foreach (['A', 'B'] as $code) {
            $this->catalog->image($this->catalog->product('DURING' . $code, [1000]), 'a/' . $code . '.jpg');
        }
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);
        $feed->setStaleSince(new \DateTime('-1 hour'));
        $this->entityManager()->flush();
        $code = (string) $feed->getCode();
        $variantCode = 'DURINGA_' . $this->catalog->suffix;
        $entityManager = $this->entityManager();

        // A format whose first write changes a price that was already written (an admin at work).
        $format = new class(new GoogleXmlFormat(), $entityManager, $variantCode) implements FeedFormatInterface {
            public function __construct(private readonly GoogleXmlFormat $inner, private readonly \Doctrine\ORM\EntityManagerInterface $entityManager, private readonly string $variantCode)
            {
            }

            public function getCode(): string
            {
                return GoogleXmlFormat::CODE;
            }

            public function getLabel(): string
            {
                return $this->inner->getLabel();
            }

            public function getFiles(): array
            {
                return $this->inner->getFiles();
            }

            public function getContentType(string $role): string
            {
                return $this->inner->getContentType($role);
            }

            public function validate(FeedItem $item): array
            {
                return $this->inner->validate($item);
            }

            public function validateFeed(FeedContext $context): array
            {
                return [];
            }

            public function open(FeedContext $context, array $streams): FeedWriterInterface
            {
                $writer = $this->inner->open($context, $streams);
                $entityManager = $this->entityManager;
                $variantCode = $this->variantCode;

                return new class($writer, $entityManager, $variantCode) implements FeedWriterInterface {
                    private bool $changed = false;

                    public function __construct(private readonly FeedWriterInterface $writer, private readonly \Doctrine\ORM\EntityManagerInterface $entityManager, private readonly string $variantCode)
                    {
                    }

                    public function write(FeedItem $item): void
                    {
                        $this->writer->write($item);
                        if (!$this->changed) {
                            $this->changed = true;
                            sleep(1);
                            $variant = $this->entityManager->getRepository(ProductVariantInterface::class)->findOneBy(['code' => $this->variantCode]);
                            $pricing = $variant instanceof ProductVariantInterface ? $variant->getChannelPricings()->first() : null;
                            if ($pricing instanceof ChannelPricingInterface) {
                                $pricing->setPrice(1500);
                                $this->entityManager->flush();
                            }
                        }
                    }

                    public function finish(): void
                    {
                        $this->writer->finish();
                    }
                };
            }
        };
        $container = self::getContainer();
        $itemBuilder = $container->get('tmonier_agentic_feeds.item_builder');
        self::assertInstanceOf(ItemBuilder::class, $itemBuilder);
        $storage = $container->get('tmonier_agentic_feeds.storage');
        self::assertInstanceOf(FeedStorageInterface::class, $storage);
        $issues = $container->get('tmonier_agentic_feeds.repository.feed_issue');
        self::assertInstanceOf(FeedIssueRepositoryInterface::class, $issues);
        /** @var FactoryInterface<FeedIssueInterface> $issueFactory */
        $issueFactory = $container->get('tmonier_agentic_feeds.factory.feed_issue');
        $rates = $container->get('sylius.repository.exchange_rate');
        self::assertInstanceOf(ExchangeRateRepositoryInterface::class, $rates);
        $generator = new FeedGenerator($entityManager, $itemBuilder, new FormatRegistry([$format]), $storage, $issues, $issueFactory, new NullLogger(), $rates, 1);

        $generator->generate($feed);

        self::assertStringContainsString('10.00 EUR', (string) $this->file($code, '.xml'), 'Written before the change.');
        $this->entityManager()->clear();
        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        self::assertNotNull($feed->getStaleSince(), 'The change made during the generation is not lost.');
    }

    public function testAGtinIsNeverRepeated(): void
    {
        $first = $this->catalog->product('FIRST', [1000]);
        $second = $this->catalog->product('SECOND', [1000]);
        foreach ([$first, $second] as $product) {
            $this->catalog->image($product, 'a/' . $product->getCode() . '.jpg');
            $this->catalog->identifiers($this->catalog->variant($product), '4006381333931');
        }
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);

        $result = $this->generate($feed);

        self::assertSame(1, substr_count((string) $this->file((string) $feed->getCode(), '.xml'), '<g:gtin>4006381333931</g:gtin>'));
        self::assertContains('duplicate_gtin', array_map(static fn (array $issue): string => $issue[0]->code, $result->issues()));
    }

    public function testControlCharactersAreRemoved(): void
    {
        $product = $this->catalog->product('CTRL', [1000], null, "Line\x01 one\x0B and \x1F two");
        $this->catalog->image($product, 'a/ctrl.jpg');
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);

        $result = $this->generate($feed);

        $xml = simplexml_load_string((string) $this->file((string) $feed->getCode(), '.xml'));
        self::assertNotFalse($xml, 'The XML stays well-formed.');
        self::assertSame('Line one and two', (string) $xml->channel->item[0]->description);
        self::assertContains('invalid_characters', array_map(static fn (array $issue): string => $issue[0]->code, $result->issues()));
    }

    public function testADisabledChannelIsNotGenerated(): void
    {
        $this->catalog->image($this->catalog->product('OFFCHANNEL', [1000]), 'a/off.jpg');
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);
        $this->catalog->channel()->setEnabled(false);
        $this->entityManager()->flush();

        $result = $this->generate($feed);

        self::assertTrue($result->failed());
        self::assertStringContainsString('is disabled', (string) $result->failure());
    }

    public function testTheIdentifiersImport(): void
    {
        $product = $this->catalog->product('IMPORT', [1000, 1000]);
        $codes = array_map(static fn (ProductVariantInterface $variant): string => (string) $variant->getCode(), [$this->catalog->variant($product, 0), $this->catalog->variant($product, 1)]);
        $file = tempnam(sys_get_temp_dir(), 'tmonier_ids');
        self::assertIsString($file);
        $kernel = self::getContainer()->get('kernel');
        self::assertInstanceOf(KernelInterface::class, $kernel);
        $tester = new CommandTester((new Application($kernel))->find('tmonier:feeds:import-identifiers'));

        try {
            file_put_contents($file, sprintf("variant_code;gtin;mpn\n%s;4006381333931;M-1\n%s;4006381333931;M-2\n", $codes[0], $codes[1]));
            self::assertSame(1, $tester->execute(['file' => $file]));
            self::assertStringContainsString('already on line 2', $tester->getDisplay());

            file_put_contents($file, sprintf("variant_code;gtin;mpn\n%s;400-6381 333931;M-1\n%s;;M-2\n", $codes[0], $codes[1]));
            self::assertSame(0, $tester->execute(['file' => $file]));
        } finally {
            unlink($file);
        }
        $this->entityManager()->clear();
        $rows = $this->entityManager()->getRepository(VariantIdentifiersInterface::class)->findAll();
        $byCode = [];
        foreach ($rows as $row) {
            $byCode[(string) $row->getVariant()?->getCode()] = [$row->getGtin(), $row->getMpn()];
        }
        self::assertSame(['4006381333931', 'M-1'], $byCode[$codes[0]] ?? null);
        self::assertSame([null, 'M-2'], $byCode[$codes[1]] ?? null);
    }
}
