<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleXmlFormat;
use Tmonier\SyliusAgenticFeedsPlugin\Format\StripeCsvFormat;

final class FeedDownloadTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testAPublicFeed(): void
    {
        $product = $this->catalog->product('PUBLIC', [1000]);
        $this->catalog->image($product, 'a/public.jpg');
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);
        $code = (string) $feed->getCode();

        $this->client->request('GET', '/feeds/' . $code);
        self::assertResponseStatusCodeSame(404, 'Not generated yet.');

        $this->generate($feed);
        $this->client->request('GET', '/feeds/' . $code);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        $lastModified = (string) $this->client->getResponse()->headers->get('Last-Modified');
        self::assertStringContainsString('PUBLIC_' . $this->catalog->suffix, (string) $this->client->getInternalResponse()->getContent());

        $this->client->request('GET', '/feeds/' . $code, server: ['HTTP_IF_MODIFIED_SINCE' => $lastModified]);
        self::assertResponseStatusCodeSame(304);

        $this->client->request('GET', '/feeds/' . $code . '/inventory');
        self::assertResponseStatusCodeSame(404, 'No such file for this format.');
        $this->client->request('GET', '/feeds/unknown');
        self::assertResponseStatusCodeSame(404);

        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        $feed->setEnabled(false);
        $this->entityManager()->flush();
        $this->client->request('GET', '/feeds/' . $code);
        self::assertResponseStatusCodeSame(404, 'Disabled feeds are not served.');
    }

    public function testAFeedProtectedByAToken(): void
    {
        $product = $this->catalog->product('PRIVATE', [1000]);
        $this->catalog->image($product, 'a/private.jpg');
        $token = 'secret-' . bin2hex(random_bytes(8));
        $feed = $this->catalog->feed(StripeCsvFormat::CODE, $token);
        $code = (string) $feed->getCode();
        $this->generate($feed);

        $this->client->request('GET', '/feeds/' . $code);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/feeds/' . $code . '?token=wrong');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/feeds/' . $code . '/pricing?token=' . $token);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringStartsWith('id,price,sale_price,sale_price_effective_date', (string) $this->client->getInternalResponse()->getContent());
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', '/feeds/' . $code, server: ['HTTP_X_FEED_TOKEN' => $token]);
        self::assertResponseIsSuccessful();
    }

    public function testFeedsOfADisabledChannelAreNotServed(): void
    {
        $product = $this->catalog->product('CHANNELOFF', [1000]);
        $this->catalog->image($product, 'a/channeloff.jpg');
        $feed = $this->catalog->feed(GoogleXmlFormat::CODE);
        $code = (string) $feed->getCode();
        $this->generate($feed);
        $channel = $this->entityManager()->getRepository(\Sylius\Component\Core\Model\ChannelInterface::class)->findOneBy(['code' => $this->catalog->channel()->getCode()]);
        self::assertInstanceOf(\Sylius\Component\Core\Model\ChannelInterface::class, $channel);
        $channel->setEnabled(false);
        $this->entityManager()->flush();

        $this->client->request('GET', '/feeds/' . $code);

        self::assertResponseStatusCodeSame(404);
    }
}
