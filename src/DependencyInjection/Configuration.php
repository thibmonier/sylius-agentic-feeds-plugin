<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\DependencyInjection;

use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssue;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssueInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeed;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\FeedIssueRepository;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\ProductFeedRepository;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_sylius_agentic_feeds');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('storage')
                    ->info('Where generated files are written. "local": a directory; "flysystem": a League\Flysystem\FilesystemOperator service (e.g. from league/flysystem-bundle).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('adapter')->values(['local', 'flysystem'])->defaultValue('local')->end()
                        ->scalarNode('directory')->defaultValue('%kernel.project_dir%/var/agentic_feeds')->end()
                        ->scalarNode('flysystem_service')->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('attributes')
                    ->info('Codes of the product attributes holding the identifiers. Null disables one. Values of text attributes, in the feed locale or any locale.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('gtin')->defaultValue('gtin')->end()
                        ->scalarNode('mpn')->defaultValue('mpn')->end()
                        ->scalarNode('brand')->defaultValue('brand')->end()
                    ->end()
                ->end()
                ->scalarNode('default_brand')
                    ->info('Brand of the products without brand attribute (e.g. your own brand), null for none.')
                    ->defaultNull()
                ->end()
                ->enumNode('condition')
                    ->info('Condition of every item (new, refurbished, used).')
                    ->values(['new', 'refurbished', 'used'])
                    ->defaultValue('new')
                ->end()
                ->scalarNode('link_parameters')
                    ->info('Query string added to product links, e.g. "utm_source=feed&utm_medium=feed". "{format}" is replaced by the feed format. Empty for none.')
                    ->defaultValue('utm_medium=feed&utm_source={format}')
                ->end()
                ->integerNode('batch_size')
                    ->info('Products loaded per batch during generation (the entity manager is cleared between batches).')
                    ->defaultValue(100)
                    ->min(10)
                ->end()
                ->arrayNode('json_ld')
                    ->info('schema.org Product/Offer JSON-LD on product pages (left out when the page already has a Product JSON-LD).')
                    ->canBeDisabled()
                ->end()
                ->arrayNode('schedule')
                    ->info('Regenerate the feeds whose catalog data changed, through Symfony Scheduler (symfony/scheduler) when installed; otherwise run "tmonier:feeds:generate --stale" from cron.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('frequency')->defaultValue('1 hour')->end()
                    ->end()
                ->end()
                ->arrayNode('webhook_events')
                    ->info('With tmonier/sylius-webhook-outbox-plugin: record product_variant.price_changed events (stock changes are product_variant.stock_changed of that plugin).')
                    ->canBeDisabled()
                ->end()
                ->arrayNode('seller_policies')
                    ->info('Per channel code: URLs of the return policy, privacy policy and terms, return window in days (OpenAI feed fields; leave empty to omit them).')
                    ->useAttributeAsKey('channel')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('return_policy')->defaultNull()->end()
                            ->scalarNode('privacy_policy')->defaultNull()->end()
                            ->scalarNode('terms')->defaultNull()->end()
                            ->integerNode('return_days')->defaultNull()->min(0)->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->append($this->resource('product_feed', ProductFeed::class, ProductFeedInterface::class, ProductFeedRepository::class))
                        ->append($this->resource('feed_issue', FeedIssue::class, FeedIssueInterface::class, FeedIssueRepository::class))
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private function resource(string $name, string $model, string $interface, string $repository): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('classes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('model')->defaultValue($model)->cannotBeEmpty()->end()
                        ->scalarNode('interface')->defaultValue($interface)->cannotBeEmpty()->end()
                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                        ->scalarNode('repository')->defaultValue($repository)->cannotBeEmpty()->end()
                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
