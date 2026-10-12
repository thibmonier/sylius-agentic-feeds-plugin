<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Format;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;

/**
 * A target format. Registered with the tag "tmonier_agentic_feeds.format" (attribute "code").
 * A format writes one main file and optional companion files (e.g. Stripe inventory and
 * pricing), each a stream opened by the generator.
 */
interface FeedFormatInterface
{
    public function getCode(): string;

    /** Translation key of the label. */
    public function getLabel(): string;

    /** @return array<string, string> file role => file name suffix ("" for the main file), e.g. ['main' => '.xml'] */
    public function getFiles(): array;

    public function getContentType(string $role): string;

    /**
     * Format-specific checks (lengths, required fields).
     *
     * @return list<Issue>
     */
    public function validate(FeedItem $item): array;

    /** @param array<string, resource> $streams one writable stream per role of getFiles() */
    public function open(FeedContext $context, array $streams): FeedWriterInterface;
}
