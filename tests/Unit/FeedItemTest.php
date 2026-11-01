<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;

final class FeedItemTest extends TestCase
{
    #[Test]
    public function currentPriceReturnsBasePriceWhenNoSale(): void
    {
        $item = FeedItems::item(price: 2500, salePrice: null);

        self::assertSame(2500, $item->currentPrice());
    }

    #[Test]
    public function currentPriceReturnsSalePriceWhenPresent(): void
    {
        $item = FeedItems::item(price: 2500, salePrice: 1990);

        self::assertSame(1990, $item->currentPrice());
    }

    #[Test]
    public function formatAmountFormatsCorrectly(): void
    {
        self::assertSame('25.00 EUR', FeedItem::formatAmount(2500, 'EUR'));
        self::assertSame('0.99 USD', FeedItem::formatAmount(99, 'USD'));
        self::assertSame('0.00 EUR', FeedItem::formatAmount(0, 'EUR'));
        self::assertSame('100.50 GBP', FeedItem::formatAmount(10050, 'GBP'));
    }

    #[Test]
    public function hasNoIdentifierWhenNoGtinNoMpnNoFlags(): void
    {
        $item = FeedItems::item(gtin: null, mpn: null, sharedIdentifierIgnored: false);

        self::assertTrue($item->hasNoIdentifier());
    }

    #[Test]
    public function hasNoIdentifierFalseWhenGtinPresent(): void
    {
        $item = FeedItems::item(gtin: '4006381333931', mpn: null);

        self::assertFalse($item->hasNoIdentifier());
    }

    #[Test]
    public function hasNoIdentifierFalseWhenMpnPresent(): void
    {
        $item = FeedItems::item(gtin: null, mpn: 'ABC-123');

        self::assertFalse($item->hasNoIdentifier());
    }

    #[Test]
    public function hasNoIdentifierFalseWhenSharedIdentifierIgnored(): void
    {
        $item = FeedItems::item(gtin: null, mpn: null, sharedIdentifierIgnored: true);

        self::assertFalse($item->hasNoIdentifier());
    }

    #[Test]
    public function hasNoIdentifierFalseWhenIdentifierWithheld(): void
    {
        $item = FeedItems::item(gtin: null, mpn: null, sharedIdentifierIgnored: false);
        $withoutGtin = $item->withoutGtin();

        self::assertFalse($withoutGtin->hasNoIdentifier());
    }

    #[Test]
    public function withoutGtinNullsGtinAndSetsIdentifierWithheld(): void
    {
        $item = FeedItems::item(gtin: '4006381333931');
        $copy = $item->withoutGtin();

        self::assertNull($copy->gtin);
        self::assertTrue($copy->identifierWithheld);
        self::assertSame($item->id, $copy->id);
        self::assertSame($item->title, $copy->title);
        self::assertSame($item->price, $copy->price);
        self::assertSame($item->mpn, $copy->mpn);
    }

    #[Test]
    public function taxExclusiveCountries(): void
    {
        self::assertSame(['US', 'CA'], FeedItem::TAX_EXCLUSIVE_COUNTRIES);
    }

    #[Test]
    public function constants(): void
    {
        self::assertSame('in_stock', FeedItem::IN_STOCK);
        self::assertSame('out_of_stock', FeedItem::OUT_OF_STOCK);
    }
}
