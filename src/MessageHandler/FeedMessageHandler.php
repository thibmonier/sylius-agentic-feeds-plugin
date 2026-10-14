<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\MessageHandler;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedGenerator;
use Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateFeed;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\ProductFeedRepositoryInterface;

final readonly class FeedMessageHandler
{
    public function __construct(
        private ProductFeedRepositoryInterface $feedRepository,
        private FeedGenerator $generator,
    ) {
    }

    public function generate(GenerateFeed $message): void
    {
        $feed = $this->feedRepository->findOneByCode($message->feedCode);
        if ($feed instanceof \Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface && $feed->isEnabled()) {
            $this->generator->generate($feed);
        }
    }

    public function generateStale(): void
    {
        foreach ($this->feedRepository->findEnabled() as $feed) {
            if (null !== $feed->getStaleSince() || null === $feed->getLastGeneratedAt()) {
                $this->generator->generate($feed);
            }
        }
    }
}
