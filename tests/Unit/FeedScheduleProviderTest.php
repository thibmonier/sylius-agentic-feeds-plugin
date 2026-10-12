<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Message\FeedScheduleProvider;
use Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateStaleFeeds;

final class FeedScheduleProviderTest extends TestCase
{
    public function testTheStaleFeedsAreRegeneratedEveryPeriod(): void
    {
        $messages = (new FeedScheduleProvider('15 minutes'))->getSchedule()->getRecurringMessages();

        self::assertCount(1, $messages);
        $message = reset($messages);
        self::assertNotFalse($message);
        self::assertStringContainsString('15 minutes', (string) $message->getTrigger());
        self::assertInstanceOf(GenerateStaleFeeds::class, iterator_to_array($message->getMessages(new \Symfony\Component\Scheduler\Generator\MessageContext('tmonier_agentic_feeds', $message->getId(), $message->getTrigger(), new \DateTimeImmutable())), false)[0] ?? null);
    }
}
