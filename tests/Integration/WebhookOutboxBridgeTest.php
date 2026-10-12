<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Integration;

use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Tmonier\SyliusAgenticFeedsPlugin\AgenticFeedsTestTrait;
use Tmonier\SyliusAgenticFeedsPlugin\Outbox\FeedEventTypeProvider;
use Tmonier\SyliusWebhookOutboxPlugin\Entity\OutboxEventInterface;
use Tmonier\SyliusWebhookOutboxPlugin\Entity\WebhookEndpointInterface;

/** With tmonier/sylius-webhook-outbox-plugin (dev dependency): price changes become events. */
final class WebhookOutboxBridgeTest extends KernelTestCase
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

    public function testAPriceChangeIsRecorded(): void
    {
        $product = $this->catalog->product('PRICED', [1000]);
        /** @var FactoryInterface<WebhookEndpointInterface> $factory */
        $factory = self::getContainer()->get('tmonier_webhook.factory.webhook_endpoint');
        $endpoint = $factory->createNew();
        $endpoint->setName('Feed consumer');
        $endpoint->setUrl('https://consumer.example.com/hooks');
        $endpoint->setSecret(str_repeat('s', 32));
        $endpoint->setEventTypes([FeedEventTypeProvider::PRICE_CHANGED]);
        $endpoint->setEnabled(true);
        $this->entityManager()->persist($endpoint);
        $this->entityManager()->flush();

        $pricing = $this->catalog->variant($product)->getChannelPricingForChannel($this->catalog->channel());
        self::assertNotNull($pricing);
        $pricing->setPrice(1250);
        $this->entityManager()->flush();

        $events = $this->entityManager()->getRepository(OutboxEventInterface::class)->findBy(['type' => FeedEventTypeProvider::PRICE_CHANGED]);
        $events = array_values(array_filter($events, fn (OutboxEventInterface $event): bool => $event->getChannelCode() === $this->catalog->channel()->getCode()));
        self::assertCount(1, $events);
        $variants = $events[0]->getPayload()['variants'] ?? null;
        self::assertIsArray($variants);
        self::assertSame([
            'code' => 'PRICED_' . $this->catalog->suffix,
            'product_code' => 'PRICED_' . $this->catalog->suffix,
            'price' => 1250,
            'original_price' => null,
            'previous_price' => 1000,
            'previous_original_price' => null,
        ], $variants[0] ?? null);
    }
}
