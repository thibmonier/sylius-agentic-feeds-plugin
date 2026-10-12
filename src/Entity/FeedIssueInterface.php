<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Entity;

use Sylius\Resource\Model\ResourceInterface;

/** One finding of the last generation of a feed (replaced at each generation). */
interface FeedIssueInterface extends ResourceInterface
{
    public const SEVERITY_ERROR = 'error';

    public const SEVERITY_WARNING = 'warning';

    public function getFeed(): ?ProductFeedInterface;

    public function setFeed(?ProductFeedInterface $feed): void;

    public function getSeverity(): string;

    public function setSeverity(string $severity): void;

    /** Translation key suffix: tmonier_agentic_feeds.issue.<code>. */
    public function getCode(): string;

    public function setCode(string $code): void;

    public function getField(): ?string;

    public function setField(?string $field): void;

    public function getItemId(): ?string;

    public function setItemId(?string $itemId): void;

    public function getProductCode(): ?string;

    public function setProductCode(?string $productCode): void;

    /** @return array<string, string|int> */
    public function getParameters(): array;

    /** @param array<string, string|int> $parameters */
    public function setParameters(array $parameters): void;

    public function getCreatedAt(): ?\DateTimeInterface;
}
