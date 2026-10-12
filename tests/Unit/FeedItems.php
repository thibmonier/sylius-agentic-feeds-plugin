<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use Sylius\Component\Core\Model\Channel;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;

/** Items and contexts for the format tests. */
final class FeedItems
{
    public static function context(string $format = 'google_xml', ?string $country = 'FR'): FeedContext
    {
        $channel = new Channel();
        $channel->setCode('WEB_FR');
        $channel->setName('Boutique');
        $channel->setHostname('shop.example.com');

        return new FeedContext($channel, 'fr_FR', 'EUR', $country, $format, new \DateTimeImmutable('2026-10-12 10:00:00+02:00'));
    }

    /** @param array<string, mixed> $overrides */
    public static function item(array $overrides = []): FeedItem
    {
        $values = array_merge([
            'id' => 'TSHIRT-RED-M',
            'itemGroupId' => 'TSHIRT',
            'productCode' => 'TSHIRT',
            'title' => 'T-shirt - Rouge, M',
            'description' => 'Coton bio, "coupe droite"',
            'link' => 'https://shop.example.com/fr_FR/products/t-shirt',
            'imageLink' => 'https://shop.example.com/media/image/a.jpg',
            'additionalImageLinks' => ['https://shop.example.com/media/image/b,1.jpg'],
            'price' => 2500,
            'currency' => 'EUR',
            'salePrice' => null,
            'salePriceStart' => null,
            'salePriceEnd' => null,
            'availability' => FeedItem::IN_STOCK,
            'quantity' => 12,
            'gtin' => '4006381333931',
            'mpn' => null,
            'brand' => 'Acme',
            'condition' => 'new',
            'productType' => 'Vêtements > T-shirts',
            'weightKg' => 0.25,
            'options' => ['Couleur' => 'Rouge', 'Taille' => 'M'],
            'hasVariants' => true,
            'sellerName' => 'Boutique',
            'sellerUrl' => 'https://shop.example.com',
            'color' => 'Rouge',
            'size' => 'M',
        ], $overrides);

        return new FeedItem(...$values);
    }

    /** A reduced item: 25.00 reference, 19.90 now, until 2026-10-31. */
    public static function onSale(?\DateTimeInterface $end = new \DateTimeImmutable('2026-10-31 23:59:00+01:00')): FeedItem
    {
        return self::item(['salePrice' => 1990, 'salePriceStart' => new \DateTimeImmutable('2026-10-01 00:00:00+02:00'), 'salePriceEnd' => $end]);
    }

    /**
     * @param array<string, resource> $streams
     *
     * @return array<string, string>
     */
    public static function contents(array $streams): array
    {
        $contents = [];
        foreach ($streams as $role => $stream) {
            rewind($stream);
            $contents[$role] = (string) stream_get_contents($stream);
        }

        return $contents;
    }

    /**
     * @param list<string> $roles
     *
     * @return array<string, resource>
     */
    public static function streams(array $roles): array
    {
        $streams = [];
        foreach ($roles as $role) {
            $stream = fopen('php://memory', 'w+b');
            if (false === $stream) {
                throw new \RuntimeException('No memory stream.');
            }
            $streams[$role] = $stream;
        }

        return $streams;
    }
}
