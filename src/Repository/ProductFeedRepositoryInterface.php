<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Repository;

use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;

/** @extends RepositoryInterface<ProductFeedInterface> */
interface ProductFeedRepositoryInterface extends RepositoryInterface
{
    public function findOneByCode(string $code): ?ProductFeedInterface;

    /** @return list<ProductFeedInterface> */
    public function findEnabled(): array;

    /** Marks the enabled feeds as stale (catalog data changed), keeping the first date. */
    public function markStale(\DateTimeInterface $since): void;
}
