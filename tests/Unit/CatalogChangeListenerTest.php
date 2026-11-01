<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductImage;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Tmonier\SyliusAgenticFeedsPlugin\EventListener\CatalogChangeListener;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\ProductFeedRepositoryInterface;

final class CatalogChangeListenerTest extends TestCase
{
    #[Test]
    public function postFlushDoesNothingWhenNoChangeDetected(): void
    {
        $repo = $this->createMock(ProductFeedRepositoryInterface::class);
        $repo->expects(self::never())->method('markStale');

        $listener = new CatalogChangeListener($repo);
        $listener->postFlush();
    }

    #[Test]
    public function detectsProductInsertionAndMarksStale(): void
    {
        $repo = $this->createMock(ProductFeedRepositoryInterface::class);
        $repo->expects(self::once())->method('markStale');

        $listener = new CatalogChangeListener($repo);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([new Product()]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        $args = new OnFlushEventArgs($em);
        $listener->onFlush($args);
        $listener->postFlush();
    }

    #[Test]
    public function detectsVariantUpdate(): void
    {
        $repo = $this->createMock(ProductFeedRepositoryInterface::class);
        $repo->expects(self::once())->method('markStale');

        $listener = new CatalogChangeListener($repo);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([new ProductVariant()]);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        $listener->onFlush(new OnFlushEventArgs($em));
        $listener->postFlush();
    }

    #[Test]
    public function detectsPricingDeletion(): void
    {
        $repo = $this->createMock(ProductFeedRepositoryInterface::class);
        $repo->expects(self::once())->method('markStale');

        $listener = new CatalogChangeListener($repo);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([new ChannelPricing()]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        $listener->onFlush(new OnFlushEventArgs($em));
        $listener->postFlush();
    }

    #[Test]
    public function detectsTranslationChange(): void
    {
        $repo = $this->createMock(ProductFeedRepositoryInterface::class);
        $repo->expects(self::once())->method('markStale');
        $listener = new CatalogChangeListener($repo);

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([new ProductTranslation()]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        $listener->onFlush(new OnFlushEventArgs($em));
        $listener->postFlush();
    }

    #[Test]
    public function detectsImageChange(): void
    {
        $repo = $this->createMock(ProductFeedRepositoryInterface::class);
        $repo->expects(self::once())->method('markStale');
        $listener = new CatalogChangeListener($repo);

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([]);
        $uow->method('getScheduledEntityUpdates')->willReturn([new ProductImage()]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        $listener->onFlush(new OnFlushEventArgs($em));
        $listener->postFlush();
    }

    #[Test]
    public function detectsAttributeValueChange(): void
    {
        $repo = $this->createMock(ProductFeedRepositoryInterface::class);
        $repo->expects(self::once())->method('markStale');
        $listener = new CatalogChangeListener($repo);

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([new ProductAttributeValue()]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        $listener->onFlush(new OnFlushEventArgs($em));
        $listener->postFlush();
    }

    #[Test]
    public function ignoresUnrelatedEntities(): void
    {
        $repo = $this->createMock(ProductFeedRepositoryInterface::class);
        $repo->expects(self::never())->method('markStale');
        $listener = new CatalogChangeListener($repo);

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([new \stdClass()]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        $listener->onFlush(new OnFlushEventArgs($em));
        $listener->postFlush();
    }

    #[Test]
    public function resetsAfterPostFlush(): void
    {
        $repo = $this->createMock(ProductFeedRepositoryInterface::class);
        $repo->expects(self::once())->method('markStale');
        $listener = new CatalogChangeListener($repo);

        $uow = $this->createMock(UnitOfWork::class);
        $uow->method('getScheduledEntityInsertions')->willReturn([new Product()]);
        $uow->method('getScheduledEntityUpdates')->willReturn([]);
        $uow->method('getScheduledEntityDeletions')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($uow);

        $listener->onFlush(new OnFlushEventArgs($em));
        $listener->postFlush();
        $listener->postFlush();
    }
}
