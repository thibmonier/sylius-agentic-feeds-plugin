<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Entity;

class FeedIssue implements FeedIssueInterface
{
    protected ?int $id = null;

    protected ?ProductFeedInterface $feed = null;

    protected string $severity = self::SEVERITY_ERROR;

    protected string $code = '';

    protected ?string $field = null;

    protected ?string $itemId = null;

    protected ?string $productCode = null;

    /** @var array<string, string|int> */
    protected array $parameters = [];

    protected ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeed(): ?ProductFeedInterface
    {
        return $this->feed;
    }

    public function setFeed(?ProductFeedInterface $feed): void
    {
        $this->feed = $feed;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): void
    {
        $this->severity = $severity;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setField(?string $field): void
    {
        $this->field = $field;
    }

    public function getItemId(): ?string
    {
        return $this->itemId;
    }

    public function setItemId(?string $itemId): void
    {
        $this->itemId = $itemId;
    }

    public function getProductCode(): ?string
    {
        return $this->productCode;
    }

    public function setProductCode(?string $productCode): void
    {
        $this->productCode = $productCode;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
