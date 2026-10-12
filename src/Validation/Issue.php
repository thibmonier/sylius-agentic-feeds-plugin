<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Validation;

/** A finding on one item; errors leave the item out of the file, warnings do not. */
final class Issue
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const MISSING_PRICE = 'missing_price';

    public const MISSING_FIELD = 'missing_field';

    public const TOO_LONG = 'too_long';

    public const INVALID_GTIN = 'invalid_gtin';

    public const NO_IDENTIFIERS = 'no_identifiers';

    public const NO_SALE_END_DATE = 'no_sale_end_date';

    /** @param array<string, string|int> $parameters */
    public function __construct(
        public readonly string $severity,
        public readonly string $code,
        public readonly ?string $field = null,
        public readonly array $parameters = [],
    ) {
    }

    public static function error(string $code, ?string $field = null, array $parameters = []): self
    {
        return new self(self::ERROR, $code, $field, $parameters);
    }

    public static function warning(string $code, ?string $field = null, array $parameters = []): self
    {
        return new self(self::WARNING, $code, $field, $parameters);
    }

    public function isError(): bool
    {
        return self::ERROR === $this->severity;
    }
}
