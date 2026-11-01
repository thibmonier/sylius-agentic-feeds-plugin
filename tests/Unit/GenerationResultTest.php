<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\GenerationResult;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;

final class GenerationResultTest extends TestCase
{
    #[Test]
    public function freshResultIsOk(): void
    {
        $result = new GenerationResult('google_xml');

        self::assertSame('google_xml', $result->feedCode);
        self::assertSame(0, $result->items());
        self::assertSame(0, $result->errorCount());
        self::assertSame(0, $result->warningCount());
        self::assertFalse($result->failed());
        self::assertFalse($result->hasErrors());
        self::assertNull($result->failure());
        self::assertSame(ProductFeedInterface::STATUS_OK, $result->status());
        self::assertSame([], $result->issues());
        self::assertSame(0, $result->durationMs());
    }

    #[Test]
    public function itemWrittenIncrementsCount(): void
    {
        $result = new GenerationResult('feed');
        $result->itemWritten();
        $result->itemWritten();
        $result->itemWritten();

        self::assertSame(3, $result->items());
    }

    #[Test]
    public function addErrorIncrementsErrorCount(): void
    {
        $result = new GenerationResult('feed');
        $issue = Issue::error(Issue::MISSING_PRICE, 'price');
        $result->add($issue, 'ITEM-1', 'PROD-1');

        self::assertSame(1, $result->errorCount());
        self::assertSame(0, $result->warningCount());
        self::assertTrue($result->hasErrors());
        self::assertSame(ProductFeedInterface::STATUS_ERRORS, $result->status());
        self::assertCount(1, $result->issues());
        self::assertSame($issue, $result->issues()[0][0]);
        self::assertSame('ITEM-1', $result->issues()[0][1]);
        self::assertSame('PROD-1', $result->issues()[0][2]);
    }

    #[Test]
    public function addWarningIncrementsWarningCount(): void
    {
        $result = new GenerationResult('feed');
        $result->add(Issue::warning(Issue::TOO_LONG, 'title'), null, null);

        self::assertSame(0, $result->errorCount());
        self::assertSame(1, $result->warningCount());
        self::assertFalse($result->hasErrors());
        self::assertSame(ProductFeedInterface::STATUS_WARNINGS, $result->status());
    }

    #[Test]
    public function failSetsFailureAndStatus(): void
    {
        $result = new GenerationResult('feed');
        $result->fail('Out of memory');

        self::assertTrue($result->failed());
        self::assertTrue($result->hasErrors());
        self::assertSame('Out of memory', $result->failure());
        self::assertSame(ProductFeedInterface::STATUS_FAILED, $result->status());
    }

    #[Test]
    public function failedStatusTakesPriorityOverErrors(): void
    {
        $result = new GenerationResult('feed');
        $result->add(Issue::error(Issue::MISSING_PRICE, 'price'), 'A', 'P');
        $result->add(Issue::warning(Issue::TOO_LONG, 'title'), 'B', 'Q');
        $result->fail('Crash');

        self::assertSame(ProductFeedInterface::STATUS_FAILED, $result->status());
    }

    #[Test]
    public function errorsStatusTakesPriorityOverWarnings(): void
    {
        $result = new GenerationResult('feed');
        $result->add(Issue::warning(Issue::TOO_LONG, 'title'), null, null);
        $result->add(Issue::error(Issue::MISSING_PRICE, 'price'), null, null);

        self::assertSame(ProductFeedInterface::STATUS_ERRORS, $result->status());
    }

    #[Test]
    public function finishSetsDuration(): void
    {
        $result = new GenerationResult('feed');
        $result->finish(1234);

        self::assertSame(1234, $result->durationMs());
    }
}
