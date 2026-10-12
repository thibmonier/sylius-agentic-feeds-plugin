<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Entity;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Model\CodeAwareInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;

/** A feed of the products of one channel in one locale and currency, in one target format. */
interface ProductFeedInterface extends ResourceInterface, CodeAwareInterface, ToggleableInterface, TimestampableInterface
{
    public const STATUS_NEVER = 'never';

    public const STATUS_OK = 'ok';

    /** Generated; some items have warnings. */
    public const STATUS_WARNINGS = 'warnings';

    /** Generated; items with errors were left out of the file. */
    public const STATUS_ERRORS = 'errors';

    /** Generation failed: the previous file (if any) is still served. */
    public const STATUS_FAILED = 'failed';

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getChannel(): ?ChannelInterface;

    public function setChannel(?ChannelInterface $channel): void;

    public function getLocaleCode(): ?string;

    public function setLocaleCode(?string $localeCode): void;

    public function getCurrencyCode(): ?string;

    public function setCurrencyCode(?string $currencyCode): void;

    /** ISO 3166-1 alpha-2 country the offers target (shipping and tax hints, Google). */
    public function getCountryCode(): ?string;

    public function setCountryCode(?string $countryCode): void;

    /** One of the registered format codes, e.g. "google_xml". */
    public function getFormat(): ?string;

    public function setFormat(?string $format): void;

    /** Null: the feed URL is public. Otherwise required as ?token= or the X-Feed-Token header. */
    public function getAccessToken(): ?string;

    public function setAccessToken(?string $accessToken): void;

    public function getLastGeneratedAt(): ?\DateTimeInterface;

    public function getLastStatus(): string;

    public function getLastItemCount(): int;

    public function getLastErrorCount(): int;

    public function getLastWarningCount(): int;

    public function getLastDurationMs(): ?int;

    public function getLastFailure(): ?string;

    public function recordGeneration(\DateTimeInterface $at, string $status, int $items, int $errors, int $warnings, int $durationMs, ?string $failure = null): void;

    /** Catalog data changed since the last generation (incremental regeneration). */
    public function getStaleSince(): ?\DateTimeInterface;

    public function setStaleSince(?\DateTimeInterface $staleSince): void;
}
