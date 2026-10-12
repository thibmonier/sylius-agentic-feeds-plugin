<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssueInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;

/** @extends RepositoryInterface<FeedIssueInterface> */
interface FeedIssueRepositoryInterface extends RepositoryInterface
{
    public function deleteByFeed(ProductFeedInterface $feed): void;

    /** Admin grid of the issues of one feed. */
    public function createByFeedQueryBuilder(string $feedId): QueryBuilder;
}
