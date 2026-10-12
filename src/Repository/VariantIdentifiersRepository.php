<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\VariantIdentifiersInterface;

class VariantIdentifiersRepository extends EntityRepository implements VariantIdentifiersRepositoryInterface
{
    public function findOneByVariant(ProductVariantInterface $variant): ?VariantIdentifiersInterface
    {
        if (null === $variant->getId()) {
            return null;
        }
        $identifiers = $this->findOneBy(['variant' => $variant]);

        return $identifiers instanceof VariantIdentifiersInterface ? $identifiers : null;
    }

    public function findByVariantIds(array $variantIds): array
    {
        if ([] === $variantIds) {
            return [];
        }
        /** @var list<VariantIdentifiersInterface> $rows */
        $rows = $this->createQueryBuilder('o')
            ->andWhere('IDENTITY(o.variant) IN (:ids)')
            ->setParameter('ids', $variantIds)
            ->getQuery()
            ->getResult()
        ;
        $byVariant = [];
        foreach ($rows as $row) {
            $id = $row->getVariant()?->getId();
            if (\is_int($id)) {
                $byVariant[$id] = $row;
            }
        }

        return $byVariant;
    }
}
