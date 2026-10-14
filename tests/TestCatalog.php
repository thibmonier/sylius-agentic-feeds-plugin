<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Addressing\Model\ZoneMemberInterface;
use Sylius\Component\Attribute\AttributeType\TextAttributeType;
use Sylius\Component\Core\Model\CatalogPromotionInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ChannelPriceHistoryConfigInterface;
use Sylius\Component\Core\Model\ChannelPricingInterface;
use Sylius\Component\Core\Model\ProductImageInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Sylius\Component\Core\Model\TaxRateInterface;
use Sylius\Component\Currency\Model\CurrencyInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Product\Model\ProductAttributeInterface;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Taxation\Model\TaxCategoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Webmozart\Assert\Assert;

/**
 * Data created by the tests (inside their rolled-back transaction): a channel of its own (so
 * feeds only hold the test products), products, prices, promotions, taxes, attributes, feeds.
 */
final class TestCatalog
{
    public const LOCALE = 'en_US';

    public readonly string $suffix;

    private ?ChannelInterface $channel = null;

    private int $feeds = 0;

    public function __construct(private readonly ContainerInterface $container)
    {
        $this->suffix = strtoupper(bin2hex(random_bytes(3)));
    }

    /** A channel of its own: en_US, EUR, hostname feeds-<suffix>.test, lowest prices of discounted products visible. */
    public function channel(): ChannelInterface
    {
        if ($this->channel instanceof \Sylius\Component\Core\Model\ChannelInterface) {
            return $this->channel;
        }
        $locale = $this->entityManager()->getRepository(LocaleInterface::class)->findOneBy(['code' => self::LOCALE]);
        Assert::isInstanceOf($locale, LocaleInterface::class);
        $currency = $this->currency('EUR');

        /** @var FactoryInterface<ChannelInterface> $factory */
        $factory = $this->container->get('sylius.factory.channel');
        $channel = $factory->createNew();
        $channel->setCode('FEEDS_' . $this->suffix);
        $channel->setName('Feeds ' . $this->suffix);
        $channel->setHostname(strtolower('feeds-' . $this->suffix . '.test'));
        $channel->addLocale($locale);
        $channel->setDefaultLocale($locale);
        $channel->addCurrency($currency);
        $channel->setBaseCurrency($currency);
        $channel->setTaxCalculationStrategy('order_items_based');
        $channel->setEnabled(true);
        $config = $channel->getChannelPriceHistoryConfig();
        if (!$config instanceof ChannelPriceHistoryConfigInterface) {
            /** @var FactoryInterface<ChannelPriceHistoryConfigInterface> $configFactory */
            $configFactory = $this->container->get('sylius.factory.channel_price_history_config');
            $config = $configFactory->createNew();
            $channel->setChannelPriceHistoryConfig($config);
        }
        $config->setLowestPriceForDiscountedProductsVisible(true);
        $this->entityManager()->persist($channel);
        $this->entityManager()->flush();

        return $this->channel = $channel;
    }

    /** Use the shop channel of the test application (hostname localhost) instead of a new one. */
    public function useShopChannel(): ChannelInterface
    {
        $channel = $this->entityManager()->getRepository(ChannelInterface::class)->findOneBy(['hostname' => 'localhost']);
        Assert::isInstanceOf($channel, ChannelInterface::class);

        return $this->channel = $channel;
    }

    public function currency(string $code): CurrencyInterface
    {
        $currency = $this->entityManager()->getRepository(CurrencyInterface::class)->findOneBy(['code' => $code]);
        if ($currency instanceof CurrencyInterface) {
            return $currency;
        }
        /** @var FactoryInterface<CurrencyInterface> $factory */
        $factory = $this->container->get('sylius.factory.currency');
        $currency = $factory->createNew();
        $currency->setCode($code);
        $this->entityManager()->persist($currency);
        $this->entityManager()->flush();

        return $currency;
    }

