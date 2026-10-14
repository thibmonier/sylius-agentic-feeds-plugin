<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Format;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Gtin;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\ItemValidator;

/**
 * Stripe Agentic Commerce Suite catalog feed (docs.stripe.com/agentic-commerce/concepts/
 * catalog-feed, read 2026-10-12): product CSV (daily), plus inventory and pricing CSVs (every
 * 15 minutes). One row per variant, header row, UTF-8. Uploaded by the merchant (Dashboard or
 * API, not done by this plugin). A sale needs an end date in this format: without one, the
 * current price is the price (no reduction shown).
 */
final class StripeCsvFormat implements FeedFormatInterface
{
    public const CODE = 'stripe_csv';

    public const MAX = ['id' => 100, 'title' => 150, 'description' => 5000];

    public const REQUIRED = ['title', 'description', 'link', 'image', 'brand'];

    private const PRODUCT = ['id', 'title', 'description', 'link', 'image_link', 'additional_image_link', 'availability', 'inventory_quantity', 'inventory_not_tracked', 'price', 'sale_price', 'sale_price_effective_date', 'tax_behavior', 'brand', 'gtin', 'mpn', 'condition', 'product_category', 'item_group_id', 'color', 'size', 'weight', 'shipping'];

    private const INVENTORY = ['id', 'availability', 'inventory_quantity'];

    private const PRICING = ['id', 'price', 'sale_price', 'sale_price_effective_date'];

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'tmonier_agentic_feeds.format.stripe_csv';
    }

    public function getFiles(): array
    {
        return ['main' => '.csv', 'inventory' => '-inventory.csv', 'pricing' => '-pricing.csv'];
    }

    public function getContentType(string $role): string
    {
        return 'text/csv; charset=UTF-8';
    }

    public function validate(FeedItem $item): array
    {
        $issues = ItemValidator::validate($item, self::MAX, self::REQUIRED);
        if (null !== $item->salePrice && !$item->salePriceEnd instanceof \DateTimeInterface) {
            $issues[] = Issue::warning(Issue::NO_SALE_END_DATE, 'sale_price');
        }

        return $issues;
    }

    /** @return array<string, string> */
    public function map(FeedItem $item, FeedContext $context): array
    {
        $sale = null !== $item->salePrice && $item->salePriceEnd instanceof \DateTimeInterface;

        return [
            'id' => $item->id,
            'title' => ItemValidator::cut($item->title, self::MAX['title']),
            'description' => ItemValidator::cut($item->description, self::MAX['description']),
            'link' => $item->link,
            'image_link' => (string) $item->imageLink,
            'additional_image_link' => implode(',', array_map(static fn (string $url): string => str_replace(',', '%2C', $url), $item->additionalImageLinks)),
            'availability' => $item->availability,
            'inventory_quantity' => null === $item->quantity ? '' : (string) $item->quantity,
            'inventory_not_tracked' => null === $item->quantity ? 'true' : 'false',
            'price' => FeedItem::formatAmount($sale ? $item->price : $item->currentPrice(), $item->currency),
            'sale_price' => $sale ? FeedItem::formatAmount((int) $item->salePrice, $item->currency) : '',
            'sale_price_effective_date' => $sale ? Dates::day($item->salePriceStart ?? $context->now) . '/' . Dates::day($item->salePriceEnd) : '',
            'tax_behavior' => $item->taxIncluded ? 'inclusive' : 'exclusive',
            'brand' => (string) $item->brand,
            'gtin' => (string) Gtin::normalize($item->gtin),
            'mpn' => (string) $item->mpn,
            'condition' => $item->condition,
            'product_category' => (string) $item->productType,
            'item_group_id' => $item->hasVariants ? ItemValidator::cut($item->itemGroupId, 70) : '',
            'color' => (string) $item->color,
            'size' => null === $item->size ? '' : ItemValidator::cut($item->size, 20),
            'weight' => null === $item->weightKg ? '' : sprintf('%s kg', rtrim(rtrim(number_format($item->weightKg, 3, '.', ''), '0'), '.')),
            'shipping' => $item->shippingRequired ? implode(',', $context->shippingRules) : '',
        ];
    }

    /** One entry: country:delivery_area:service:speed_range:price, e.g. "FR:ALL:Standard:2-4:9.90 EUR". */
    public const SHIPPING_ENTRY = '/^[A-Z]{2}:[^:,]+:[^:,]+:(\d+-\d+)?:\d+(\.\d{1,2})? [A-Z]{3}$/';

    /** Stripe requires "shipping" for physical goods: the feed needs shipping entries. */
    public function validateFeed(FeedContext $context): array
    {
        return [] === $context->shippingRules ? [Issue::error(Issue::MISSING_SHIPPING, 'shipping')] : [];
    }

    public function open(FeedContext $context, array $streams): FeedWriterInterface
    {
        foreach (['main' => self::PRODUCT, 'inventory' => self::INVENTORY, 'pricing' => self::PRICING] as $role => $columns) {
            fputcsv($streams[$role], $columns, ',', '"', '');
        }

        return new class($this, $context, $streams) implements FeedWriterInterface {
            /** @param array<string, resource> $streams */
            public function __construct(private readonly StripeCsvFormat $format, private readonly FeedContext $context, private readonly array $streams)
            {
            }

            public function write(FeedItem $item): void
            {
                $row = $this->format->map($item, $this->context);
                fputcsv($this->streams['main'], array_values($row), ',', '"', '');
                // Inventory feed: tracked variants only (inventory_quantity is required there).
                if (null !== $item->quantity) {
                    fputcsv($this->streams['inventory'], [$row['id'], $row['availability'], $row['inventory_quantity']], ',', '"', '');
                }
                fputcsv($this->streams['pricing'], [$row['id'], $row['price'], $row['sale_price'], $row['sale_price_effective_date']], ',', '"', '');
            }

            public function finish(): void
            {
            }
        };
    }
}
