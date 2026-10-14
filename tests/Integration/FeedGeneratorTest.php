<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Integration;

use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Model\ExchangeRateInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Tmonier\SyliusAgenticFeedsPlugin\AgenticFeedsTestTrait;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\FeedIssueInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleXmlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\OpenAiJsonlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\StripeCsvFormat;

final class FeedGeneratorTest extends KernelTestCase
{
    use AgenticFeedsTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testTheGoogleFeedOfAChannel(): void
    {
        $parent = $this->catalog->taxon('Clothing', $this->catalog->taxon('Category'));
        $shirts = $this->catalog->product('SHIRT', [2500, 2700], $this->catalog->taxon('Shirts', $parent));
        $this->catalog->attribute($shirts, 'brand', 'Acme');
        // A product-level GTIN is not repeated on the variants: the first has its own.
        $this->catalog->attribute($shirts, 'gtin', '4006381333931');
        $this->catalog->identifiers($this->catalog->variant($shirts), '96385074', 'SH-1');
        $this->catalog->image($shirts, 'ab/cd/shirt.jpg');
        $mug = $this->catalog->product('MUG', [990]);
        $this->catalog->image($mug, 'ab/cd/mug.jpg');
        $disabled = $this->catalog->product('OFF', [100]);
        $disabled->setEnabled(false);
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);
        $code = (string) $feed->getCode();
        $host = (string) $this->catalog->channel()->getHostname();

        $result = $this->generate($feed);

        self::assertSame(3, $result->items());
        $xml = new \SimpleXMLElement((string) $this->file($code, '.xml'));
        $items = $xml->channel->item;
        self::assertCount(3, $items);
        $first = $items[0];
        $g = $first->children('http://base.google.com/ns/1.0');
        self::assertSame('SHIRT_' . $this->catalog->suffix . '-1', (string) $g->id);
        self::assertSame('Product SHIRT - Variant 1', (string) $first->title);
        self::assertSame('Organic cotton.', (string) $first->description);
        self::assertSame(sprintf('https://%s/en_US/products/p-shirt-%s?utm_medium=feed&utm_source=google_xml', $host, strtolower($this->catalog->suffix)), (string) $first->link);
        self::assertSame(sprintf('https://%s/media/image/ab/cd/shirt.jpg', $host), (string) $g->image_link);
        self::assertSame('25.00 EUR', (string) $g->price);
        self::assertSame('Acme', (string) $g->brand);
        self::assertSame('96385074', (string) $g->gtin);
        self::assertSame('SH-1', (string) $g->mpn);
        $second = $items[1]->children('http://base.google.com/ns/1.0');
        self::assertCount(0, $second->gtin, 'The product GTIN is not given to every variant.');
        self::assertContains('shared_identifier', array_map(static fn (array $issue): string => $issue[0]->code, array_filter($result->issues(), fn (array $issue): bool => $issue[1] === 'SHIRT_' . $this->catalog->suffix . '-2')));
        self::assertSame('SHIRT_' . $this->catalog->suffix, (string) $g->item_group_id);
        self::assertSame('Clothing > Shirts', (string) $g->product_type);
        self::assertSame('in_stock', (string) $g->availability);

        $mugItem = $items[2]->children('http://base.google.com/ns/1.0');
        self::assertSame('Sylius Demo', (string) $mugItem->brand, 'Default brand of the configuration.');
        self::assertSame('no', (string) $mugItem->identifier_exists);
        self::assertCount(0, $mugItem->item_group_id);

