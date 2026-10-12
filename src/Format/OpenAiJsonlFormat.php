<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Format;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Gtin;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\ItemValidator;

/**
 * OpenAI ChatGPT product feed, file upload format (developers.openai.com/commerce/specs/
 * file-upload/products, read 2026-10-12): gzipped JSON Lines, one object per variant, UTF-8,
 * fields left out instead of placeholders. Onboarding is limited to partners approved by
 * OpenAI; the file is delivered by SFTP (not done by this plugin). Checkout eligibility is
 * always false: buyers are sent to the shop.
 */
final class OpenAiJsonlFormat implements FeedFormatInterface
{
    public const CODE = 'openai_jsonl';

    public const MAX = ['id' => 255, 'title' => 150, 'description' => 5000];

    public const REQUIRED = ['title', 'description', 'link', 'image', 'brand'];

    /** @param array<string, array{return_policy: ?string, privacy_policy: ?string, terms: ?string, return_days: ?int}> $sellerPolicies by channel code */
    public function __construct(private readonly array $sellerPolicies = [])
    {
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'tmonier_agentic_feeds.format.openai_jsonl';
    }

    public function getFiles(): array
    {
        return ['main' => '.jsonl.gz'];
    }

    public function getContentType(string $role): string
    {
        return 'application/gzip';
    }

    public function validate(FeedItem $item): array
    {
        $issues = ItemValidator::validate($item, self::MAX, self::REQUIRED);
        if (null === $item->sellerName || '' === $item->sellerName) {
            $issues[] = Issue::error(Issue::MISSING_FIELD, 'seller_name');
        }

        return $issues;
    }

    /** @return array<string, mixed> */
    public function map(FeedItem $item, FeedContext $context): array
    {
        $gtin = Gtin::normalize($item->gtin);
        $policies = $this->sellerPolicies[(string) $context->channel->getCode()] ?? [];
        $fields = [
            'item_id' => $item->id,
            'title' => ItemValidator::cut($item->title, self::MAX['title']),
            'description' => ItemValidator::cut($item->description, self::MAX['description']),
            'url' => $item->link,
            'brand' => $item->brand,
            'seller_name' => $item->sellerName,
            'seller_url' => $item->sellerUrl,
            'image_url' => $item->imageLink,
            'additional_image_urls' => [] === $item->additionalImageLinks ? null : $item->additionalImageLinks,
            'availability' => $item->availability,
            'price' => FeedItem::formatAmount($item->price, $item->currency),
            // No sale date field in this format: the sale price only while it applies.
            'sale_price' => null !== $item->salePrice && $item->salePrice < $item->price ? FeedItem::formatAmount($item->salePrice, $item->currency) : null,
            'condition' => $item->condition,
            'product_category' => $item->productType,
            'gtin' => $gtin,
            'mpn' => $item->mpn,
            'color' => $item->color,
            'size' => $item->size,
            'weight' => $item->weightKg,
            'item_weight_unit' => null === $item->weightKg ? null : 'kg',
            'is_eligible_search' => true,
            'is_eligible_checkout' => false,
            'return_policy' => $policies['return_policy'] ?? null,
            'return_deadline_in_days' => $policies['return_days'] ?? null,
            'seller_privacy_policy' => $policies['privacy_policy'] ?? null,
            'seller_tos' => $policies['terms'] ?? null,
            'target_countries' => null === $context->countryCode ? null : [$context->countryCode],
        ];
        if ($item->hasVariants && $item->itemGroupId !== $item->id) {
            $fields['group_id'] = $item->itemGroupId;
            $fields['listing_has_variations'] = true;
            $fields['variant_dict'] = [] === $item->options ? null : $item->options;
        }

        return array_filter($fields, static fn (mixed $value): bool => null !== $value && '' !== $value);
    }

    public function open(FeedContext $context, array $streams): FeedWriterInterface
    {
        $stream = $streams['main'];
        // gzip (window 31: gzip header and trailer) while writing.
        $filter = stream_filter_append($stream, 'zlib.deflate', \STREAM_FILTER_WRITE, ['window' => 31, 'level' => 6]);

        return new class($this, $context, $stream, $filter) implements FeedWriterInterface {
            /**
             * @param resource $stream
             * @param resource|false $filter
             */
            public function __construct(private readonly OpenAiJsonlFormat $format, private readonly FeedContext $context, private $stream, private $filter)
            {
            }

            public function write(FeedItem $item): void
            {
                fwrite($this->stream, json_encode($this->format->map($item, $this->context), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR) . "\n");
            }

            public function finish(): void
            {
                if (false !== $this->filter) {
                    stream_filter_remove($this->filter);
                }
            }
        };
    }
}
