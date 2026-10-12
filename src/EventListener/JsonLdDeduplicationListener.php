<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * "Only if not already present": when the page already has a Product JSON-LD (theme, another
 * plugin), the one of this plugin is removed from the response.
 */
final class JsonLdDeduplicationListener
{
    private const OURS = '~<script type="application/ld\+json" data-tmonier-agentic-feeds>.*?</script>~s';

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $response = $event->getResponse();
        $content = $response->getContent();
        if (!\is_string($content) || !str_contains($content, 'data-tmonier-agentic-feeds') || !str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'html')) {
            return;
        }
        $others = preg_replace(self::OURS, '', $content) ?? $content;
        if (1 === preg_match('~<script[^>]*type=["\']application/ld\+json["\'][^>]*>(?:(?!</script>).)*"@type"\s*:\s*"Product"~s', $others)) {
            $response->setContent($others);
        }
    }
}
