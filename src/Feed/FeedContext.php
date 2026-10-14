<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Feed;

use Sylius\Component\Core\Model\ChannelInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;

/** What a feed is built for: channel, locale, currency, target country, format, shipping entries. */
final readonly class FeedContext
{
    /** @param list<string> $shippingRules */
    public function __construct(
        public ChannelInterface $channel,
        public string $localeCode,
        public string $currencyCode,
        public ?string $countryCode,
        public string $format,
        public \DateTimeImmutable $now,
        /** Stripe shipping entries "country:delivery_area:service:speed_range:price" of the feed. */
        public array $shippingRules = [],
    ) {
    }

    public static function fromFeed(ProductFeedInterface $feed, ?\DateTimeImmutable $now = null): self
    {
        $channel = $feed->getChannel();
        if (!$channel instanceof \Sylius\Component\Core\Model\ChannelInterface) {
            throw new \InvalidArgumentException(sprintf('Feed "%s" has no channel.', (string) $feed->getCode()));
        }

        return new self(
            $channel,
            (string) ($feed->getLocaleCode() ?? $channel->getDefaultLocale()?->getCode()),
            (string) ($feed->getCurrencyCode() ?? $channel->getBaseCurrency()?->getCode()),
            $feed->getCountryCode(),
            (string) $feed->getFormat(),
            $now ?? new \DateTimeImmutable(),
            $feed->getShippingRules(),
        );
    }
}
