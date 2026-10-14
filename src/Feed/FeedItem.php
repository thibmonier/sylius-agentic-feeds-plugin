<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Feed;

/**
 * One variant, in neutral form, before a format maps it to its fields. Amounts in minor units
 * of $currency, weight in kg, texts plain (no HTML).
 */
final readonly class FeedItem
{
    public const IN_STOCK = 'in_stock';

    public const OUT_OF_STOCK = 'out_of_stock';

    /**
     * @param list<string> $additionalImageLinks
     * @param array<string, string> $options option name => value (e.g. "Size" => "M")
     */
    public function __construct(
        public string $id,
        public string $itemGroupId,
        public string $productCode,
        public string $title,
        public string $description,
        public string $link,
        public ?string $imageLink,
        public array $additionalImageLinks,
        public int $price,
        public string $currency,
        public ?int $salePrice,
        public ?\DateTimeInterface $salePriceStart,
        public ?\DateTimeInterface $salePriceEnd,
        public string $availability,
        public ?int $quantity,
        public ?string $gtin,
        public ?string $mpn,
        public ?string $brand,
        public string $condition,
        public ?string $productType,
        public ?float $weightKg,
        public array $options,
        public bool $hasVariants,
        public ?string $sellerName = null,
        public ?string $sellerUrl = null,
        public ?string $color = null,
        public ?string $size = null,
        /** The price includes the tax (as displayed by the shop; never in US/CA feeds). */
        public bool $taxIncluded = true,
        /** Characters invalid in XML (control characters) were removed from the texts. */
        public bool $textCleaned = false,
        /** Physical product: shipping information applies (Stripe "shipping"). */
        public bool $shippingRequired = true,
        /** The product has a GTIN/MPN attribute but several variants: not repeated on each variant. */
        public bool $sharedIdentifierIgnored = false,
        /** The channel has no default tax zone: whether the price includes tax is unknown. */
        public bool $taxUnknown = false,
        /** A GTIN exists but is not sent (duplicate of another item). */
        public bool $identifierWithheld = false,
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
            $this->taxUnknown,
            true,
        );
    }

    /**
     * The product genuinely has no identifier: no GTIN (valid or not), no MPN, none left out
     * (Google identifier_exists "no" only when certain).
     */
    public function hasNoIdentifier(): bool
    {
        return null === $this->gtin && null === $this->mpn && !$this->sharedIdentifierIgnored && !$this->identifierWithheld;
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
