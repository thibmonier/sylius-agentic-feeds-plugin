<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Scheduler\RecurringMessage;
use Tmonier\SyliusAgenticFeedsPlugin\Message\FeedScheduleProvider;
use Tmonier\SyliusAgenticFeedsPlugin\Outbox\FeedEventTypeProvider;
use Tmonier\SyliusAgenticFeedsPlugin\Outbox\PriceChangedDetector;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\FlysystemFeedStorage;
use Tmonier\SyliusWebhookOutboxPlugin\Capture\ChangeDetectorInterface;

final class TmonierSyliusAgenticFeedsExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{storage: array{adapter: string, directory: string, flysystem_service: ?string}, attributes: array{gtin: ?string, mpn: ?string, brand: ?string}, default_brand: ?string, condition: string, link_parameters: ?string, batch_size: int, json_ld: array{enabled: bool}, schedule: array{enabled: bool, frequency: string}, webhook_events: array{enabled: bool}, seller_policies: array<string, array{return_policy: ?string, privacy_policy: ?string, terms: ?string, return_days: ?int}>, driver: string, resources: array<string, mixed>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $this->registerResources('tmonier_agentic_feeds', $config['driver'], $config['resources'], $container);

        $container->setParameter('tmonier_agentic_feeds.storage.adapter', $config['storage']['adapter']);
        $container->setParameter('tmonier_agentic_feeds.storage.directory', $config['storage']['directory']);
        $container->setParameter('tmonier_agentic_feeds.storage.flysystem_service', $config['storage']['flysystem_service']);
        $container->setParameter('tmonier_agentic_feeds.attributes', $config['attributes']);
        $container->setParameter('tmonier_agentic_feeds.default_brand', $config['default_brand']);
        $container->setParameter('tmonier_agentic_feeds.condition', $config['condition']);
        $container->setParameter('tmonier_agentic_feeds.link_parameters', (string) $config['link_parameters']);
        $container->setParameter('tmonier_agentic_feeds.batch_size', $config['batch_size']);
        $container->setParameter('tmonier_agentic_feeds.json_ld', $config['json_ld']['enabled']);
        $container->setParameter('tmonier_agentic_feeds.schedule.enabled', $config['schedule']['enabled']);
        $container->setParameter('tmonier_agentic_feeds.schedule.frequency', $config['schedule']['frequency']);
        $container->setParameter('tmonier_agentic_feeds.webhook_events', $config['webhook_events']['enabled']);

        $container->setParameter('tmonier_agentic_feeds.seller_policies', $config['seller_policies']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        if ('flysystem' === $config['storage']['adapter']) {
            if (null === $config['storage']['flysystem_service']) {
                throw new \InvalidArgumentException('tmonier_sylius_agentic_feeds.storage.flysystem_service is required with the flysystem adapter.');
            }
            $container->setDefinition('tmonier_agentic_feeds.storage.flysystem', new Definition(FlysystemFeedStorage::class, [new Reference($config['storage']['flysystem_service'])]));
            $container->setAlias('tmonier_agentic_feeds.storage', 'tmonier_agentic_feeds.storage.flysystem');
        }

        // Optional: tmonier/sylius-webhook-outbox-plugin.
        if ($config['webhook_events']['enabled'] && interface_exists(ChangeDetectorInterface::class)) {
            $container->setDefinition('tmonier_agentic_feeds.outbox.event_types', (new Definition(FeedEventTypeProvider::class))->addTag('tmonier_webhook.event_type_provider'));
            $container->setDefinition('tmonier_agentic_feeds.outbox.price_changed_detector', (new Definition(PriceChangedDetector::class))->addTag('tmonier_webhook.change_detector', ['priority' => 50]));
        }

        // Optional: symfony/scheduler.
        if ($config['schedule']['enabled'] && class_exists(RecurringMessage::class)) {
            $container->setDefinition('tmonier_agentic_feeds.schedule_provider', (new Definition(FeedScheduleProvider::class, [$config['schedule']['frequency']]))->addTag('scheduler.schedule_provider', ['name' => 'tmonier_agentic_feeds']));
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Grids are processed before this extension is loaded: the model class parameters have
        // to exist at prepend time.
        $configs = array_map(
            static fn (array $config): array => isset($config['resources']) ? ['resources' => $config['resources']] : [],
            $container->getExtensionConfig($this->getAlias()),
        );
        /** @var array{resources: array<string, array{classes: array{model: string}}>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);
        foreach ($config['resources'] as $name => $resource) {
            $container->setParameter(sprintf('tmonier_agentic_feeds.model.%s.class', $name), $resource['classes']['model']);
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'TmonierSyliusAgenticFeedsPlugin' => [
                        'type' => 'xml',
                        'dir' => 'config/doctrine',
                        'prefix' => 'Tmonier\SyliusAgenticFeedsPlugin\Entity',
                        'is_bundle' => true,
                    ],
                ],
            ],
        ]);

        $this->prependDoctrineMigrations($container);
    }

    public function getAlias(): string
    {
        return 'tmonier_sylius_agentic_feeds';
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Tmonier\SyliusAgenticFeedsPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@TmonierSyliusAgenticFeedsPlugin/src/Migrations';
    }

    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
