<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Fixture;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleXmlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\OpenAiJsonlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\StripeCsvFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\ProductFeedRepositoryInterface;

/**
 * Demo feeds: Google Merchant Center XML, OpenAI JSONL and Stripe CSV for each channel, in its
 * default locale and base currency (the Stripe one protected by an access token). Idempotent.
 */
final class ProductFeedFixture extends AbstractFixture
{
    private const FORMATS = ['google' => GoogleXmlFormat::CODE, 'openai' => OpenAiJsonlFormat::CODE, 'stripe' => StripeCsvFormat::CODE];

    /**
     * @param FactoryInterface<ProductFeedInterface> $feedFactory
     * @param RepositoryInterface<ChannelInterface> $channelRepository
     */
    public function __construct(
        private readonly ObjectManager $manager,
        private readonly FactoryInterface $feedFactory,
        private readonly ProductFeedRepositoryInterface $feedRepository,
        private readonly RepositoryInterface $channelRepository,
    ) {
    }

    public function getName(): string
    {
        return 'tmonier_agentic_feeds';
    }

    public function load(array $options): void
    {
        foreach ($this->channelRepository->findAll() as $channel) {
            $locale = $channel->getDefaultLocale()?->getCode();
            $currency = $channel->getBaseCurrency()?->getCode();
            if (null === $locale || null === $currency) {
                continue;
            }
            foreach (self::FORMATS as $prefix => $format) {
                $code = strtolower(sprintf('%s_%s', $prefix, (string) $channel->getCode()));
                if ($this->feedRepository->findOneByCode($code) instanceof \Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface) {
                    continue;
                }
                $feed = $this->feedFactory->createNew();
                $feed->setCode($code);
                $feed->setName(sprintf('%s - %s', ucfirst($prefix), (string) $channel->getName()));
                $feed->setChannel($channel);
                $feed->setLocaleCode($locale);
                $feed->setCurrencyCode($currency);
                $feed->setFormat($format);
                if ('stripe' === $prefix) {
                    $feed->setAccessToken('demo-' . bin2hex(random_bytes(12)));
                    $feed->setShippingRulesText(sprintf('%s:ALL:Standard:2-5:9.90 %s', 'USD' === $currency ? 'US' : 'FR', $currency));
                }
                $feed->setEnabled(true);
                $this->manager->persist($feed);
            }
        }
        $this->manager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
    }
}
