<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Outbox;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Tmonier\SyliusWebhookOutboxPlugin\Capture\ChangeDetectorInterface;
use Tmonier\SyliusWebhookOutboxPlugin\Outbox\WebhookEvent;

/**
 * product_variant.price_changed (version 1), one event per channel and flush listing the
 * variants whose price or original price changed (new channel prices included, previous values
 * null). Lets a feed consumer update prices between full feeds (e.g. the Stripe pricing feed
 * cadence). Stock changes are product_variant.stock_changed of the outbox plugin.
 */
final class PriceChangedDetector implements ChangeDetectorInterface
{
    public function detect(EntityManagerInterface $entityManager, UnitOfWork $unitOfWork): iterable
    {
        /** @var array<string, list<array<string, mixed>>> $byChannel */
        $byChannel = [];
        foreach ($unitOfWork->getScheduledEntityInsertions() as $pricing) {
            if ($pricing instanceof ChannelPricingInterface && null !== $pricing->getPrice()) {
                $this->collect($byChannel, $pricing, null, null);
            }
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $pricing) {
            if (!$pricing instanceof ChannelPricingInterface) {
                continue;
            }
            $changeSet = $unitOfWork->getEntityChangeSet($pricing);
            $price = $this->change($changeSet['price'] ?? null);
            $original = $this->change($changeSet['originalPrice'] ?? null);
            if (null === $price && null === $original) {
                continue;
            }
            $this->collect($byChannel, $pricing, null === $price ? $pricing->getPrice() : $price[0], null === $original ? $pricing->getOriginalPrice() : $original[0]);
        }

        foreach ($byChannel as $channelCode => $variants) {
            yield new WebhookEvent(FeedEventTypeProvider::PRICE_CHANGED, ['channel' => $channelCode, 'variants' => $variants], $channelCode, 'product_variant');
        }
    }

    /** @param array<string, list<array<string, mixed>>> $byChannel */
    private function collect(array &$byChannel, ChannelPricingInterface $pricing, ?int $previousPrice, ?int $previousOriginal): void
    {
        $variant = $pricing->getProductVariant();
        $channelCode = (string) $pricing->getChannelCode();
        $byChannel[$channelCode][] = [
            'code' => $variant?->getCode(),
            'product_code' => $variant?->getProduct()?->getCode(),
            'price' => $pricing->getPrice(),
            'original_price' => $pricing->getOriginalPrice(),
            'previous_price' => $previousPrice,
            'previous_original_price' => $previousOriginal,
        ];
    }

    /** @return array{0: int|null, 1: int|null}|null [old, new] when the value really changed */
    private function change(mixed $change): ?array
    {
        if (!\is_array($change)) {
            return null;
        }
        $old = is_numeric($change[0] ?? null) ? (int) $change[0] : null;
        $new = is_numeric($change[1] ?? null) ? (int) $change[1] : null;

        return $old === $new ? null : [$old, $new];
    }
}
