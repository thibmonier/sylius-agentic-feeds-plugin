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
        $this->catalog->attribute($shirts, 'gtin', '4006381333931');
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
        self::assertSame('4006381333931', (string) $g->gtin);
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
        self::assertNull($feed->getStaleSince());
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

    public function testPricesIncludeTheTaxAndAreConverted(): void
    {
        $product = $this->catalog->product('TAXED', [2500]);
        $this->catalog->image($product, 'a/taxed.jpg');
        $this->catalog->tax($this->catalog->variant($product), 0.2);
        $usd = $this->catalog->currency('USD');
        $this->catalog->channel()->addCurrency($usd);
        /** @var FactoryInterface<ExchangeRateInterface> $rateFactory */
        $rateFactory = self::getContainer()->get('sylius.factory.exchange_rate');
        $rate = $rateFactory->createNew();
        $rate->setSourceCurrency($this->catalog->currency('EUR'));
        $rate->setTargetCurrency($usd);
        $rate->setRatio(1.1);
        $this->entityManager()->persist($rate);
        $this->entityManager()->flush();
        $eur = $this->catalog->feed(StripeCsvFormat::CODE);
        $dollars = $this->catalog->feed(OpenAiJsonlFormat::CODE, null, 'USD');

        $this->generate($eur);
        $this->generate($dollars);

        self::assertStringContainsString('"30.00 EUR"', (string) $this->file((string) $eur->getCode(), '-pricing.csv'));
        $line = json_decode(trim((string) $this->file((string) $dollars->getCode(), '.jsonl.gz')), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($line);
        self::assertSame('33.00 USD', $line['price']);
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
