<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Repository;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\VariantIdentifiersInterface;

/** @extends RepositoryInterface<VariantIdentifiersInterface> */
interface VariantIdentifiersRepositoryInterface extends RepositoryInterface
{
    public function findOneByVariant(ProductVariantInterface $variant): ?VariantIdentifiersInterface;

    /**
     * @param list<int> $variantIds
     *
     * @return array<int, VariantIdentifiersInterface> by variant id
     */
    public function findByVariantIds(array $variantIds): array;
}
