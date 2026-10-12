<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Tmonier\SyliusAgenticFeedsPlugin\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame(['adapter' => 'local', 'directory' => '%kernel.project_dir%/var/agentic_feeds', 'flysystem_service' => null], $config['storage']);
        self::assertSame(['gtin' => 'gtin', 'mpn' => 'mpn', 'brand' => 'brand'], $config['attributes']);
        self::assertSame('new', $config['condition']);
        self::assertSame('utm_medium=feed&utm_source={format}', $config['link_parameters']);
        self::assertSame(['enabled' => true], $config['json_ld']);
        self::assertSame(['enabled' => false, 'frequency' => '1 hour'], $config['schedule']);
        self::assertSame(['enabled' => true], $config['webhook_events']);
        self::assertSame([], $config['seller_policies']);
    }

    public function testSellerPoliciesAndValidation(): void
    {
        $config = $this->process([['seller_policies' => ['WEB' => ['return_policy' => 'https://x.test/returns', 'return_days' => 14]], 'json_ld' => false]]);

        self::assertIsArray($config['seller_policies']);
        self::assertSame(['return_policy' => 'https://x.test/returns', 'return_days' => 14, 'privacy_policy' => null, 'terms' => null], $config['seller_policies']['WEB'] ?? null);
        self::assertSame(['enabled' => false], $config['json_ld']);

        $this->expectException(InvalidConfigurationException::class);
        $this->process([['condition' => 'broken']]);
    }

    /**
     * @param list<array<string, mixed>> $configs
     *
     * @return array<string, mixed>
     */
    private function process(array $configs): array
    {
        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration(new Configuration(), $configs);

        return $processed;
    }
}
