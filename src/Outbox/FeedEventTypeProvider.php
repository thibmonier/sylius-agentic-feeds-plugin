<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Outbox;

use Tmonier\SyliusWebhookOutboxPlugin\EventType\EventType;
use Tmonier\SyliusWebhookOutboxPlugin\EventType\EventTypeProviderInterface;

/** Registered only when tmonier/sylius-webhook-outbox-plugin is installed. */
final class FeedEventTypeProvider implements EventTypeProviderInterface
{
    public const PRICE_CHANGED = 'product_variant.price_changed';

    public function getEventTypes(): iterable
    {
        yield new EventType(self::PRICE_CHANGED, version: 1, label: 'tmonier_agentic_feeds.webhook.price_changed');
    }
}
