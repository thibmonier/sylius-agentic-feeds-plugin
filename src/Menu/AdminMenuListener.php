<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $marketing = $event->getMenu()->getChild('marketing') ?? $event->getMenu()->getChild('catalog');
        if (!$marketing instanceof \Knp\Menu\ItemInterface) {
            return;
        }
        $marketing
            ->addChild('tmonier_agentic_feeds', ['route' => 'tmonier_agentic_feeds_admin_product_feed_index'])
            ->setLabel('tmonier_agentic_feeds.ui.product_feeds')
            ->setLabelAttribute('icon', 'tabler:rss')
        ;
    }
}
