<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Twig;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\ItemBuilder;
use Tmonier\SyliusAgenticFeedsPlugin\Validation\Gtin;

/**
 * schema.org Product with one Offer per enabled variant (https://schema.org/Product,
 * https://schema.org/Offer), from the same data as the feeds: prices with the shop currency and
 * tax rules, a sale only with the 30-day reference price (priceValidUntil = end of the
 * catalog promotion).
 */
final readonly class JsonLdBuilder
{
    public function __construct(
        private ItemBuilder $itemBuilder,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private CurrencyContextInterface $currencyContext,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function build(ProductInterface $product): ?array
    {
        $channel = $this->channelContext->getChannel();
        if (!$channel instanceof ChannelInterface || !$product->isEnabled()) {
            return null;
        }
        $context = new FeedContext($channel, $this->localeContext->getLocaleCode(), $this->currencyContext->getCurrencyCode(), null, 'json_ld', new \DateTimeImmutable());

        $items = [];
        foreach ($product->getEnabledVariants() as $variant) {
            if ($variant instanceof ProductVariantInterface) {
                $item = $this->itemBuilder->build($variant, $context);
                if ($item instanceof \Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem) {
                    $items[] = $item;
                }
            }
        }
        if ([] === $items) {
            return null;
        }
        $first = $items[0];
        $url = strtok($first->link, '?') ?: $first->link;

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => trim((string) $product->getTranslation($context->localeCode)->getName()),
            'url' => $url,
            'sku' => (string) $product->getCode(),
        ];
        if ('' !== $first->description) {
            $data['description'] = mb_substr($first->description, 0, 5000);
        }
        $images = array_values(array_filter([$first->imageLink, ...$first->additionalImageLinks]));
        if ([] !== $images) {
            $data['image'] = $images;
        }
        if (null !== $first->brand) {
            $data['brand'] = ['@type' => 'Brand', 'name' => $first->brand];
        }
        $data['offers'] = array_map(fn (FeedItem $item): array => $this->offer($item, $url), $items);

        return $data;
    }

    /** @return array<string, mixed> */
    private function offer(FeedItem $item, string $url): array
    {
        $offer = [
            '@type' => 'Offer',
            'sku' => $item->id,
            'url' => $url,
            'price' => number_format($item->currentPrice() / 100, 2, '.', ''),
            'priceCurrency' => $item->currency,
            'availability' => FeedItem::IN_STOCK === $item->availability ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => match ($item->condition) {
                'used' => 'https://schema.org/UsedCondition',
                'refurbished' => 'https://schema.org/RefurbishedCondition',
                default => 'https://schema.org/NewCondition',
            },
        ];
        if ($item->hasVariants) {
            $offer['name'] = $item->title;
        }
        if (null !== $item->salePrice && $item->salePriceEnd instanceof \DateTimeInterface) {
            $offer['priceValidUntil'] = $item->salePriceEnd->format('Y-m-d');
        }
        $gtin = Gtin::normalize($item->gtin);
        if (null !== $gtin) {
            $offer['gtin'] = $gtin;
        }
        if (null !== $item->mpn) {
            $offer['mpn'] = $item->mpn;
        }

        return $offer;
    }
}
