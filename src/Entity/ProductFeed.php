<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Entity;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Model\TimestampableTrait;
use Sylius\Resource\Model\ToggleableTrait;

class ProductFeed implements ProductFeedInterface
{
    use TimestampableTrait;
    use ToggleableTrait;

    protected ?int $id = null;

    protected ?string $code = null;

    protected ?string $name = null;

    protected ?ChannelInterface $channel = null;

    protected ?string $localeCode = null;

    protected ?string $currencyCode = null;

    protected ?string $countryCode = null;

    protected ?string $format = null;

    protected ?string $accessToken = null;

    protected ?\DateTimeInterface $lastGeneratedAt = null;

    protected string $lastStatus = self::STATUS_NEVER;

    protected int $lastItemCount = 0;

    protected int $lastErrorCount = 0;

    protected int $lastWarningCount = 0;

    protected ?int $lastDurationMs = null;

    protected ?string $lastFailure = null;

    protected ?\DateTimeInterface $staleSince = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getLocaleCode(): ?string
    {
        return $this->localeCode;
    }

    public function setLocaleCode(?string $localeCode): void
    {
        $this->localeCode = $localeCode;
    }

    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(?string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): void
    {
        $this->countryCode = null === $countryCode ? null : strtoupper($countryCode);
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function setFormat(?string $format): void
    {
        $this->format = $format;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): void
    {
        $this->accessToken = null === $accessToken || '' === $accessToken ? null : $accessToken;
    }

    public function getLastGeneratedAt(): ?\DateTimeInterface
    {
        return $this->lastGeneratedAt;
    }

    public function getLastStatus(): string
    {
        return $this->lastStatus;
    }

    public function getLastItemCount(): int
    {
        return $this->lastItemCount;
    }

    public function getLastErrorCount(): int
    {
        return $this->lastErrorCount;
    }

    public function getLastWarningCount(): int
    {
        return $this->lastWarningCount;
    }

    public function getLastDurationMs(): ?int
    {
        return $this->lastDurationMs;
    }

    public function getLastFailure(): ?string
    {
        return $this->lastFailure;
    }

    public function recordGeneration(\DateTimeInterface $at, string $status, int $items, int $errors, int $warnings, int $durationMs, ?string $failure = null): void
    {
        $this->lastStatus = $status;
        $this->lastDurationMs = $durationMs;
        $this->lastFailure = null === $failure ? null : mb_substr($failure, 0, 1000);
        if (self::STATUS_FAILED === $status) {
            // The previous file is still served: keep its counts and date.
            return;
        }
        $this->lastGeneratedAt = $at;
        $this->lastItemCount = $items;
        $this->lastErrorCount = $errors;
        $this->lastWarningCount = $warnings;
    }

    public function getStaleSince(): ?\DateTimeInterface
    {
        return $this->staleSince;
    }

    public function setStaleSince(?\DateTimeInterface $staleSince): void
    {
        $this->staleSince = $staleSince;
    }
}
