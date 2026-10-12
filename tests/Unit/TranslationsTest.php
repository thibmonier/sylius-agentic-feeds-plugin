<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssueInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleTsvFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleXmlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\OpenAiJsonlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\StripeCsvFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;

final class TranslationsTest extends TestCase
{
    private const LOCALES = ['en', 'fr', 'de', 'es', 'it', 'nl', 'pl'];

    private const DOMAINS = ['messages', 'validators', 'flashes'];

    /** @return iterable<string, array{string, string}> */
    public static function catalogues(): iterable
    {
        foreach (self::DOMAINS as $domain) {
            foreach (self::LOCALES as $locale) {
                yield sprintf('%s.%s', $domain, $locale) => [$domain, $locale];
            }
        }
    }

    #[DataProvider('catalogues')]
    public function testEveryLocaleDefinesTheSameKeys(string $domain, string $locale): void
    {
        $reference = $this->flatten($this->parse($domain, 'en'));
        $catalogue = $this->flatten($this->parse($domain, $locale));

        self::assertSame([], array_values(array_diff(array_keys($reference), array_keys($catalogue))), 'Missing keys');
        self::assertSame([], array_values(array_diff(array_keys($catalogue), array_keys($reference))), 'Unknown keys');
        foreach ($catalogue as $key => $value) {
            self::assertNotSame('', trim($value), sprintf('Empty translation "%s"', $key));
            preg_match_all('/%\w+%/', $reference[$key], $expected);
            preg_match_all('/%\w+%/', $value, $actual);
            self::assertEqualsCanonicalizing($expected[0], $actual[0], sprintf('Placeholders of "%s"', $key));
        }
    }

    public function testEveryEnumValueHasALabel(): void
    {
        $messages = $this->flatten($this->parse('messages', 'en'));
        foreach ([new GoogleXmlFormat(), new GoogleTsvFormat(), new OpenAiJsonlFormat(), new StripeCsvFormat()] as $format) {
            self::assertArrayHasKey($format->getLabel(), $messages);
            foreach (array_keys($format->getFiles()) as $role) {
                self::assertArrayHasKey('tmonier_agentic_feeds.file.' . $role, $messages);
            }
        }
        foreach ((new \ReflectionClass(ProductFeedInterface::class))->getConstants() as $name => $status) {
            if (str_starts_with($name, 'STATUS_')) {
                self::assertIsString($status);
                self::assertArrayHasKey('tmonier_agentic_feeds.status.' . $status, $messages);
            }
        }
        foreach ([FeedIssueInterface::SEVERITY_ERROR, FeedIssueInterface::SEVERITY_WARNING] as $severity) {
            self::assertArrayHasKey('tmonier_agentic_feeds.severity.' . $severity, $messages);
        }
        foreach ((new \ReflectionClass(Issue::class))->getConstants() as $name => $code) {
            if (!\in_array($name, ['ERROR', 'WARNING'], true)) {
                self::assertIsString($code);
                self::assertArrayHasKey('tmonier_agentic_feeds.issue.' . $code, $messages);
            }
        }
    }

    public function testEveryKeyUsedInTheCodeExists(): void
    {
        $known = array_keys($this->flatten($this->parse('messages', 'en')) + $this->flatten($this->parse('validators', 'en')) + $this->flatten($this->parse('flashes', 'en')));
        $root = dirname(__DIR__, 2);
        $used = [];
        foreach (['src', 'templates', 'config'] as $directory) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                // Service ids (tmonier_agentic_feeds.format.google_xml...) are not labels.
                if (!$file instanceof \SplFileInfo || !in_array($file->getExtension(), ['php', 'twig', 'yaml', 'xml'], true) || str_starts_with($file->getFilename(), 'services')) {
                    continue;
                }
                preg_match_all('/tmonier_agentic_feeds\.(?:ui|form|format|status|file|severity|field|issue|webhook|flash|product_feed|variant)(?:\.[a-z_]*[a-z_])?/', (string) file_get_contents($file->getPathname()), $matches);
                foreach ($matches[0] as $key) {
                    $used[$key] = true;
                }
            }
        }

        // Prefixes completed at runtime ('tmonier_agentic_feeds.status.' ~ data.lastStatus) are skipped.
        $missing = array_filter(
            array_diff(array_keys($used), $known),
            static fn (string $key): bool => [] === array_filter($known, static fn (string $knownKey): bool => str_starts_with($knownKey, str_ends_with($key, '_') ? $key : $key . '.')),
        );

        self::assertGreaterThan(30, count($used));
        self::assertSame([], array_values($missing));
    }

    /** @return array<mixed> */
    private function parse(string $domain, string $locale): array
    {
        $data = Yaml::parseFile(sprintf('%s/../../translations/%s.%s.yaml', __DIR__, $domain, $locale));
        self::assertIsArray($data);

        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $flat += $this->flatten($value, $prefix . $key . '.');

                continue;
            }

            $flat[$prefix . $key] = is_scalar($value) ? (string) $value : '';
        }

        return $flat;
    }
}
