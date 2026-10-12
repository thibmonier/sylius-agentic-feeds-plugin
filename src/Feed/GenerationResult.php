<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Feed;

use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;

final class GenerationResult
{
    private int $items = 0;

    private int $errors = 0;

    private int $warnings = 0;

    /** @var list<array{Issue, ?string, ?string}> issue, item id, product code */
    private array $issues = [];

    private ?string $failure = null;

    private int $durationMs = 0;

    public function __construct(public readonly string $feedCode)
    {
    }

    public function add(Issue $issue, ?string $itemId, ?string $productCode): void
    {
        $issue->isError() ? ++$this->errors : ++$this->warnings;
        $this->issues[] = [$issue, $itemId, $productCode];
    }

    public function itemWritten(): void
    {
        ++$this->items;
    }

    public function fail(string $message): void
    {
        $this->failure = $message;
    }

    public function finish(int $durationMs): void
    {
        $this->durationMs = $durationMs;
    }

    public function status(): string
    {
        return match (true) {
            null !== $this->failure => ProductFeedInterface::STATUS_FAILED,
            $this->errors > 0 => ProductFeedInterface::STATUS_ERRORS,
            $this->warnings > 0 => ProductFeedInterface::STATUS_WARNINGS,
            default => ProductFeedInterface::STATUS_OK,
        };
    }

    public function failed(): bool
    {
        return null !== $this->failure;
    }

    public function failure(): ?string
    {
        return $this->failure;
    }

    public function items(): int
    {
        return $this->items;
    }

    public function errorCount(): int
    {
        return $this->errors;
    }

    public function warningCount(): int
    {
        return $this->warnings;
    }

    /** Items left out (errors) and failures make a CI check fail. */
    public function hasErrors(): bool
    {
        return $this->errors > 0 || null !== $this->failure;
    }

    /** @return list<array{Issue, ?string, ?string}> */
    public function issues(): array
    {
        return $this->issues;
    }

    public function durationMs(): int
    {
        return $this->durationMs;
    }
}