    /**
     * A product of the channel with one variant per price (codes <CODE>-<n>, the first variant
     * <CODE> when only one).
     *
     * @param list<int> $prices
     */
    public function product(string $code, array $prices = [2500], ?TaxonInterface $taxon = null, string $description = '<p>Organic <b>cotton</b>.</p>'): ProductInterface
    {
        $channel = $this->channel();
        /** @var FactoryInterface<ProductInterface> $productFactory */
        $productFactory = $this->container->get('sylius.factory.product');
        /** @var FactoryInterface<ProductVariantInterface> $variantFactory */
        $variantFactory = $this->container->get('sylius.factory.product_variant');
        /** @var FactoryInterface<ChannelPricingInterface> $pricingFactory */
        $pricingFactory = $this->container->get('sylius.factory.channel_pricing');

        $product = $productFactory->createNew();
        $product->setCode($code . '_' . $this->suffix);
        $product->setCurrentLocale(self::LOCALE);
        $product->setFallbackLocale(self::LOCALE);
        $product->setName('Product ' . $code);
        $product->setSlug(strtolower('p-' . $code . '-' . $this->suffix));
        $product->setDescription($description);
        $product->setEnabled(true);
        $product->addChannel($channel);
        if ($taxon instanceof \Sylius\Component\Core\Model\TaxonInterface) {
            /** @var FactoryInterface<ProductTaxonInterface> $productTaxonFactory */
            $productTaxonFactory = $this->container->get('sylius.factory.product_taxon');
            $productTaxon = $productTaxonFactory->createNew();
            $productTaxon->setTaxon($taxon);
            $product->addProductTaxon($productTaxon);
            $product->setMainTaxon($taxon);
        }
        foreach ($prices as $index => $price) {
            $variant = $variantFactory->createNew();
            $variant->setCode(1 === \count($prices) ? $product->getCode() : sprintf('%s-%d', (string) $product->getCode(), $index + 1));
            $variant->setCurrentLocale(self::LOCALE);
            $variant->setFallbackLocale(self::LOCALE);
            $variant->setName(sprintf('Variant %d', $index + 1));
            $variant->setPosition($index);
            $variant->setTracked(false);
            $pricing = $pricingFactory->createNew();
            $pricing->setChannelCode($channel->getCode());
            $pricing->setPrice($price);
            $variant->addChannelPricing($pricing);
            $product->addVariant($variant);
        }
        $this->entityManager()->persist($product);
        $this->entityManager()->flush();

        return $product;
    }

    public function variant(ProductInterface $product, int $index = 0): ProductVariantInterface
    {
        $variant = $product->getVariants()->toArray()[$index] ?? null;
        Assert::isInstanceOf($variant, ProductVariantInterface::class);

        return $variant;
    }

    public function image(ProductInterface $product, string $path): void
    {
        /** @var FactoryInterface<ProductImageInterface> $factory */
        $factory = $this->container->get('sylius.factory.product_image');
        $image = $factory->createNew();
        $image->setPath($path);
        $image->setType('main');
        $product->addImage($image);
        $this->entityManager()->flush();
    }

    /** A text attribute value (brand, gtin, mpn), the attribute created when missing. */
    public function attribute(ProductInterface $product, string $code, string $value): void
    {
        $attribute = $this->entityManager()->getRepository(ProductAttributeInterface::class)->findOneBy(['code' => $code]);
        if (!$attribute instanceof ProductAttributeInterface) {
            /** @var FactoryInterface<ProductAttributeInterface> $attributeFactory */
            $attributeFactory = $this->container->get('sylius.factory.product_attribute');
            $attribute = $attributeFactory->createNew();
            $attribute->setCode($code);
            $attribute->setType(TextAttributeType::TYPE);
            $attribute->setStorageType('text');
            $attribute->setTranslatable(false);
            $attribute->setCurrentLocale(self::LOCALE);
            $attribute->setFallbackLocale(self::LOCALE);
            $attribute->setName(ucfirst($code));
            $this->entityManager()->persist($attribute);
        }
        /** @var FactoryInterface<ProductAttributeValueInterface> $valueFactory */
        $valueFactory = $this->container->get('sylius.factory.product_attribute_value');
        $attributeValue = $valueFactory->createNew();
        $attributeValue->setAttribute($attribute);
        $attributeValue->setLocaleCode(self::LOCALE);
        $attributeValue->setValue($value);
        $product->addAttribute($attributeValue);
        $this->entityManager()->flush();
    }

    /** GTIN / MPN of a variant (plugin field). */
    public function identifiers(ProductVariantInterface $variant, ?string $gtin, ?string $mpn = null): void
    {
        $manager = $this->container->get('tmonier_agentic_feeds.identifiers_manager');
        Assert::isInstanceOf($manager, \Tmonier\SyliusAgenticFeedsPlugin\Form\VariantIdentifiersManager::class);
        $manager->write($variant, $gtin, $mpn);
        $this->entityManager()->flush();
    }

