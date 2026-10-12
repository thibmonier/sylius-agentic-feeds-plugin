<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;

class FeedIssueRepository extends EntityRepository implements FeedIssueRepositoryInterface
{
    public function deleteByFeed(ProductFeedInterface $feed): void
    {
        $this->createQueryBuilder('o')
            ->delete()
            ->andWhere('o.feed = :feed')
            ->setParameter('feed', $feed)
            ->getQuery()
            ->execute()
        ;
    }

    public function createByFeedQueryBuilder(string $feedId): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('IDENTITY(o.feed) = :feedId')
            ->setParameter('feedId', $feedId)
        ;
    }
}