        $this->entityManager()->clear();
        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        self::assertSame(ProductFeedInterface::STATUS_WARNINGS, $feed->getLastStatus());
        self::assertSame(3, $feed->getLastItemCount());
        self::assertNotNull($feed->getLastGeneratedAt());
    }

    public function testASalePriceOnlyWithACatalogPromotionAndItsReferencePrice(): void
    {
        $sale = $this->catalog->product('SALE', [3000]);
        $this->catalog->image($sale, 'a/sale.jpg');
        $this->catalog->promotion($this->catalog->variant($sale), 2400, null, new \DateTime('+10 days'));
        $manual = $this->catalog->product('MANUAL', [3000]);
        $this->catalog->image($manual, 'a/manual.jpg');
        $this->catalog->variant($manual)->getChannelPricingForChannel($this->catalog->channel())?->setOriginalPrice(5000);
        $this->entityManager()->flush();
        $channelCode = (string) $this->catalog->channel()->getCode();
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);
        $code = (string) $feed->getCode();

        $this->generate($feed);

        // Reference = the lowest price of the 30 days before the promotion (Sylius price
        // history: 30.00), never the manual "original price" without promotion.
        self::assertSame(['SALE' => ['30.00 EUR', '24.00 EUR', true], 'MANUAL' => ['30.00 EUR', '', false]], $this->prices($code));

        // Lowest prices not shown in the channel: no reduction announced, the current price only.
        $channel = $this->entityManager()->getRepository(\Sylius\Component\Core\Model\ChannelInterface::class)->findOneBy(['code' => $channelCode]);
        self::assertInstanceOf(\Sylius\Component\Core\Model\ChannelInterface::class, $channel);
        $channel->getChannelPriceHistoryConfig()?->setLowestPriceForDiscountedProductsVisible(false);
        $this->entityManager()->flush();
        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        $this->generate($feed);

        self::assertSame(['24.00 EUR', '', false], $this->prices($code)['SALE']);
    }

    /** @return array<string, array{string, string, bool}> by product code prefix */
    private function prices(string $code): array
    {
        $items = [];
        foreach ((new \SimpleXMLElement((string) $this->file($code, '.xml')))->channel->item as $item) {
            $g = $item->children('http://base.google.com/ns/1.0');
            $items[explode('_', (string) $g->id)[0]] = [(string) $g->price, (string) $g->sale_price, '' !== (string) $g->sale_price_effective_date];
        }

        return $items;
    }

    public function testPricesAreTheDisplayedPricesAndUsCanadaFeedsExcludeTheTax(): void
    {
        $net = $this->catalog->product('NET', [2500]);
        $this->catalog->image($net, 'a/net.jpg');
        // 20 % not included: the shop displays 25.00 without tax.
        $this->catalog->tax($this->catalog->variant($net), 0.2);
        $eu = $this->catalog->feed(StripeCsvFormat::CODE);
        $us = $this->catalog->feed(StripeCsvFormat::CODE, null, 'EUR', 'US');

        $result = $this->generate($eu);
        $this->generate($us);

        $row = $this->stripeRow((string) $eu->getCode());
        self::assertSame(['25.00 EUR', 'exclusive'], [$row['price'], $row['tax_behavior']], 'Never a tax the shop does not display.');
        self::assertContains('price_excludes_tax', array_map(static fn (array $issue): string => $issue[0]->code, $result->issues()));
        self::assertSame(['25.00 EUR', 'exclusive'], [$this->stripeRow((string) $us->getCode())['price'], $this->stripeRow((string) $us->getCode())['tax_behavior']]);
    }

    public function testWithoutDefaultTaxZoneThePriceIsNotClaimedToIncludeTax(): void
    {
        $net = $this->catalog->product('NOZONE', [2500]);
        $this->catalog->image($net, 'a/nozone.jpg');
        $this->catalog->tax($this->catalog->variant($net), 0.2);
        $this->catalog->channel()->setDefaultTaxZone(null);
        $this->entityManager()->flush();
        $feed = $this->catalog->feed(StripeCsvFormat::CODE);

        $result = $this->generate($feed);

        $row = $this->stripeRow((string) $feed->getCode());
        self::assertSame(['25.00 EUR', 'exclusive'], [$row['price'], $row['tax_behavior']], 'Unknown tax: never sent as tax included.');
        self::assertContains('tax_unknown', array_map(static fn (array $issue): string => $issue[0]->code, $result->issues()));
    }

    public function testIncludedTaxIsRemovedForUsFeeds(): void
    {
        $gross = $this->catalog->product('GROSS', [3000]);
        $this->catalog->image($gross, 'a/gross.jpg');
        $this->catalog->tax($this->catalog->variant($gross), 0.2, true);
        $eu = $this->catalog->feed(GoogleXmlFormat::CODE);
        $us = $this->catalog->feed(GoogleXmlFormat::CODE, null, 'EUR', 'US');

        $result = $this->generate($eu);
        $this->generate($us);

        self::assertSame(['30.00 EUR', '', false], $this->prices((string) $eu->getCode())['GROSS']);
        self::assertNotContains('price_excludes_tax', array_map(static fn (array $issue): string => $issue[0]->code, $result->issues()));
        self::assertSame(['25.00 EUR', '', false], $this->prices((string) $us->getCode())['GROSS'], 'US and Canada: prices without tax.');
    }

    public function testConversionAndMissingExchangeRate(): void
    {
        $product = $this->catalog->product('CONVERTED', [2500]);
        $this->catalog->image($product, 'a/converted.jpg');
        $usd = $this->catalog->currency('USD');
        $sek = $this->catalog->currency('SEK');
        $this->catalog->channel()->addCurrency($usd);
        $this->catalog->channel()->addCurrency($sek);
        /** @var FactoryInterface<ExchangeRateInterface> $rateFactory */
        $rateFactory = self::getContainer()->get('sylius.factory.exchange_rate');
        $rate = $rateFactory->createNew();
        $rate->setSourceCurrency($this->catalog->currency('EUR'));
        $rate->setTargetCurrency($usd);
        $rate->setRatio(1.1);
        $this->entityManager()->persist($rate);
        $this->entityManager()->flush();
        $dollars = $this->catalog->feed(OpenAiJsonlFormat::CODE, null, 'USD', 'US');
        $kronor = $this->catalog->feed(OpenAiJsonlFormat::CODE, null, 'SEK', 'US');

        $this->generate($dollars);
        $result = $this->generate($kronor);

        $line = json_decode(trim((string) $this->file((string) $dollars->getCode(), '.jsonl.gz')), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($line);
        self::assertSame('27.50 USD', $line['price']);
        // No EUR/SEK rate: no unconverted amount published as SEK.
        self::assertTrue($result->failed());
        self::assertStringContainsString('No exchange rate between EUR and SEK', (string) $result->failure());
        self::assertNull($this->file((string) $kronor->getCode(), '.jsonl.gz'));
    }

    /** @return array<string, string> the first product row of a Stripe feed */
    private function stripeRow(string $code): array
    {
        $lines = explode("\n", trim((string) $this->file($code, '.csv')));
        $header = str_getcsv($lines[0], ',', '"', '');
        $row = str_getcsv($lines[1] ?? '', ',', '"', '');

        return array_combine(array_map(strval(...), $header), array_map(strval(...), array_pad($row, \count($header), '')));
    }

    public function testStockAndExcludedVariants(): void
    {
        $product = $this->catalog->product('STOCK', [1000, 1000, 1000]);
        $this->catalog->image($product, 'a/stock.jpg');
        $soldOut = $this->catalog->variant($product, 0);
        $soldOut->setTracked(true);
        $soldOut->setOnHand(3);
        $soldOut->setOnHold(3);
        $inStock = $this->catalog->variant($product, 1);
        $inStock->setTracked(true);
        $inStock->setOnHand(5);
        $this->catalog->variant($product, 2)->setEnabled(false);
        $this->entityManager()->flush();
        $feed = $this->catalog->feed(StripeCsvFormat::CODE);

        $this->generate($feed);

        $inventory = (string) $this->file((string) $feed->getCode(), '-inventory.csv');
        self::assertStringContainsString(sprintf("%s,out_of_stock,0\n", $soldOut->getCode()), $inventory);
        self::assertStringContainsString(sprintf("%s,in_stock,5\n", $inStock->getCode()), $inventory);
        self::assertSame(3, substr_count($inventory, "\n"), 'Header and two enabled variants.');
    }

    public function testItemsWithErrorsAreLeftOutAndReported(): void
    {
        $this->catalog->product('NOIMAGE', [1000]);
        $ok = $this->catalog->product('OK', [1000]);
        $this->catalog->image($ok, 'a/ok.jpg');
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);
        $code = (string) $feed->getCode();

        $result = $this->generate($feed);

        self::assertSame(1, $result->items());
        self::assertTrue($result->hasErrors());
        $this->entityManager()->clear();
        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        self::assertSame(ProductFeedInterface::STATUS_ERRORS, $feed->getLastStatus());
        $issues = $this->entityManager()->getRepository(FeedIssueInterface::class)->findBy(['feed' => $feed, 'severity' => 'error']);
        self::assertCount(1, $issues);
        self::assertSame('missing_field', $issues[0]->getCode());
        self::assertSame('image', $issues[0]->getField());
        self::assertSame('NOIMAGE_' . $this->catalog->suffix, $issues[0]->getItemId());
        self::assertStringNotContainsString('NOIMAGE', (string) $this->file($code, '.xml'));
    }

    public function testAFailedGenerationKeepsThePreviousFile(): void
    {
        $product = $this->catalog->product('KEEP', [1000]);
        $this->catalog->image($product, 'a/keep.jpg');
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);
        $code = (string) $feed->getCode();
        $this->generate($feed);
        $before = $this->file($code, '.xml');

        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        $feed->setFormat('unknown');
        $this->entityManager()->flush();
        $result = $this->generate($feed);

        self::assertTrue($result->failed());
        self::assertSame($before, $this->file($code, '.xml'));
        $this->entityManager()->clear();
        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        self::assertSame(ProductFeedInterface::STATUS_FAILED, $feed->getLastStatus());
        self::assertSame(1, $feed->getLastItemCount(), 'Counts of the file still served.');
    }

    public function testCatalogChangesMarkTheFeedsStaleAndTheCommandRegeneratesThem(): void
    {
        $product = $this->catalog->product('STALE', [1000]);
        $this->catalog->image($product, 'a/stale.jpg');
        $feed = $this->catalog->feed(OpenAiJsonlFormat::CODE);
        $code = (string) $feed->getCode();
        $this->generate($feed);

        $variant = $this->entityManager()->getRepository(ProductVariantInterface::class)->findOneBy(['code' => 'STALE_' . $this->catalog->suffix]);
        self::assertInstanceOf(ProductVariantInterface::class, $variant);
        $pricing = $variant->getChannelPricings()->first();
        self::assertInstanceOf(ChannelPricingInterface::class, $pricing);
        $pricing->setPrice(1200);
        $this->entityManager()->flush();
        $this->entityManager()->clear();
        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        self::assertNotNull($feed->getStaleSince());
        // The change happened a minute ago (a change in the second a generation starts keeps the feed stale).
        $feed->setStaleSince(new \DateTime('-1 minute'));
        $this->entityManager()->flush();

        $tester = $this->command();
        self::assertSame(0, $tester->execute(['codes' => [$code], '--stale' => true]));
        self::assertStringContainsString($code, $tester->getDisplay());
        self::assertStringContainsString('"12.00 EUR"', (string) $this->file($code, '.jsonl.gz'));

        $tester->execute(['codes' => [$code], '--stale' => true]);
        self::assertStringContainsString('No feed to generate', $tester->getDisplay());
    }

    public function testTheCommandForCi(): void
    {
        $this->catalog->product('NOIMAGE', [1000]);
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);
        $code = (string) $feed->getCode();
        $tester = $this->command();

        self::assertSame(0, $tester->execute(['codes' => [$code], '--dry-run' => true]));
        self::assertNull($this->file($code, '.xml'), 'A dry run writes nothing.');
        self::assertSame(1, $tester->execute(['codes' => [$code], '--dry-run' => true, '--fail-on-errors' => true]));
        self::assertSame(1, $tester->execute(['codes' => ['nope']]));
    }

    private function command(): CommandTester
    {
        $kernel = self::getContainer()->get('kernel');
        self::assertInstanceOf(KernelInterface::class, $kernel);

        return new CommandTester((new Application($kernel))->find('tmonier:feeds:generate'));
    }
}
