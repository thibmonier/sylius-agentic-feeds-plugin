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
        /** The price includes the tax (as displayed by the shop; never in US/CA feeds). */
        public readonly bool $taxIncluded = true,
        /** Characters invalid in XML (control characters) were removed from the texts. */
        public readonly bool $textCleaned = false,
        /** Physical product: shipping information applies (Stripe "shipping"). */
        public readonly bool $shippingRequired = true,
        /** The product has a GTIN/MPN attribute but several variants: not repeated on each variant. */
        public readonly bool $sharedIdentifierIgnored = false,
    ) {
    }

    /** The same item without GTIN (duplicate of another item of the feed). */
    public function withoutGtin(): self
    {
        return new self(
            $this->id,
            $this->itemGroupId,
            $this->productCode,
            $this->title,
            $this->description,
            $this->link,
            $this->imageLink,
            $this->additionalImageLinks,
            $this->price,
            $this->currency,
            $this->salePrice,
            $this->salePriceStart,
            $this->salePriceEnd,
            $this->availability,
            $this->quantity,
            null,
            $this->mpn,
            $this->brand,
            $this->condition,
            $this->productType,
            $this->weightKg,
            $this->options,
            $this->hasVariants,
            $this->sellerName,
            $this->sellerUrl,
            $this->color,
            $this->size,
            $this->taxIncluded,
            $this->textCleaned,
            $this->shippingRequired,
            $this->sharedIdentifierIgnored,
        );
    }

    /** Current price (the sale price when there is one). */
    public function currentPrice(): int
    {
        return $this->salePrice ?? $this->price;
    }

    /** Countries whose feeds show prices without tax (Google: "Don't include any taxes" in the US and Canada). */
    public const TAX_EXCLUSIVE_COUNTRIES = ['US', 'CA'];

    public static function formatAmount(int $amount, string $currency): string
    {
        return sprintf('%s %s', number_format($amount / 100, 2, '.', ''), $currency);
    }
}