    /** A catalog promotion applied to the variant price: current price $price, 30-day lowest $lowest. */
    public function promotion(ProductVariantInterface $variant, int $price, ?int $lowest, ?\DateTimeInterface $end): void
    {
        /** @var FactoryInterface<CatalogPromotionInterface> $factory */
        $factory = $this->container->get('sylius.factory.catalog_promotion');
        $promotion = $factory->createNew();
        $promotion->setCode('CP_' . $this->suffix . bin2hex(random_bytes(2)));
        $promotion->setName('Sale');
        $promotion->setStartDate(new \DateTime('-1 day'));
        $promotion->setEndDate($end);
        $promotion->addChannel($this->channel());
        $this->entityManager()->persist($promotion);

        $pricing = $variant->getChannelPricingForChannel($this->channel());
        Assert::isInstanceOf($pricing, ChannelPricingInterface::class);
        $pricing->setOriginalPrice((int) $pricing->getPrice());
        $pricing->setPrice($price);
        $pricing->setLowestPriceBeforeDiscount($lowest);
        $pricing->addAppliedPromotion($promotion);
        $this->entityManager()->flush();
    }

    /** A rate of $amount (e.g. 0.2), included in prices or not, in a new default tax zone of the channel. */
    public function tax(ProductVariantInterface $variant, float $amount, bool $included = false): void
    {
        /** @var FactoryInterface<ZoneInterface> $zoneFactory */
        $zoneFactory = $this->container->get('sylius.factory.zone');
        /** @var FactoryInterface<ZoneMemberInterface> $memberFactory */
        $memberFactory = $this->container->get('sylius.factory.zone_member');
        $zone = $zoneFactory->createNew();
        $zone->setCode('TAX_' . $this->suffix);
        $zone->setName('Tax ' . $this->suffix);
        $zone->setType(ZoneInterface::TYPE_COUNTRY);
        $member = $memberFactory->createNew();
        $member->setCode('FR');
        $zone->addMember($member);
        $this->entityManager()->persist($zone);

        /** @var FactoryInterface<TaxCategoryInterface> $categoryFactory */
        $categoryFactory = $this->container->get('sylius.factory.tax_category');
        $category = $categoryFactory->createNew();
        $category->setCode('TC_' . $this->suffix);
        $category->setName('Standard');
        $this->entityManager()->persist($category);

        /** @var FactoryInterface<TaxRateInterface> $rateFactory */
        $rateFactory = $this->container->get('sylius.factory.tax_rate');
        $rate = $rateFactory->createNew();
        $rate->setCode('TR_' . $this->suffix);
        $rate->setName('VAT');
        $rate->setAmount($amount);
        $rate->setIncludedInPrice($included);
        $rate->setCalculator('default');
        $rate->setZone($zone);
        $rate->setCategory($category);
        $this->entityManager()->persist($rate);

        $variant->setTaxCategory($category);
        $this->channel()->setDefaultTaxZone($zone);
        $this->entityManager()->flush();
    }

    public function taxon(string $name, ?TaxonInterface $parent = null): TaxonInterface
    {
        /** @var FactoryInterface<TaxonInterface> $factory */
        $factory = $this->container->get('sylius.factory.taxon');
        $taxon = $factory->createNew();
        $taxon->setCode(strtoupper($name) . '_' . $this->suffix);
        $taxon->setCurrentLocale(self::LOCALE);
        $taxon->setFallbackLocale(self::LOCALE);
        $taxon->setName($name);
        $taxon->setSlug(strtolower($name . '-' . $this->suffix));
        if ($parent instanceof \Sylius\Component\Core\Model\TaxonInterface) {
            $parent->addChild($taxon);
        }
        $this->entityManager()->persist($taxon);
        $this->entityManager()->flush();

        return $taxon;
    }

    public function feed(string $format, ?string $token = null, string $currency = 'EUR', ?string $country = 'FR'): ProductFeedInterface
    {
        /** @var FactoryInterface<ProductFeedInterface> $factory */
        $factory = $this->container->get('tmonier_agentic_feeds.factory.product_feed');
        $feed = $factory->createNew();
        $feed->setCode(strtolower(sprintf('%s_%s_%d', $format, $this->suffix, ++$this->feeds)));
        $feed->setName('Feed ' . $format);
        $feed->setChannel($this->channel());
        $feed->setLocaleCode(self::LOCALE);
        $feed->setCurrencyCode($currency);
        $feed->setCountryCode($country);
        $feed->setShippingRulesText(sprintf("FR:ALL:Standard:2-4:9.90 %s\nBE:ALL:Standard:3-5:12.90 %s", $currency, $currency));
        $feed->setFormat($format);
        $feed->setAccessToken($token);
        $feed->setEnabled(true);
        $this->entityManager()->persist($feed);
        $this->entityManager()->flush();

        return $feed;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        Assert::isInstanceOf($entityManager, EntityManagerInterface::class);

        return $entityManager;
    }
}
