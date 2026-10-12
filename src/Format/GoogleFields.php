<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Format;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Gtin;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\ItemValidator;

/**
 * Google Merchant Center product data specification (support.google.com/merchants/answer/7052112,
 * read 2026-10-12). Shared by the XML and TSV formats. Shipping and tax are left to the Merchant
 * Center account settings (recommended by Google); VAT is included in the price.
 */
final class GoogleFields
{
    public const MAX = ['id' => 50, 'title' => 150, 'description' => 5000];

    public const REQUIRED = ['title', 'description', 'link', 'image', 'brand'];

    /** @return list<Issue> */
    public static function validate(FeedItem $item): array
    {
        return ItemValidator::validate($item, self::MAX, self::REQUIRED);
    }

    /**
     * Fields in Google order; repeated attributes as lists. Empty attributes are left out.
     *
     * @return array<string, string|list<string>>
     */
    public static function map(FeedItem $item, FeedContext $context): array
    {
        $gtin = Gtin::normalize($item->gtin);
        $fields = [
            'id' => $item->id,
            'title' => ItemValidator::cut($item->title, self::MAX['title']),
            'description' => ItemValidator::cut($item->description, self::MAX['description']),
            'link' => $item->link,
            'image_link' => (string) $item->imageLink,
            'additional_image_link' => $item->additionalImageLinks,
            'availability' => $item->availability,
            'price' => FeedItem::formatAmount($item->price, $item->currency),
            'sale_price' => null === $item->salePrice ? '' : FeedItem::formatAmount($item->salePrice, $item->currency),
            'sale_price_effective_date' => null === $item->salePrice || null === $item->salePriceEnd ? '' : Dates::iso($item->salePriceStart ?? $context->now) . '/' . Dates::iso($item->salePriceEnd),
            'brand' => (string) $item->brand,
            'gtin' => (string) $gtin,
            'mpn' => (string) $item->mpn,
            'identifier_exists' => $item->hasNoIdentifier() ? 'no' : '',
            'condition' => $item->condition,
            'item_group_id' => $item->hasVariants ? $item->itemGroupId : '',
            'product_type' => (string) $item->productType,
            'color' => (string) $item->color,
            'size' => (string) $item->size,
            'shipping_weight' => null === $item->weightKg ? '' : sprintf('%s kg', rtrim(rtrim(number_format($item->weightKg, 3, '.', ''), '0'), '.')),
        ];

        return array_filter($fields, static fn (string|array $value): bool => '' !== $value && [] !== $value);
    }
}
