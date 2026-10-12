<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusAgenticFeedsPlugin\Entity\ProductFeedInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Format\GoogleXmlFormat;

final class AdminTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testTheAdministrationIsReservedToAdministrators(): void
    {
        $this->client->request('GET', '/admin/product-feeds/');

        self::assertResponseRedirects();
        self::assertStringContainsString('/admin/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testCreateGenerateAndReview(): void
    {
        $product = $this->catalog->product('ADMIN', [1000]);
        $this->catalog->image($product, 'a/admin.jpg');
        $this->catalog->product('NOIMAGE', [1000]);
        $channelCode = (string) $this->catalog->channel()->getCode();
        $code = strtolower('admin_' . $this->catalog->suffix);
        $this->loginAdmin();

        $crawler = $this->client->request('GET', '/admin/product-feeds/new');
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Create')->form()->getPhpValues();
        $fields = $values['tmonier_agentic_feeds_product_feed'] ?? null;
        self::assertIsArray($fields);
        $fields['code'] = $code;
        $fields['name'] = 'Admin feed';
        $fields['channel'] = $channelCode;
        $fields['localeCode'] = 'fr_FR';
        $fields['currencyCode'] = 'EUR';
        $fields['format'] = GoogleXmlFormat::CODE;
        $fields['accessToken'] = 'short';
        $fields['enabled'] = '1';
        $values['tmonier_agentic_feeds_product_feed'] = $fields;

        $this->client->request('POST', '/admin/product-feeds/new', $values);
        self::assertResponseStatusCodeSame(422);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('This locale is not enabled in the channel.', $content);
        self::assertStringContainsString('The token needs at least 16 characters.', $content);

        $values['tmonier_agentic_feeds_product_feed']['localeCode'] = 'en_US';
        $values['tmonier_agentic_feeds_product_feed']['accessToken'] = '';
        $this->client->request('POST', '/admin/product-feeds/new', $values);
        self::assertResponseRedirects();
        $feed = $this->entityManager()->getRepository(ProductFeedInterface::class)->findOneBy(['code' => $code]);
        self::assertInstanceOf(ProductFeedInterface::class, $feed);
        $id = $feed->getId();
        self::assertIsInt($id);

        $crawler = $this->client->request('GET', '/admin/product-feeds/?criteria[search][value]=' . $code);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Never generated', $crawler->filter(sprintf('[data-test-tmonier-agentic-feeds-status="%s"]', $code))->text());
        $form = $crawler->filter('[data-test-tmonier-agentic-feeds-action="generate"]')->form();

        $this->client->request('POST', sprintf('/admin/product-feeds/%d/generate', $id), ['_csrf_token' => 'wrong']);
        self::assertResponseStatusCodeSame(403);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/product-feeds/');

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('The feed generation was requested.', $crawler->filter('body')->text());
        self::assertNotNull($this->file($code, '.xml'));
        $crawler = $this->client->request('GET', '/admin/product-feeds/?criteria[search][value]=' . $code);
        self::assertStringContainsString('Items left out', $crawler->filter(sprintf('[data-test-tmonier-agentic-feeds-status="%s"]', $code))->text());
        self::assertStringContainsString('/feeds/' . $code, (string) $crawler->filter('[data-test-tmonier-agentic-feeds-file="main"]')->attr('href'));

        $crawler = $this->client->request('GET', sprintf('/admin/product-feeds/issues?criteria[feed]=%d&criteria[severity]=error', $id));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('NOIMAGE_' . $this->catalog->suffix, $crawler->filter('table')->text());
        self::assertStringContainsString('Required field missing: image.', $crawler->filter('table')->text());

        $crawler = $this->client->request('GET', sprintf('/admin/product-feeds/%d/edit', $id));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Admin feed', $crawler->filter('.breadcrumb')->text());
    }
}
