<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FeedFormatInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleTsvFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleXmlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\OpenAiJsonlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\StripeCsvFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Issue;

final class FormatsTest extends TestCase
{
    public function testGoogleXml(): void
    {
        $xml = new \SimpleXMLElement($this->write(new GoogleXmlFormat(), [FeedItems::onSale(), FeedItems::item(['id' => 'MUG', 'hasVariants' => false, 'gtin' => null, 'mpn' => null, 'availability' => FeedItem::OUT_OF_STOCK])])['main']);
        $items = $xml->channel->item;
        self::assertCount(2, $items);

        $first = $items[0];
        $g = $first->children('http://base.google.com/ns/1.0');
        self::assertSame('T-shirt - Rouge, M', (string) $first->title);
        self::assertSame('Coton bio, "coupe droite"', (string) $first->description);
        self::assertSame('TSHIRT-RED-M', (string) $g->id);
        self::assertSame('25.00 EUR', (string) $g->price);
        self::assertSame('19.90 EUR', (string) $g->sale_price);
        self::assertSame('2026-10-01T00:00+0200/2026-10-31T23:59+0100', (string) $g->sale_price_effective_date);
        self::assertSame('4006381333931', (string) $g->gtin);
        self::assertSame('TSHIRT', (string) $g->item_group_id);
        self::assertSame('0.25 kg', (string) $g->shipping_weight);
        self::assertCount(1, $g->additional_image_link);
        self::assertCount(0, $g->identifier_exists);

        $g = $items[1]->children('http://base.google.com/ns/1.0');
        self::assertSame('out_of_stock', (string) $g->availability);
        self::assertSame('no', (string) $g->identifier_exists);
        self::assertCount(0, $g->item_group_id, 'No group for products without variants.');
        self::assertCount(0, $g->sale_price);
    }

    public function testGoogleTsv(): void
    {
        $lines = explode("\n", rtrim($this->write(new GoogleTsvFormat(), [FeedItems::item(['description' => "Ligne 1\tTab\nLigne 2"])])['main'], "\n"));
        $header = explode("\t", $lines[0]);
        $row = array_combine(array_map(static fn (string $name, int $index): string => $name . '#' . $index, $header, array_keys($header)), explode("\t", $lines[1]));

        self::assertSame('id', $header[0]);
        self::assertSame(10, \count(array_keys($header, 'additional_image_link', true)));
        self::assertSame('Ligne 1 Tab Ligne 2', $row['description#2']);
        self::assertSame('25.00 EUR', $row['price#6']);
    }

