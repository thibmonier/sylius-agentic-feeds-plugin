<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\ItemValidator;

final class ItemValidatorTest extends TestCase
{
    private const MAX_LENGTHS = ['id' => 50, 'title' => 150, 'description' => 5000];

    private const REQUIRED = ['title', 'description', 'link', 'image', 'brand'];

    #[Test]
    public function validItemReturnsNoIssues(): void
    {
        $item = FeedItems::item();

        self::assertSame([], ItemValidator::validate($item, self::MAX_LENGTHS, self::REQUIRED));
    }

    #[Test]
    public function idTooLongReturnsError(): void
    {
        $item = FeedItems::item(id: str_repeat('X', 51));

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, self::REQUIRED);

        self::assertCount(1, $issues);
        self::assertSame(Issue::TOO_LONG, $issues[0]->code);
        self::assertSame('id', $issues[0]->field);
        self::assertTrue($issues[0]->isError());
    }

    #[Test]
    public function missingTitleReturnsError(): void
    {
        $item = FeedItems::item(title: '');

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, self::REQUIRED);

        $titleIssues = array_values(array_filter($issues, fn (Issue $i) => $i->field === 'title' && $i->code === Issue::MISSING_FIELD));
        self::assertCount(1, $titleIssues);
        self::assertTrue($titleIssues[0]->isError());
    }

    #[Test]
    public function missingImageReturnsError(): void
    {
        $item = FeedItems::item(imageLink: null);

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, self::REQUIRED);

        $imgIssues = array_values(array_filter($issues, fn (Issue $i) => $i->field === 'image' && $i->code === Issue::MISSING_FIELD));
        self::assertCount(1, $imgIssues);
    }

    #[Test]
    public function missingBrandReturnsErrorWhenRequired(): void
    {
        $item = FeedItems::item(brand: null);

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, ['brand']);

        self::assertCount(1, array_filter($issues, fn (Issue $i) => $i->field === 'brand' && $i->code === Issue::MISSING_FIELD));
    }

    #[Test]
    public function missingBrandNotCheckedWhenNotRequired(): void
    {
        $item = FeedItems::item(brand: null);

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, ['title']);

        self::assertEmpty(array_filter($issues, fn (Issue $i) => $i->field === 'brand'));
    }

    #[Test]
    public function titleTooLongReturnsWarning(): void
    {
        $item = FeedItems::item(title: str_repeat('A', 151));

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, []);

        $titleIssues = array_values(array_filter($issues, fn (Issue $i) => $i->field === 'title' && $i->code === Issue::TOO_LONG));
        self::assertCount(1, $titleIssues);
        self::assertFalse($titleIssues[0]->isError());
    }

    #[Test]
    public function descriptionTooLongReturnsWarning(): void
    {
        $item = FeedItems::item(description: str_repeat('B', 5001));

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, []);

        $descIssues = array_values(array_filter($issues, fn (Issue $i) => $i->field === 'description' && $i->code === Issue::TOO_LONG));
        self::assertCount(1, $descIssues);
        self::assertFalse($descIssues[0]->isError());
    }

    #[Test]
    public function zeroPriceReturnsError(): void
    {
        $item = FeedItems::item(price: 0);

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, []);

        $priceIssues = array_values(array_filter($issues, fn (Issue $i) => $i->code === Issue::MISSING_PRICE));
        self::assertCount(1, $priceIssues);
        self::assertTrue($priceIssues[0]->isError());
    }

    #[Test]
    public function negativePriceReturnsError(): void
    {
        $item = FeedItems::item(price: -100);

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, []);

        self::assertNotEmpty(array_filter($issues, fn (Issue $i) => $i->code === Issue::MISSING_PRICE));
    }

    #[Test]
    public function invalidGtinReturnsWarning(): void
    {
        $item = FeedItems::item(gtin: '000INVALID');

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, []);

        $gtinIssues = array_values(array_filter($issues, fn (Issue $i) => $i->code === Issue::INVALID_GTIN));
        self::assertCount(1, $gtinIssues);
        self::assertFalse($gtinIssues[0]->isError());
    }

    #[Test]
    public function noIdentifiersReturnsWarning(): void
    {
        $item = FeedItems::item(gtin: null, mpn: null);

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, []);

        $idIssues = array_values(array_filter($issues, fn (Issue $i) => $i->code === Issue::NO_IDENTIFIERS));
        self::assertCount(1, $idIssues);
        self::assertFalse($idIssues[0]->isError());
    }

    #[Test]
    public function validGtinNoWarning(): void
    {
        $item = FeedItems::item(gtin: '4006381333931');

        $issues = ItemValidator::validate($item, self::MAX_LENGTHS, []);

        self::assertEmpty(array_filter($issues, fn (Issue $i) => $i->code === Issue::INVALID_GTIN));
        self::assertEmpty(array_filter($issues, fn (Issue $i) => $i->code === Issue::NO_IDENTIFIERS));
    }

    #[Test]
    public function cutReturnsTextUnchangedWhenWithinLimit(): void
    {
        self::assertSame('Hello', ItemValidator::cut('Hello', 10));
    }

    #[Test]
    public function cutTruncatesWithEllipsis(): void
    {
        $result = ItemValidator::cut('Hello, World!', 10);

        self::assertSame('Hello, Wo…', $result);
        self::assertSame(10, mb_strlen($result));
    }

    #[Test]
    public function cutExactLengthIsNotTruncated(): void
    {
        self::assertSame('12345', ItemValidator::cut('12345', 5));
    }
}
