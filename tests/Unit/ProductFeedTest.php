<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeed;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;

final class ProductFeedTest extends TestCase
{
    #[Test]
    public function defaultState(): void
    {
        $feed = new ProductFeed();

        self::assertNull($feed->getId());
        self::assertNull($feed->getCode());
        self::assertNull($feed->getName());
        self::assertNull($feed->getChannel());
        self::assertNull($feed->getLocaleCode());
        self::assertNull($feed->getCurrencyCode());
        self::assertNull($feed->getCountryCode());
        self::assertNull($feed->getFormat());
        self::assertNull($feed->getAccessToken());
        self::assertNull($feed->getShippingRulesText());
        self::assertSame([], $feed->getShippingRules());
        self::assertNull($feed->getLastGeneratedAt());
        self::assertSame(ProductFeedInterface::STATUS_NEVER, $feed->getLastStatus());
        self::assertSame(0, $feed->getLastItemCount());
        self::assertSame(0, $feed->getLastErrorCount());
        self::assertSame(0, $feed->getLastWarningCount());
        self::assertNull($feed->getLastDurationMs());
        self::assertNull($feed->getLastFailure());
        self::assertNull($feed->getStaleSince());
        self::assertNotNull($feed->getCreatedAt());
    }

    #[Test]
    public function recordGenerationSuccess(): void
    {
        $feed = new ProductFeed();
        $at = new \DateTimeImmutable('2026-10-12 10:00:00');

        $feed->recordGeneration($at, ProductFeedInterface::STATUS_OK, 42, 0, 0, 1500);

        self::assertSame(ProductFeedInterface::STATUS_OK, $feed->getLastStatus());
        self::assertSame($at, $feed->getLastGeneratedAt());
        self::assertSame(42, $feed->getLastItemCount());
        self::assertSame(0, $feed->getLastErrorCount());
        self::assertSame(0, $feed->getLastWarningCount());
        self::assertSame(1500, $feed->getLastDurationMs());
        self::assertNull($feed->getLastFailure());
    }

    #[Test]
    public function recordGenerationWithWarnings(): void
    {
        $feed = new ProductFeed();
        $at = new \DateTimeImmutable();

        $feed->recordGeneration($at, ProductFeedInterface::STATUS_WARNINGS, 40, 0, 3, 2000);

        self::assertSame(ProductFeedInterface::STATUS_WARNINGS, $feed->getLastStatus());
        self::assertSame(40, $feed->getLastItemCount());
        self::assertSame(3, $feed->getLastWarningCount());
    }

    #[Test]
    public function recordGenerationFailureKeepsPreviousCounts(): void
    {
        $feed = new ProductFeed();
        $first = new \DateTimeImmutable('2026-10-12 10:00:00');
        $feed->recordGeneration($first, ProductFeedInterface::STATUS_OK, 42, 0, 0, 1000);

        $second = new \DateTimeImmutable('2026-10-12 11:00:00');
        $feed->recordGeneration($second, ProductFeedInterface::STATUS_FAILED, 0, 0, 0, 500, 'Out of memory');

        self::assertSame(ProductFeedInterface::STATUS_FAILED, $feed->getLastStatus());
        self::assertSame(500, $feed->getLastDurationMs());
        self::assertSame('Out of memory', $feed->getLastFailure());
        self::assertSame($first, $feed->getLastGeneratedAt());
        self::assertSame(42, $feed->getLastItemCount());
        self::assertSame(0, $feed->getLastErrorCount());
    }

    #[Test]
    public function recordGenerationTruncatesLongFailureMessage(): void
    {
        $feed = new ProductFeed();
        $message = str_repeat('A', 2000);

        $feed->recordGeneration(new \DateTimeImmutable(), ProductFeedInterface::STATUS_FAILED, 0, 0, 0, 100, $message);

        self::assertSame(1000, mb_strlen((string) $feed->getLastFailure()));
    }

    #[Test]
    public function setCountryCodeUppercases(): void
    {
        $feed = new ProductFeed();

        $feed->setCountryCode('fr');

        self::assertSame('FR', $feed->getCountryCode());
    }

    #[Test]
    public function setCountryCodeNull(): void
    {
        $feed = new ProductFeed();

        $feed->setCountryCode(null);

        self::assertNull($feed->getCountryCode());
    }

    #[Test]
    public function setAccessTokenNormalizesEmpty(): void
    {
        $feed = new ProductFeed();

        $feed->setAccessToken('');

        self::assertNull($feed->getAccessToken());
    }

    #[Test]
    public function setAccessTokenKeepsValue(): void
    {
        $feed = new ProductFeed();

        $feed->setAccessToken('abc123');

        self::assertSame('abc123', $feed->getAccessToken());
    }

    #[Test]
    public function getShippingRulesSplitsLines(): void
    {
        $feed = new ProductFeed();
        $feed->setShippingRulesText("FR:national:standard:5-7:500\nDE:national:express:1-2:1200");

        $rules = $feed->getShippingRules();

        self::assertSame(['FR:national:standard:5-7:500', 'DE:national:express:1-2:1200'], $rules);
    }

    #[Test]
    public function getShippingRulesTrimsAndFilters(): void
    {
        $feed = new ProductFeed();
        $feed->setShippingRulesText("  FR:rule  \n\n  \n  DE:rule  ");

        $rules = $feed->getShippingRules();

        self::assertSame(['FR:rule', 'DE:rule'], $rules);
    }

    #[Test]
    public function setShippingRulesTextNormalizesBlank(): void
    {
        $feed = new ProductFeed();

        $feed->setShippingRulesText('  ');

        self::assertNull($feed->getShippingRulesText());
    }
}