    public function testOpenAiJsonlIsGzippedAndUsesTheOpenAiNames(): void
    {
        $format = new OpenAiJsonlFormat(['WEB_FR' => ['return_policy' => 'https://shop.example.com/returns', 'privacy_policy' => null, 'terms' => null, 'return_days' => 30]]);
        $gzip = $this->write($format, [FeedItems::onSale(), FeedItems::item(['id' => 'MUG', 'itemGroupId' => 'MUG', 'hasVariants' => false, 'gtin' => '123'])], 'openai_jsonl')['main'];

        self::assertSame("\x1f\x8b", substr($gzip, 0, 2));
        $lines = explode("\n", trim((string) gzdecode($gzip)));
        self::assertCount(2, $lines);
        $first = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($first);
        self::assertSame('TSHIRT-RED-M', $first['item_id']);
        self::assertSame('https://shop.example.com/fr_FR/products/t-shirt', $first['url']);
        self::assertSame('https://shop.example.com/media/image/a.jpg', $first['image_url']);
        self::assertSame('25.00 EUR', $first['price']);
        self::assertSame('19.90 EUR', $first['sale_price']);
        self::assertSame('TSHIRT', $first['group_id']);
        self::assertTrue($first['listing_has_variations']);
        self::assertSame(['Couleur' => 'Rouge', 'Taille' => 'M'], $first['variant_dict']);
        self::assertTrue($first['is_eligible_search']);
        self::assertFalse($first['is_eligible_checkout']);
        self::assertSame('Boutique', $first['seller_name']);
        self::assertSame('https://shop.example.com/returns', $first['return_policy']);
        self::assertSame(30, $first['return_deadline_in_days']);
        self::assertSame(['FR'], $first['target_countries']);
        self::assertArrayNotHasKey('mpn', $first, 'No placeholders: absent values are left out.');

        $second = json_decode($lines[1], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($second);
        self::assertArrayNotHasKey('group_id', $second);
        self::assertArrayNotHasKey('gtin', $second, 'Invalid GTINs are left out.');
    }

    public function testStripeCsvWritesTheProductInventoryAndPricingFiles(): void
    {
        $files = $this->write(new StripeCsvFormat(), [FeedItems::onSale(), FeedItems::item(['id' => 'UNTRACKED', 'quantity' => null])], 'stripe_csv');

        $products = $this->csv($files['main']);
        self::assertSame(['id', 'title', 'description', 'link', 'image_link', 'additional_image_link', 'availability', 'inventory_quantity', 'inventory_not_tracked', 'price', 'sale_price', 'sale_price_effective_date', 'tax_behavior', 'brand', 'gtin', 'mpn', 'condition', 'product_category', 'item_group_id', 'color', 'size', 'weight'], array_keys($products[0]));
        self::assertSame('19.90 EUR', $products[0]['sale_price']);
        self::assertSame('2026-10-01/2026-10-31', $products[0]['sale_price_effective_date']);
        self::assertSame('https://shop.example.com/media/image/b%2C1.jpg', $products[0]['additional_image_link']);
        self::assertSame('12', $products[0]['inventory_quantity']);
        self::assertSame('false', $products[0]['inventory_not_tracked']);
        self::assertSame('true', $products[1]['inventory_not_tracked']);
        self::assertSame('inclusive', $products[0]['tax_behavior']);

        self::assertSame([['id' => 'TSHIRT-RED-M', 'availability' => 'in_stock', 'inventory_quantity' => '12']], $this->csv($files['inventory']), 'Tracked variants only.');
        $pricing = $this->csv($files['pricing']);
        self::assertSame(['id' => 'TSHIRT-RED-M', 'price' => '25.00 EUR', 'sale_price' => '19.90 EUR', 'sale_price_effective_date' => '2026-10-01/2026-10-31'], $pricing[0]);
    }

    public function testStripeShowsNoReductionWithoutEndDate(): void
    {
        $item = FeedItems::onSale(null);
        $format = new StripeCsvFormat();

        $products = $this->csv($this->write($format, [$item], 'stripe_csv')['main']);

        self::assertSame('19.90 EUR', $products[0]['price']);
        self::assertSame('', $products[0]['sale_price']);
        self::assertContains(Issue::NO_SALE_END_DATE, array_map(static fn (Issue $issue): string => $issue->code, $format->validate($item)));
    }

    public function testValidation(): void
    {
        $issues = (new GoogleXmlFormat())->validate(FeedItems::item(['id' => str_repeat('x', 51), 'title' => str_repeat('t', 151), 'brand' => null, 'imageLink' => null, 'gtin' => '123', 'mpn' => null, 'price' => 0]));
        $codes = array_map(static fn (Issue $issue): string => $issue->severity . ':' . $issue->code . ':' . $issue->field, $issues);

        self::assertEqualsCanonicalizing([
            'error:too_long:id',
            'error:missing_field:image',
            'error:missing_field:brand',
            'warning:too_long:title',
            'error:missing_price:price',
            'warning:invalid_gtin:gtin',
            'warning:no_identifiers:gtin',
        ], $codes);
        self::assertSame([], (new GoogleXmlFormat())->validate(FeedItems::item()));
    }

    public function testLongTitlesAreCut(): void
    {
        $xml = new \SimpleXMLElement($this->write(new GoogleXmlFormat(), [FeedItems::item(['title' => str_repeat('é', 200)])])['main']);

        self::assertSame(150, mb_strlen((string) $xml->channel->item[0]->title));
    }

    /**
     * @param list<FeedItem> $items
     *
     * @return array<string, string>
     */
    private function write(FeedFormatInterface $format, array $items, string $code = 'google_xml'): array
    {
        $streams = FeedItems::streams(array_keys($format->getFiles()));
        $writer = $format->open(FeedItems::context($code), $streams);
        foreach ($items as $item) {
            $writer->write($item);
        }
        $writer->finish();

        return FeedItems::contents($streams);
    }

    /** @return list<array<string, string>> */
    private function csv(string $content): array
    {
        $stream = fopen('php://memory', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);
        $header = fgetcsv($stream, null, ',', '"', '');
        self::assertIsArray($header);
        $rows = [];
        while (false !== ($row = fgetcsv($stream, null, ',', '"', ''))) {
            $rows[] = array_combine(array_map('strval', $header), array_map('strval', $row));
        }

        return $rows;
    }
}
