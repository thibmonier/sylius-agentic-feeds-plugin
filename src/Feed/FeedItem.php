<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Feed;

/**
 * One variant, in neutral form, before a format maps it to its fields. Amounts in minor units
 * of $currency, weight in kg, texts plain (no HTML).
 */
final class FeedItem
{
    public const IN_STOCK = 'in_stock';

    public const OUT_OF_STOCK = 'out_of_stock';

    /**
     * @param list<string> $additionalImageLinks
     * @param array<string, string> $options option name => value (e.g. "Size" => "M")
     */
    public function __construct(
        public readonly string $id,
        public readonly string $itemGroupId,
        public readonly string $productCode,
        public readonly string $title,
        public readonly string $description,
        public readonly string $link,
        public readonly ?string $imageLink,
        public readonly array $additionalImageLinks,
        public readonly int $price,
        public readonly string $currency,
        public readonly ?int $salePrice,
        public readonly ?\DateTimeInterface $salePriceStart,
        public readonly ?\DateTimeInterface $salePriceEnd,
        public readonly string $availability,
        public readonly ?int $quantity,
        public readonly ?string $gtin,
        public readonly ?string $mpn,
        public readonly ?string $brand,
        public readonly string $condition,
        public readonly ?string $productType,
        public readonly ?float $weightKg,
        public readonly array $options,
        public readonly bool $hasVariants,
        public readonly ?string $sellerName = null,
        public readonly ?string $sellerUrl = null,
        public readonly ?string $color = null,
        public readonly ?string $size = null,
        /** Price includes the tax of the channel default tax zone (or the zone has none). */
        public readonly bool $taxIncluded = true,
    ) {
    }

    /** Current price (the sale price when there is one). */
    public function currentPrice(): int
    {
        return $this->salePrice ?? $this->price;
    }

    public static function formatAmount(int $amount, string $currency): string
    {
        return sprintf('%s %s', number_format($amount / 100, 2, '.', ''), $currency);
    }
}
