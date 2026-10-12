<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Gtin;

final class GtinTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function values(): iterable
    {
        yield 'EAN-13' => ['4006381333931', '4006381333931'];
        yield 'EAN-13 with spaces and dashes' => ['400-6381 333931', '4006381333931'];
        yield 'UPC-A' => ['036000291452', '036000291452'];
        yield 'EAN-8' => ['96385074', '96385074'];
        yield 'GTIN-14' => ['10036000291459', '10036000291459'];
        yield 'wrong check digit' => ['4006381333932', null];
        yield 'wrong length' => ['400638133393', null];
        yield 'letters' => ['40063813339A1', null];
        yield 'restricted 2 prefix' => ['2000000000008', null];
        yield 'coupon 99 prefix' => ['9900000000004', null];
        yield 'restricted UPC 4 prefix' => ['400000000008', null];
    }

    #[DataProvider('values')]
    public function testNormalize(string $value, ?string $expected): void
    {
        self::assertSame($expected, Gtin::normalize($value));
    }
}
