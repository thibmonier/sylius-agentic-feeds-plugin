<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Currency\Model\Currency;
use Sylius\Component\Locale\Model\Locale;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeed;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;

final class FeedContextTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $channel = new Channel();
        $now = new \DateTimeImmutable();

        $context = new FeedContext($channel, 'fr_FR', 'EUR', 'FR', 'google_xml', $now, ['rule1', 'rule2']);

        self::assertSame($channel, $context->channel);
        self::assertSame('fr_FR', $context->localeCode);
        self::assertSame('EUR', $context->currencyCode);
        self::assertSame('FR', $context->countryCode);
        self::assertSame('google_xml', $context->format);
        self::assertSame($now, $context->now);
        self::assertSame(['rule1', 'rule2'], $context->shippingRules);
    }

    #[Test]
    public function countryCodeCanBeNull(): void
    {
        $channel = new Channel();
        $context = new FeedContext($channel, 'en_US', 'USD', null, 'stripe_csv', new \DateTimeImmutable());

        self::assertNull($context->countryCode);
    }

    #[Test]
    public function fromFeedBuildsContext(): void
    {
        $channel = new Channel();
        $channel->setCode('WEB');

        $locale = new Locale();
        $locale->setCode('fr_FR');
        $channel->setDefaultLocale($locale);

        $currency = new Currency();
        $currency->setCode('EUR');
        $channel->setBaseCurrency($currency);

        $feed = new ProductFeed();
        $feed->setChannel($channel);
        $feed->setLocaleCode('de_DE');
        $feed->setCurrencyCode('CHF');
        $feed->setCountryCode('CH');
        $feed->setFormat('google_xml');
        $feed->setShippingRulesText("CH:national:standard:5-7:500");

        $now = new \DateTimeImmutable('2026-11-01 10:00:00');
        $context = FeedContext::fromFeed($feed, $now);

        self::assertSame($channel, $context->channel);
        self::assertSame('de_DE', $context->localeCode);
        self::assertSame('CHF', $context->currencyCode);
        self::assertSame('CH', $context->countryCode);
        self::assertSame('google_xml', $context->format);
        self::assertSame($now, $context->now);
        self::assertSame(['CH:national:standard:5-7:500'], $context->shippingRules);
    }

    #[Test]
    public function fromFeedUsesChannelDefaultsWhenFeedHasNoOverride(): void
    {
        $channel = new Channel();

        $locale = new Locale();
        $locale->setCode('en_US');
        $channel->setDefaultLocale($locale);

        $currency = new Currency();
        $currency->setCode('USD');
        $channel->setBaseCurrency($currency);

        $feed = new ProductFeed();
        $feed->setChannel($channel);
        $feed->setFormat('stripe_csv');

        $context = FeedContext::fromFeed($feed);

        self::assertSame('en_US', $context->localeCode);
        self::assertSame('USD', $context->currencyCode);
        self::assertNull($context->countryCode);
    }

    #[Test]
    public function fromFeedThrowsWithoutChannel(): void
    {
        $feed = new ProductFeed();
        $feed->setCode('no_channel');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Feed "no_channel" has no channel');

        FeedContext::fromFeed($feed);
    }
}
