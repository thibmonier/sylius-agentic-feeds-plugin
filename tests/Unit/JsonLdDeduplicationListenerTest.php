<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusAgenticFeedsPlugin\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tmonier\SyliusAgenticFeedsPlugin\EventListener\JsonLdDeduplicationListener;

final class JsonLdDeduplicationListenerTest extends TestCase
{
    private const OURS = '<script type="application/ld+json" data-tmonier-agentic-feeds>{"@context":"https://schema.org","@type":"Product"}</script>';

    public function testOursIsRemovedWhenThePageAlreadyHasAProduct(): void
    {
        $theme = '<script type="application/ld+json">{"@context": "https://schema.org", "@type": "Product", "name": "X"}</script>';

        self::assertSame('<html>' . $theme . '</html>', $this->filter('<html>' . $theme . self::OURS . '</html>'));
    }

    public function testOursIsKeptOtherwise(): void
    {
        $breadcrumb = '<script type="application/ld+json">{"@type": "BreadcrumbList"}</script>';

        self::assertSame('<html>' . $breadcrumb . self::OURS . '</html>', $this->filter('<html>' . $breadcrumb . self::OURS . '</html>'));
    }

    private function filter(string $html): string
    {
        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $kernel = $this->createStub(HttpKernelInterface::class);
        (new JsonLdDeduplicationListener())(new ResponseEvent($kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, $response));

        return (string) $response->getContent();
    }
}
