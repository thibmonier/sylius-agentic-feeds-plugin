<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Twig;

use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\FeedStorageInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AgenticFeedsExtension extends AbstractExtension
{
    public function __construct(
        private readonly JsonLdBuilder $jsonLdBuilder,
        private readonly bool $jsonLdEnabled,
        private readonly FormatRegistry $formats,
        private readonly FeedStorageInterface $storage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tmonier_agentic_feeds_json_ld', $this->jsonLd(...), ['is_safe' => ['html']]),
            new TwigFunction('tmonier_agentic_feeds_file_urls', $this->fileUrls(...)),
        ];
    }

    /** @return array<string, string> role => absolute URL (with the access token) of the generated files */
    public function fileUrls(ProductFeedInterface $feed): array
    {
        $code = (string) $feed->getCode();
        if (!$this->formats->has((string) $feed->getFormat())) {
            return [];
        }
        $urls = [];
        foreach ($this->formats->get((string) $feed->getFormat())->getFiles() as $role => $suffix) {
            if (!$this->storage->exists(FormatRegistry::fileName($code, $suffix))) {
                continue;
            }
            $parameters = ['code' => $code, 'role' => $role];
            if (null !== $feed->getAccessToken()) {
                $parameters['token'] = $feed->getAccessToken();
            }
            $urls[$role] = $this->urlGenerator->generate('tmonier_agentic_feeds_download', $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $urls;
    }

    /** The JSON-LD script of a product page, empty when disabled or not applicable. */
    public function jsonLd(mixed $product): string
    {
        if (!$this->jsonLdEnabled || !$product instanceof ProductInterface) {
            return '';
        }
        $data = $this->jsonLdBuilder->build($product);
        if (null === $data) {
            return '';
        }
        // JSON_HEX_TAG: no "</script>" can close the element early.
        $json = json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR);

        return sprintf('<script type="application/ld+json" data-tmonier-agentic-feeds>%s</script>', $json);
    }
}
