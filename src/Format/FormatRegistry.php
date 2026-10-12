<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Format;

/** The formats tagged "tmonier_agentic_feeds.format", by code. */
final class FormatRegistry
{
    /** @var array<string, FeedFormatInterface> */
    private array $formats = [];

    /** @param iterable<FeedFormatInterface> $formats */
    public function __construct(iterable $formats)
    {
        foreach ($formats as $format) {
            $this->formats[$format->getCode()] = $format;
        }
    }

    public function get(string $code): FeedFormatInterface
    {
        return $this->formats[$code] ?? throw new \InvalidArgumentException(sprintf('Unknown feed format "%s".', $code));
    }

    public function has(string $code): bool
    {
        return isset($this->formats[$code]);
    }

    /** @return array<string, FeedFormatInterface> */
    public function all(): array
    {
        return $this->formats;
    }

    /** File name of a role of a feed, e.g. "google-fr.xml", "stripe-inventory.csv". */
    public static function fileName(string $feedCode, string $suffix): string
    {
        return $feedCode . $suffix;
    }
}
