<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Feed;

use Sylius\Component\Core\Model\ChannelInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;

/** What a feed is built for: channel, locale, currency, target country, format. */
final class FeedContext
{
    public function __construct(
        public readonly ChannelInterface $channel,
        public readonly string $localeCode,
        public readonly string $currencyCode,
        public readonly ?string $countryCode,
        public readonly string $format,
        public readonly \DateTimeImmutable $now,
    ) {
    }

    public static function fromFeed(ProductFeedInterface $feed, ?\DateTimeImmutable $now = null): self
    {
        $channel = $feed->getChannel();
        if (null === $channel) {
            throw new \InvalidArgumentException(sprintf('Feed "%s" has no channel.', (string) $feed->getCode()));
        }

        return new self(
            $channel,
            (string) ($feed->getLocaleCode() ?? $channel->getDefaultLocale()?->getCode()),
            (string) ($feed->getCurrencyCode() ?? $channel->getBaseCurrency()?->getCode()),
            $feed->getCountryCode(),
            (string) $feed->getFormat(),
            $now ?? new \DateTimeImmutable(),
        );
    }
}
