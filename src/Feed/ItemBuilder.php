<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Feed;

use Sylius\Component\Core\Calculator\ProductVariantPricesCalculatorInterface;
use Sylius\Component\Core\Model\CatalogPromotionInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Currency\Converter\CurrencyConverterInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Model\TaxRateInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\VariantIdentifiersInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\VariantIdentifiersRepositoryInterface;

/**
 * Builds the neutral item of a variant for a feed context.
 *
 * Prices (Omnibus-safe): the current channel price; a sale price only when a catalog promotion
 * is applied to it and Sylius knows the lowest price of the 30 days before the reduction (the
 * reference price shown struck through, never the manual "original price"). Prices are
 * converted to the feed currency and are the prices the shop displays: with the tax when the
 * rate of the channel default tax zone includes it, without otherwise (taxIncluded tells which).
 * US and Canada feeds get prices without tax (the included tax is removed).
 */
final class ItemBuilder
{
    /** @var array<int, VariantIdentifiersInterface> identifiers of the primed batch, by variant id */
    private array $identifiers = [];

    /** @var list<int> */
    private array $primed = [];

    /** @param array{gtin: ?string, mpn: ?string, brand: ?string} $attributes product attribute codes */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CurrencyConverterInterface $currencyConverter,
        private readonly TaxRateResolverInterface $taxRateResolver,
        private readonly CalculatorInterface $taxCalculator,
        private readonly ProductVariantPricesCalculatorInterface $pricesCalculator,
        private readonly VariantIdentifiersRepositoryInterface $identifiersRepository,
        private readonly array $attributes,
        private readonly ?string $defaultBrand,
        private readonly string $condition,
        private readonly string $linkParameters,
    ) {
    }

    /** Null when the variant is not sold in the channel (no price): reported by the caller. */
    public function build(ProductVariantInterface $variant, FeedContext $context): ?FeedItem
    {
        $product = $variant->getProduct();
        $pricing = $variant->getChannelPricingForChannel($context->channel);
        if (!$product instanceof ProductInterface || null === $pricing || null === $pricing->getPrice()) {
            return null;
        }
        $locale = $context->localeCode;
        $hasVariants = $product->getEnabledVariants()->count() > 1;

        [$taxIncluded, $rate] = $this->tax($variant, $context);
        [$price, $salePrice, $saleStart, $saleEnd] = $this->prices($variant, $pricing, $context, $rate, $taxIncluded);
        $title = self::clean($this->title($product, $variant, $locale, $hasVariants));
        $description = self::clean((string) ($product->getTranslation($locale)->getDescription() ?? $product->getTranslation($locale)->getShortDescription() ?? ''));
        $description[0] = self::plainText($description[0]);

        $quantity = $variant->isTracked() ? max(0, (int) $variant->getOnHand() - (int) $variant->getOnHold()) : null;
        [$image, $additionalImages] = $this->images($product, $variant, $context);
        [$gtin, $mpn, $sharedIgnored] = $this->identifiers($product, $variant, $locale, $hasVariants);

        return new FeedItem(
            (string) $variant->getCode(),
            (string) $product->getCode(),
            (string) $product->getCode(),
            $title[0],
            $description[0],
            $this->link($product, $context),
            $image,
            $additionalImages,
            $price,
            $context->currencyCode,
            $salePrice,
            $saleStart,
            $saleEnd,
            null === $quantity || $quantity > 0 ? FeedItem::IN_STOCK : FeedItem::OUT_OF_STOCK,
            $quantity,
            $gtin,
            $mpn,
            $this->attribute($product, $this->attributes['brand'], $locale) ?? $this->defaultBrand,
            $this->condition,
            $this->productType($product->getMainTaxon(), $locale),
            null === $variant->getWeight() || $variant->getWeight() <= 0 ? null : (float) $variant->getWeight(),
            $this->options($variant, $locale),
            $hasVariants,
            $context->channel->getName(),
            $this->baseUrl($context),
            $this->optionByCode($variant, ['color', 'colour'], $locale),
            $this->optionByCode($variant, ['size'], $locale),
            $taxIncluded,
            $title[1] || $description[1],
            $variant->isShippingRequired(),
            $sharedIgnored,
            null === $context->channel->getDefaultTaxZone(),
        );
    }

    /**
     * Loads the identifiers of a batch of variants in one query (generation).
     *
     * @param list<int> $variantIds
     */
    public function primeIdentifiers(array $variantIds): void
    {
        $this->identifiers = $this->identifiersRepository->findByVariantIds($variantIds);
        $this->primed = $variantIds;
    }

    /**
     * GTIN and MPN of the variant (plugin field); the product attributes only for a product
     * without variants: one GTIN is never repeated on several variants.
     *
     * @return array{?string, ?string, bool} gtin, mpn, product-level identifier ignored
     */
    private function identifiers(ProductInterface $product, ProductVariantInterface $variant, string $locale, bool $hasVariants): array
    {
        $id = $variant->getId();
        $own = \is_int($id) && \in_array($id, $this->primed, true) ? ($this->identifiers[$id] ?? null) : $this->identifiersRepository->findOneByVariant($variant);
        $gtin = $own?->getGtin();
        $mpn = $own?->getMpn();
        $productGtin = $this->attribute($product, $this->attributes['gtin'], $locale);
        $productMpn = $this->attribute($product, $this->attributes['mpn'], $locale);
        if (!$hasVariants) {
            return [$gtin ?? $productGtin, $mpn ?? $productMpn, false];
        }

        return [$gtin, $mpn, (null === $gtin && null !== $productGtin) || (null === $mpn && null !== $productMpn)];
    }

    /** HTML to plain text: tags removed (with a space for block ends), entities decoded, spaces collapsed. */
    public static function plainText(string $html): string
    {
        $text = preg_replace('~<(br|/p|/div|/li|/h[1-6])\b[^>]*>~i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * Removes the characters XML 1.0 forbids (control characters of ERP or office copies).
     *
     * @return array{string, bool} text, whether something was removed
     */
    public static function clean(string $text): array
    {
        $clean = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return [$clean, $clean !== $text];
    }

    /** @return array{int, ?int, ?\DateTimeInterface, ?\DateTimeInterface} price, sale price, sale start, sale end */
    private function prices(ProductVariantInterface $variant, ChannelPricingInterface $pricing, FeedContext $context, ?TaxRateInterface $rate, bool $taxIncluded): array
    {
        $current = (int) $pricing->getPrice();
        $reference = null;
        $start = null;
        $end = null;
        $promotions = $pricing->getAppliedPromotions();
        if (!$promotions->isEmpty()) {
            $lowest = $this->pricesCalculator->calculateLowestPriceBeforeDiscount($variant, ['channel' => $context->channel]);
            if (null !== $lowest && $lowest > $current) {
                $reference = $lowest;
                foreach ($promotions as $promotion) {
                    if (!$promotion instanceof CatalogPromotionInterface) {
                        continue;
                    }
                    $start = self::latest($start, $promotion->getStartDate());
                    $end = self::earliest($end, $promotion->getEndDate());
                }
            }
        }

        $amount = fn (int $value): int => $this->forTarget($this->convert($value, $context), $rate, $taxIncluded);

        return null === $reference
            ? [$amount($current), null, null, null]
            : [$amount($reference), $amount($current), $start, $end];
    }

    private function convert(int $amount, FeedContext $context): int
    {
        $base = $context->channel->getBaseCurrency()?->getCode();

        return null === $base || $base === $context->currencyCode ? $amount : $this->currencyConverter->convert($amount, $base, $context->currencyCode);
    }

    /**
     * Tax situation of the price the shop displays (rate of the channel default tax zone).
     *
     * @return array{bool, ?TaxRateInterface} price includes the tax in the feed, rate
     */
    private function tax(ProductVariantInterface $variant, FeedContext $context): array
    {
        $zone = $context->channel->getDefaultTaxZone();
        if (null === $zone) {
            // Unknown: reported (tax_unknown) and treated as excluded (never claim an included tax).
            return [false, null];
        }
        $rate = $this->taxRateResolver->resolve($variant, ['zone' => $zone]);
        if (\in_array($context->countryCode, FeedItem::TAX_EXCLUSIVE_COUNTRIES, true)) {
            return [false, $rate];
        }

        // No rate: the displayed price is the final price.
        return [null === $rate || $rate->isIncludedInPrice(), $rate];
    }

    /** US/CA feeds: the tax included in the displayed price is removed; otherwise the displayed price. */
    private function forTarget(int $amount, ?TaxRateInterface $rate, bool $taxIncluded): int
    {
        if ($taxIncluded || null === $rate || !$rate->isIncludedInPrice()) {
            return $amount;
        }

        return $amount - (int) round($this->taxCalculator->calculate((float) $amount, $rate));
    }

    private function title(ProductInterface $product, ProductVariantInterface $variant, string $locale, bool $hasVariants): string
    {
        $title = trim((string) $product->getTranslation($locale)->getName());
        if (!$hasVariants) {
            return $title;
        }
        $options = $this->options($variant, $locale);
        $suffix = [] !== $options ? implode(', ', $options) : trim((string) $variant->getTranslation($locale)->getName());

        return '' === $suffix ? $title : sprintf('%s - %s', $title, $suffix);
    }

    private function link(ProductInterface $product, FeedContext $context): string
    {
        $path = $this->urlGenerator->generate('sylius_shop_product_show', [
            'slug' => $product->getTranslation($context->localeCode)->getSlug(),
            '_locale' => $context->localeCode,
        ]);
        $parameters = str_replace('{format}', $context->format, $this->linkParameters);

        return $this->baseUrl($context) . $path . ('' === $parameters ? '' : (str_contains($path, '?') ? '&' : '?') . $parameters);
    }

    /** https://<channel hostname>, or the router base URL when the channel has none. */
    private function baseUrl(FeedContext $context): string
    {
        $hostname = $context->channel->getHostname();
        if (null !== $hostname && '' !== $hostname) {
            return 'https://' . $hostname;
        }
        $root = $this->urlGenerator->generate('sylius_shop_product_show', ['slug' => 'x', '_locale' => $context->localeCode], UrlGeneratorInterface::ABSOLUTE_URL);
        $path = $this->urlGenerator->generate('sylius_shop_product_show', ['slug' => 'x', '_locale' => $context->localeCode]);

        return substr($root, 0, \strlen($root) - \strlen($path));
    }

    /** @return array{?string, list<string>} main image, additional images (variant images first) */
    private function images(ProductInterface $product, ProductVariantInterface $variant, FeedContext $context): array
    {
        $images = $product->getImages()->toArray();
        usort($images, static function (mixed $a, mixed $b) use ($variant): int {
            $own = static fn (mixed $image): int => $image instanceof ProductImageInterface && $image->hasProductVariant($variant) ? 0 : 1;

            return $own($a) <=> $own($b);
        });
        $links = [];
        foreach ($images as $image) {
            if ($image instanceof ProductImageInterface && null !== $image->getPath() && '' !== $image->getPath()) {
                $links[] = $this->baseUrl($context) . '/media/image/' . ltrim((string) $image->getPath(), '/');
            }
        }
        $links = array_values(array_unique($links));

        return [$links[0] ?? null, \array_slice($links, 1, 10)];
    }

    private function attribute(ProductInterface $product, ?string $code, string $locale): ?string
    {
        if (null === $code || '' === $code) {
            return null;
        }
        $value = $product->getAttributeByCodeAndLocale($code, $locale)?->getValue();
        if (\is_array($value)) {
            $value = reset($value);
        }

        return \is_scalar($value) && '' !== trim((string) $value) ? trim((string) $value) : null;
    }

    private function productType(?TaxonInterface $taxon, string $locale): ?string
    {
        if (null === $taxon) {
            return null;
        }
        $names = [];
        foreach (array_reverse($taxon->getAncestors()->toArray()) as $ancestor) {
            if ($ancestor instanceof TaxonInterface && !$ancestor->isRoot()) {
                $names[] = (string) $ancestor->getTranslation($locale)->getName();
            }
        }
        $names[] = (string) $taxon->getTranslation($locale)->getName();

        return implode(' > ', array_filter($names, static fn (string $name): bool => '' !== $name));
    }

    /** @return array<string, string> */
    private function options(ProductVariantInterface $variant, string $locale): array
    {
        $options = [];
        foreach ($variant->getOptionValues() as $value) {
            $value->setCurrentLocale($locale);
            $option = $value->getOption();
            $option?->setCurrentLocale($locale);
            $options[(string) ($option?->getName() ?? $value->getOptionCode())] = (string) $value->getValue();
        }

        return $options;
    }

    /** @param list<string> $codes words searched in the option codes, case-insensitive */
    private function optionByCode(ProductVariantInterface $variant, array $codes, string $locale): ?string
    {
        foreach ($variant->getOptionValues() as $value) {
            $code = strtolower((string) $value->getOptionCode());
            // "size", "t_shirt_size", "shoe_color"...: the option code contains the word.
            if ([] !== array_filter($codes, static fn (string $word): bool => str_contains($code, $word))) {
                $value->setCurrentLocale($locale);

                return (string) $value->getValue();
            }
        }

        return null;
    }

    private static function latest(?\DateTimeInterface $current, ?\DateTimeInterface $candidate): ?\DateTimeInterface
    {
        return null === $candidate || (null !== $current && $current >= $candidate) ? $current : $candidate;
    }

    private static function earliest(?\DateTimeInterface $current, ?\DateTimeInterface $candidate): ?\DateTimeInterface
    {
        return null === $candidate || (null !== $current && $current <= $candidate) ? $current : $candidate;
    }
}
