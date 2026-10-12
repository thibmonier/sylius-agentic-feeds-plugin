<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShopJsonLdTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testTheProductPageHasAProductJsonLd(): void
    {
        $channel = $this->catalog->useShopChannel();
        $product = $this->catalog->product('LD', [1500, 1700]);
        $this->catalog->attribute($product, 'brand', 'Acme');
        $this->catalog->image($product, 'a/ld.jpg');
        $currency = (string) $channel->getBaseCurrency()?->getCode();

        $crawler = $this->client->request('GET', '/en_US/products/' . $product->getSlug());

        self::assertResponseIsSuccessful();
        $scripts = $crawler->filter('script[type="application/ld+json"][data-tmonier-agentic-feeds]');
        self::assertCount(1, $scripts);
        $data = json_decode($scripts->text(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('https://schema.org', $data['@context']);
        self::assertSame('Product', $data['@type']);
        self::assertSame('Product LD', $data['name']);
        self::assertSame('Organic cotton.', $data['description']);
        self::assertSame(['@type' => 'Brand', 'name' => 'Acme'], $data['brand']);
        self::assertIsArray($data['image']);
        self::assertSame('https://localhost/media/image/a/ld.jpg', $data['image'][0] ?? null);
        self::assertIsArray($data['offers']);
        self::assertCount(2, $data['offers']);
        $offer = $data['offers'][0];
        self::assertIsArray($offer);
        self::assertSame(['@type', 'sku', 'url', 'price', 'priceCurrency', 'availability', 'itemCondition', 'name'], array_keys($offer));
        self::assertSame($currency, $offer['priceCurrency']);
        self::assertSame('https://schema.org/InStock', $offer['availability']);
        self::assertIsString($data['url']);
        self::assertStringNotContainsString('utm_', $data['url']);
    }

    public function testTheOfferPriceIsTheDisplayedPrice(): void
    {
        $this->catalog->useShopChannel();
        $product = $this->catalog->product('LDTAX', [10000]);
        $this->catalog->image($product, 'a/ldtax.jpg');
        // 20 % not included: the page shows the channel price without tax.
        $this->catalog->tax($this->catalog->variant($product), 0.2);

        $crawler = $this->client->request('GET', '/en_US/products/' . $product->getSlug());

        self::assertResponseIsSuccessful();
        $data = json_decode($crawler->filter('script[data-tmonier-agentic-feeds]')->text(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertIsArray($data['offers']);
        self::assertIsArray($data['offers'][0]);
        self::assertSame('100.00', $data['offers'][0]['price']);
        self::assertStringContainsString('100.00', $crawler->filter('[data-test-product-price]')->text());
    }
}
