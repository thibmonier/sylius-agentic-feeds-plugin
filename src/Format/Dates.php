<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Format;

final class Dates
{
    /** ISO 8601 with minutes and offset, as in the Google examples: 2016-02-24T11:07+0100. */
    public static function iso(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:iO');
    }

    public static function day(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}
