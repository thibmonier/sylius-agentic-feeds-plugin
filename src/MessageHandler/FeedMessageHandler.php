<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\MessageHandler;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedGenerator;
use Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateFeed;
use Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateStaleFeeds;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\ProductFeedRepositoryInterface;

final class FeedMessageHandler
{
    public function __construct(
        private readonly ProductFeedRepositoryInterface $feedRepository,
        private readonly FeedGenerator $generator,
    ) {
    }

    public function generate(GenerateFeed $message): void
    {
        $feed = $this->feedRepository->findOneByCode($message->feedCode);
        if (null !== $feed && $feed->isEnabled()) {
            $this->generator->generate($feed);
        }
    }

    public function generateStale(GenerateStaleFeeds $message): void
    {
        foreach ($this->feedRepository->findEnabled() as $feed) {
            if (null !== $feed->getStaleSince() || null === $feed->getLastGeneratedAt()) {
                $this->generator->generate($feed);
            }
        }
    }
}
