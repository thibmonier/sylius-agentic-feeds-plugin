<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Message;

use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/** Registered only when symfony/scheduler is installed and the schedule is enabled. */
final class FeedScheduleProvider implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function __construct(private readonly string $frequency)
    {
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())->add(RecurringMessage::every($this->frequency, new GenerateStaleFeeds()));
    }
}
