<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Repository;

use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;

class ProductFeedRepository extends EntityRepository implements ProductFeedRepositoryInterface
{
    public function findOneByCode(string $code): ?ProductFeedInterface
    {
        $feed = $this->findOneBy(['code' => $code]);

        return $feed instanceof ProductFeedInterface ? $feed : null;
    }

    public function findEnabled(): array
    {
        /** @var list<ProductFeedInterface> $feeds */
        $feeds = $this->createQueryBuilder('o')
            ->andWhere('o.enabled = true')
            ->addOrderBy('o.code', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return $feeds;
    }

    public function markStale(\DateTimeInterface $since): void
    {
        $this->createQueryBuilder('o')
            ->update()
            ->set('o.staleSince', ':since')
            ->andWhere('o.enabled = true')
            ->andWhere('o.staleSince IS NULL')
            ->setParameter('since', $since)
            ->getQuery()
            ->execute()
        ;
    }
}
