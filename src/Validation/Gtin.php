<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Validation;

/**
 * GTIN checks of Google Merchant Center (answer 6324461, read 2026-10-12): 8, 12, 13 or 14
 * digits (spaces and dashes ignored), valid GS1 check digit, no restricted (02, 04, 2) or coupon
 * (05, 98, 99) prefixes.
 */
final class Gtin
{
    /** Normalised GTIN (digits only), null when invalid. */
    public static function normalize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $digits = str_replace([' ', '-'], '', $value);
        if (1 !== preg_match('/^\d+$/', $digits) || !\in_array(\strlen($digits), [8, 12, 13, 14], true)) {
            return null;
        }
        if (!self::hasValidCheckDigit($digits) || self::hasRestrictedPrefix($digits)) {
            return null;
        }

        return $digits;
    }

    public static function hasValidCheckDigit(string $digits): bool
    {
        $body = substr($digits, 0, -1);
        $sum = 0;
        // From the right, weights 3, 1, 3, 1... on the digits before the check digit.
        foreach (array_reverse(str_split($body)) as $index => $digit) {
            $sum += (int) $digit * (0 === $index % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === (int) substr($digits, -1);
    }

    private static function hasRestrictedPrefix(string $digits): bool
    {
        if (8 === \strlen($digits)) {
            return false;
        }
        // GTIN-13 form: UPC-A gets a leading 0, GTIN-14 loses its packaging indicator.
        $gtin13 = match (\strlen($digits)) {
            12 => '0' . $digits,
            14 => substr($digits, 1),
            default => $digits,
        };

        return str_starts_with($gtin13, '2') || \in_array(substr($gtin13, 0, 2), ['02', '04', '05', '98', '99'], true);
    }
}
