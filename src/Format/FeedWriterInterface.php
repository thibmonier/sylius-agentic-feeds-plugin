<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Format;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;

interface FeedWriterInterface
{
    public function write(FeedItem $item): void;

    /** Writes the end of the files (closing tags, compression trailer); streams stay open. */
    public function finish(): void;
}
