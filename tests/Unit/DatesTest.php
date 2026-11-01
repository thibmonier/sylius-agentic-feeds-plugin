<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Format\Dates;

final class DatesTest extends TestCase
{
    #[Test]
    public function isoFormatsWithTimezoneOffset(): void
    {
        $date = new \DateTimeImmutable('2026-10-12 10:00:00+02:00');

        self::assertSame('2026-10-12T10:00+0200', Dates::iso($date));
    }

    #[Test]
    public function isoFormatsUtc(): void
    {
        $date = new \DateTimeImmutable('2026-01-01 00:00:00+00:00');

        self::assertSame('2026-01-01T00:00+0000', Dates::iso($date));
    }

    #[Test]
    public function dayFormatsDateOnly(): void
    {
        $date = new \DateTimeImmutable('2026-10-31 23:59:00+01:00');

        self::assertSame('2026-10-31', Dates::day($date));
    }
}
