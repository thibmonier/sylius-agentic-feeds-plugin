<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Format;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;

/**
 * Google Merchant Center tab-separated file (answer 160567, read 2026-10-12): header row of
 * attribute names, no tabs or line breaks inside fields; repeated attributes as repeated columns.
 */
final class GoogleTsvFormat implements FeedFormatInterface
{
    public const CODE = 'google_tsv';

    /** Columns, in order; additional_image_link repeated up to 10 times. */
    private const COLUMNS = ['id', 'title', 'description', 'link', 'image_link', 'availability', 'price', 'sale_price', 'sale_price_effective_date', 'brand', 'gtin', 'mpn', 'identifier_exists', 'condition', 'item_group_id', 'product_type', 'color', 'size', 'shipping_weight'];

    private const ADDITIONAL_IMAGES = 10;

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'tmonier_agentic_feeds.format.google_tsv';
    }

    public function getFiles(): array
    {
        return ['main' => '.tsv'];
    }

    public function getContentType(string $role): string
    {
        return 'text/tab-separated-values; charset=UTF-8';
    }

    public function validate(FeedItem $item): array
    {
        return GoogleFields::validate($item);
    }

    public function open(FeedContext $context, array $streams): FeedWriterInterface
    {
        $stream = $streams['main'];
        fwrite($stream, implode("\t", [...self::COLUMNS, ...array_fill(0, self::ADDITIONAL_IMAGES, 'additional_image_link')]) . "\n");

        return new class($context, $stream) implements FeedWriterInterface {
            /** @param resource $stream */
            public function __construct(private readonly FeedContext $context, private $stream)
            {
            }

            public function write(FeedItem $item): void
            {
                $fields = GoogleFields::map($item, $this->context);
                $row = [];
                foreach (GoogleTsvFormat::columns() as $column) {
                    $value = $fields[$column] ?? '';
                    $row[] = \is_array($value) ? '' : $value;
                }
                $images = $fields['additional_image_link'] ?? [];
                $images = \is_array($images) ? $images : [];
                for ($index = 0; $index < 10; ++$index) {
                    $row[] = (string) ($images[$index] ?? '');
                }
                fwrite($this->stream, implode("\t", array_map(static fn (string $value): string => str_replace(["\t", "\r", "\n"], ' ', $value), $row)) . "\n");
            }

            public function finish(): void
            {
            }
        };
    }

    /** @return list<string> */
    public static function columns(): array
    {
        return self::COLUMNS;
    }
}
