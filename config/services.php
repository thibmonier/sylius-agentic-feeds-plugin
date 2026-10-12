<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Tmonier\SyliusAgenticFeedsPlugin\Command\GenerateFeedsCommand;
use Tmonier\SyliusAgenticFeedsPlugin\Command\ImportIdentifiersCommand;
use Tmonier\SyliusAgenticFeedsPlugin\Form\Extension\ProductVariantTypeExtension;
use Tmonier\SyliusAgenticFeedsPlugin\Form\VariantIdentifiersManager;
use Tmonier\SyliusAgenticFeedsPlugin\Controller\Admin\GenerateFeedController;
use Tmonier\SyliusAgenticFeedsPlugin\Controller\FeedDownloadController;
use Tmonier\SyliusAgenticFeedsPlugin\EventListener\CatalogChangeListener;
use Tmonier\SyliusAgenticFeedsPlugin\EventListener\JsonLdDeduplicationListener;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedGenerator;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\ItemBuilder;
use Tmonier\SyliusAgenticFeedsPlugin\Fixture\ProductFeedFixture;
use Tmonier\SyliusAgenticFeedsPlugin\Form\Type\ProductFeedType;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleTsvFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleXmlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\OpenAiJsonlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\StripeCsvFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Menu\AdminMenuListener;
use Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateFeed;
use Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateStaleFeeds;
use Tmonier\SyliusAgenticFeedsPlugin\MessageHandler\FeedMessageHandler;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\LocalFeedStorage;
use Tmonier\SyliusAgenticFeedsPlugin\Twig\AgenticFeedsExtension;
use Tmonier\SyliusAgenticFeedsPlugin\Twig\JsonLdBuilder;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\ValidProductFeedValidator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Formats
    $services->set('tmonier_agentic_feeds.format.google_xml', GoogleXmlFormat::class)->tag('tmonier_agentic_feeds.format');
    $services->set('tmonier_agentic_feeds.format.google_tsv', GoogleTsvFormat::class)->tag('tmonier_agentic_feeds.format');
    $services->set('tmonier_agentic_feeds.format.openai_jsonl', OpenAiJsonlFormat::class)
        ->args([param('tmonier_agentic_feeds.seller_policies')])
        ->tag('tmonier_agentic_feeds.format');
    $services->set('tmonier_agentic_feeds.format.stripe_csv', StripeCsvFormat::class)->tag('tmonier_agentic_feeds.format');
    $services->set('tmonier_agentic_feeds.format_registry', FormatRegistry::class)
        ->args([tagged_iterator('tmonier_agentic_feeds.format')]);

    // Storage
    $services->set('tmonier_agentic_feeds.storage.local', LocalFeedStorage::class)
        ->args([param('tmonier_agentic_feeds.storage.directory')]);
    $services->alias('tmonier_agentic_feeds.storage', 'tmonier_agentic_feeds.storage.local');

    // Items and generation
    $services->set('tmonier_agentic_feeds.item_builder', ItemBuilder::class)
        ->args([
            service('router'),
            service('sylius.converter.currency'),
            service('sylius.resolver.tax_rate'),
            service('sylius.tax_calculator'),
            service('sylius.calculator.product_variant_price'),
            service('tmonier_agentic_feeds.repository.variant_identifiers'),
            param('tmonier_agentic_feeds.attributes'),
            param('tmonier_agentic_feeds.default_brand'),
            param('tmonier_agentic_feeds.condition'),
            param('tmonier_agentic_feeds.link_parameters'),
        ]);

    $services->set('tmonier_agentic_feeds.generator', FeedGenerator::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('tmonier_agentic_feeds.item_builder'),
            service('tmonier_agentic_feeds.format_registry'),
            service('tmonier_agentic_feeds.storage'),
            service('tmonier_agentic_feeds.repository.feed_issue'),
            service('tmonier_agentic_feeds.factory.feed_issue'),
            service('logger'),
            service('sylius.repository.exchange_rate'),
            param('tmonier_agentic_feeds.batch_size'),
        ])
        ->tag('monolog.logger', ['channel' => 'tmonier_agentic_feeds']);

    $services->set('tmonier_agentic_feeds.command.generate', GenerateFeedsCommand::class)
        ->args([service('tmonier_agentic_feeds.repository.product_feed'), service('tmonier_agentic_feeds.generator')])
        ->tag('console.command');

    $services->set('tmonier_agentic_feeds.message_handler', FeedMessageHandler::class)
        ->args([service('tmonier_agentic_feeds.repository.product_feed'), service('tmonier_agentic_feeds.generator')])
        ->tag('messenger.message_handler', ['handles' => GenerateFeed::class, 'method' => 'generate'])
        ->tag('messenger.message_handler', ['handles' => GenerateStaleFeeds::class, 'method' => 'generateStale']);

    $services->set('tmonier_agentic_feeds.listener.catalog_change', CatalogChangeListener::class)
        ->args([service('tmonier_agentic_feeds.repository.product_feed')])
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
        ->tag('doctrine.event_listener', ['event' => 'postFlush']);

    // HTTP
    $services->set('tmonier_agentic_feeds.controller.download', FeedDownloadController::class)
        ->args([service('tmonier_agentic_feeds.repository.product_feed'), service('tmonier_agentic_feeds.format_registry'), service('tmonier_agentic_feeds.storage')])
        ->public();

    $services->set('tmonier_agentic_feeds.controller.admin_generate', GenerateFeedController::class)
        ->args([service('tmonier_agentic_feeds.repository.product_feed'), service('sylius.command_bus'), service('security.csrf.token_manager'), service('router')])
        ->public();

    // Admin
    $services->set('tmonier_agentic_feeds.identifiers_manager', VariantIdentifiersManager::class)
        ->args([service('tmonier_agentic_feeds.repository.variant_identifiers'), service('tmonier_agentic_feeds.factory.variant_identifiers'), service('doctrine.orm.entity_manager')])
        ->tag('kernel.event_listener', ['event' => 'sylius.product_variant.pre_create', 'method' => 'onPreSave'])
        ->tag('kernel.event_listener', ['event' => 'sylius.product_variant.pre_update', 'method' => 'onPreSave'])
        ->tag('kernel.event_listener', ['event' => 'sylius.product.pre_create', 'method' => 'onPreSave'])
        ->tag('kernel.event_listener', ['event' => 'sylius.product.pre_update', 'method' => 'onPreSave'])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('tmonier_agentic_feeds.form.extension.product_variant', ProductVariantTypeExtension::class)
        ->args([service('tmonier_agentic_feeds.identifiers_manager')])
        ->tag('form.type_extension');

    $services->set('tmonier_agentic_feeds.command.import_identifiers', ImportIdentifiersCommand::class)
        ->args([service('sylius.repository.product_variant'), service('tmonier_agentic_feeds.identifiers_manager'), service('doctrine.orm.entity_manager')])
        ->tag('console.command');

    $services->set('tmonier_agentic_feeds.form.product_feed', ProductFeedType::class)
        ->args([
            param('tmonier_agentic_feeds.model.product_feed.class'),
            ['sylius'],
            service('tmonier_agentic_feeds.format_registry'),
            service('sylius.repository.locale'),
            service('sylius.repository.currency'),
        ])
        ->tag('form.type');

    $services->set('tmonier_agentic_feeds.validator.product_feed', ValidProductFeedValidator::class)
        ->args([service('tmonier_agentic_feeds.format_registry'), service('sylius.repository.exchange_rate')])
        ->tag('validator.constraint_validator', ['alias' => 'tmonier_agentic_feeds_valid_product_feed']);

    $services->set('tmonier_agentic_feeds.menu.admin', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main']);

    // Shop: JSON-LD
    $services->set('tmonier_agentic_feeds.json_ld_builder', JsonLdBuilder::class)
        ->args([
            service('tmonier_agentic_feeds.item_builder'),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
            service('sylius.context.currency'),
        ]);

    $services->set('tmonier_agentic_feeds.twig.extension', AgenticFeedsExtension::class)
        ->args([
            service('tmonier_agentic_feeds.json_ld_builder'),
            param('tmonier_agentic_feeds.json_ld'),
            service('tmonier_agentic_feeds.format_registry'),
            service('tmonier_agentic_feeds.storage'),
            service('router'),
        ])
        ->tag('twig.extension');

    $services->set('tmonier_agentic_feeds.listener.json_ld_deduplication', JsonLdDeduplicationListener::class)
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'priority' => -10]);

    // Demo data
    $services->set('tmonier_agentic_feeds.fixture.product_feed', ProductFeedFixture::class)
        ->args([
            service('doctrine.orm.entity_manager'),
            service('tmonier_agentic_feeds.factory.product_feed'),
            service('tmonier_agentic_feeds.repository.product_feed'),
            service('sylius.repository.channel'),
        ])
        ->tag('sylius_fixtures.fixture');

};
