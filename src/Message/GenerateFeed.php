<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Message;

/** Generate one feed (admin "Generate now", or your own scheduling). */
final class GenerateFeed
{
    public function __construct(public readonly string $feedCode)
    {
    }
}
