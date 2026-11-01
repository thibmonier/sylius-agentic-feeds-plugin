<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssue;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssueInterface;

final class FeedIssueTest extends TestCase
{
    #[Test]
    public function defaultState(): void
    {
        $issue = new FeedIssue();

        self::assertNull($issue->getId());
        self::assertNull($issue->getFeed());
        self::assertSame(FeedIssueInterface::SEVERITY_ERROR, $issue->getSeverity());
        self::assertSame('', $issue->getCode());
        self::assertNull($issue->getField());
        self::assertNull($issue->getItemId());
        self::assertNull($issue->getProductCode());
        self::assertSame([], $issue->getParameters());
        self::assertNotNull($issue->getCreatedAt());
    }

    #[Test]
    public function settersAndGetters(): void
    {
        $issue = new FeedIssue();

        $issue->setSeverity('warning');
        $issue->setCode('too_long');
        $issue->setField('title');
        $issue->setItemId('ITEM-1');
        $issue->setProductCode('PROD-1');
        $issue->setParameters(['%max%' => 150]);

        self::assertSame('warning', $issue->getSeverity());
        self::assertSame('too_long', $issue->getCode());
        self::assertSame('title', $issue->getField());
        self::assertSame('ITEM-1', $issue->getItemId());
        self::assertSame('PROD-1', $issue->getProductCode());
        self::assertSame(['%max%' => 150], $issue->getParameters());
    }
}
