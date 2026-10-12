<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Tests\Tmonier\SyliusAgenticFeedsPlugin\AgenticFeedsTestTrait;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleTsvFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateFeed;
use Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateStaleFeeds;

final class FeedMessageHandlerTest extends KernelTestCase
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

    public function testTheMessagesGenerateTheFeeds(): void
    {
        $product = $this->catalog->product('BUS', [1000]);
        $this->catalog->image($product, 'a/bus.jpg');
        $feed = $this->catalog->feed(GoogleTsvFormat::CODE);
        $code = (string) $feed->getCode();
        $bus = self::getContainer()->get('sylius.command_bus');
        self::assertInstanceOf(MessageBusInterface::class, $bus);

        $bus->dispatch(new GenerateFeed($code));

        $tsv = (string) $this->file($code, '.tsv');
        self::assertStringStartsWith("id\ttitle\tdescription", $tsv);
        self::assertStringContainsString('BUS_' . $this->catalog->suffix, $tsv);

        // Never generated or stale feeds only.
        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        $generatedAt = $feed->getLastGeneratedAt()?->format('Y-m-d H:i:s');
        $bus->dispatch(new GenerateStaleFeeds());
        $this->entityManager()->clear();
        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        self::assertSame($generatedAt, $feed->getLastGeneratedAt()?->format('Y-m-d H:i:s'));
    }
}
