<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Format;

use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedContext;
use Tmonier\SyliusAgenticFeedsPlugin\Feed\FeedItem;

/** Google Merchant Center RSS 2.0 with the g: namespace (answer 160589, read 2026-10-12). */
final class GoogleXmlFormat implements FeedFormatInterface
{
    public const CODE = 'google_xml';

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'tmonier_agentic_feeds.format.google_xml';
    }

    public function getFiles(): array
    {
        return ['main' => '.xml'];
    }

    public function getContentType(string $role): string
    {
        return 'application/xml; charset=UTF-8';
    }

    public function validate(FeedItem $item): array
    {
        return GoogleFields::validate($item);
    }

    public function open(FeedContext $context, array $streams): FeedWriterInterface
    {
        return new class($context, $streams['main']) implements FeedWriterInterface {
            private readonly \XMLWriter $xml;

            /** @param resource $stream */
            public function __construct(private readonly FeedContext $context, private $stream)
            {
                $this->xml = new \XMLWriter();
                $this->xml->openMemory();
                $this->xml->setIndent(true);
                $this->xml->startDocument('1.0', 'UTF-8');
                $this->xml->startElement('rss');
                $this->xml->writeAttribute('version', '2.0');
                $this->xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
                $this->xml->startElement('channel');
                $this->xml->writeElement('title', (string) $context->channel->getName());
                $hostname = $context->channel->getHostname();
                $this->xml->writeElement('link', null === $hostname || '' === $hostname ? '' : 'https://' . $hostname);
                $this->xml->writeElement('description', sprintf('%s (%s, %s)', (string) $context->channel->getName(), $context->localeCode, $context->currencyCode));
                $this->flush();
            }

            public function write(FeedItem $item): void
            {
                $this->xml->startElement('item');
                foreach (GoogleFields::map($item, $this->context) as $name => $value) {
                    // title, link and description are plain RSS elements, the others g:-prefixed.
                    $element = \in_array($name, ['title', 'link', 'description'], true) ? $name : 'g:' . $name;
                    foreach ((array) $value as $one) {
                        $this->xml->writeElement($element, $one);
                    }
                }
                $this->xml->endElement();
                $this->flush();
            }

            public function finish(): void
            {
                $this->xml->endElement();
                $this->xml->endElement();
                $this->xml->endDocument();
                $this->flush();
            }

            private function flush(): void
            {
                fwrite($this->stream, $this->xml->outputMemory());
            }
        };
    }
}
