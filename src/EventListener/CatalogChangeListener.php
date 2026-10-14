<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTranslationInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\ProductFeedRepositoryInterface;

/**
 * Incremental regeneration: a flush that changes catalog data used by the feeds (products,
 * variants and stock, prices, translations, images, attributes) marks the enabled feeds stale;
 * "tmonier:feeds:generate --stale" (or the schedule) regenerates only those.
 */
final class CatalogChangeListener
{
    private const CLASSES = [
        ProductInterface::class,
        ProductVariantInterface::class,
        ChannelPricingInterface::class,
        ProductTranslationInterface::class,
        ProductImageInterface::class,
        ProductAttributeValueInterface::class,
    ];

    private bool $changed = false;

    public function __construct(private readonly ProductFeedRepositoryInterface $feedRepository)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        foreach ([$unitOfWork->getScheduledEntityInsertions(), $unitOfWork->getScheduledEntityUpdates(), $unitOfWork->getScheduledEntityDeletions()] as $entities) {
            foreach ($entities as $entity) {
                foreach (self::CLASSES as $class) {
                    if ($entity instanceof $class) {
                        $this->changed = true;

                        return;
                    }
                }
            }
        }
    }

    public function postFlush(): void
    {
        if (!$this->changed) {
            return;
        }
        $this->changed = false;
        $this->feedRepository->markStale(new \DateTime());
    }
}
