<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;

final class IssueTest extends TestCase
{
    #[Test]
    public function errorFactory(): void
    {
        $issue = Issue::error(Issue::MISSING_PRICE, 'price', ['%min%' => 1]);

        self::assertSame(Issue::ERROR, $issue->severity);
        self::assertSame(Issue::MISSING_PRICE, $issue->code);
        self::assertSame('price', $issue->field);
        self::assertSame(['%min%' => 1], $issue->parameters);
        self::assertTrue($issue->isError());
    }

    #[Test]
    public function warningFactory(): void
    {
        $issue = Issue::warning(Issue::TOO_LONG, 'title', ['%max%' => 150]);

        self::assertSame(Issue::WARNING, $issue->severity);
        self::assertSame(Issue::TOO_LONG, $issue->code);
        self::assertSame('title', $issue->field);
        self::assertSame(['%max%' => 150], $issue->parameters);
        self::assertFalse($issue->isError());
    }

    #[Test]
    public function errorWithoutFieldOrParameters(): void
    {
        $issue = Issue::error(Issue::MISSING_FIELD);

        self::assertNull($issue->field);
        self::assertSame([], $issue->parameters);
    }

    #[Test]
    public function constructorDirectly(): void
    {
        $issue = new Issue('custom', 'custom_code', 'field', ['key' => 'value']);

        self::assertSame('custom', $issue->severity);
        self::assertSame('custom_code', $issue->code);
        self::assertSame('field', $issue->field);
        self::assertFalse($issue->isError());
    }

    #[Test]
    public function constants(): void
    {
        self::assertSame('error', Issue::ERROR);
        self::assertSame('warning', Issue::WARNING);
        self::assertSame('missing_price', Issue::MISSING_PRICE);
        self::assertSame('missing_field', Issue::MISSING_FIELD);
        self::assertSame('too_long', Issue::TOO_LONG);
        self::assertSame('invalid_gtin', Issue::INVALID_GTIN);
        self::assertSame('no_identifiers', Issue::NO_IDENTIFIERS);
        self::assertSame('no_sale_end_date', Issue::NO_SALE_END_DATE);
        self::assertSame('invalid_characters', Issue::INVALID_CHARACTERS);
        self::assertSame('price_excludes_tax', Issue::PRICE_EXCLUDES_TAX);
        self::assertSame('tax_unknown', Issue::TAX_UNKNOWN);
        self::assertSame('shared_identifier', Issue::SHARED_IDENTIFIER);
        self::assertSame('duplicate_gtin', Issue::DUPLICATE_GTIN);
        self::assertSame('unsupported_country', Issue::UNSUPPORTED_COUNTRY);
        self::assertSame('missing_shipping', Issue::MISSING_SHIPPING);
    }
}
