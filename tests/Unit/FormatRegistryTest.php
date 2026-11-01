<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FeedFormatInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FeedWriterInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;

final class FormatRegistryTest extends TestCase
{
    #[Test]
    public function hasReturnsTrueForRegisteredFormat(): void
    {
        $registry = new FormatRegistry([$this->stubFormat('google_xml')]);

        self::assertTrue($registry->has('google_xml'));
    }

    #[Test]
    public function hasReturnsFalseForUnknownFormat(): void
    {
        $registry = new FormatRegistry([]);

        self::assertFalse($registry->has('unknown'));
    }

    #[Test]
    public function getReturnsRegisteredFormat(): void
    {
        $format = $this->stubFormat('google_xml');
        $registry = new FormatRegistry([$format]);

        self::assertSame($format, $registry->get('google_xml'));
    }

    #[Test]
    public function getThrowsForUnknownFormat(): void
    {
        $registry = new FormatRegistry([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown feed format "missing"');
        $registry->get('missing');
    }

    #[Test]
    public function allReturnsAllFormats(): void
    {
        $a = $this->stubFormat('google_xml');
        $b = $this->stubFormat('stripe_csv');
        $registry = new FormatRegistry([$a, $b]);

        $all = $registry->all();

        self::assertCount(2, $all);
        self::assertSame($a, $all['google_xml']);
        self::assertSame($b, $all['stripe_csv']);
    }

    #[Test]
    public function fileNameConcatenates(): void
    {
        self::assertSame('google-fr.xml', FormatRegistry::fileName('google-fr', '.xml'));
        self::assertSame('stripe-inventory.csv', FormatRegistry::fileName('stripe', '-inventory.csv'));
    }

    private function stubFormat(string $code): FeedFormatInterface
    {
        return new class($code) implements FeedFormatInterface {
            public function __construct(private readonly string $code)
            {
            }

            public function getCode(): string
            {
                return $this->code;
            }

            public function getLabel(): string
            {
                return $this->code;
            }

            public function getFiles(): array
            {
                return ['main' => '.xml'];
            }

            public function getContentType(string $role): string
            {
                return 'application/xml';
            }

            public function validate(FeedItem $item): array
            {
                return [];
            }

            public function validateFeed(FeedContext $context): array
            {
                return [];
            }

            public function open(FeedContext $context, array $streams): FeedWriterInterface
            {
                throw new \RuntimeException('Not implemented in stub.');
            }
        };
    }
}
