<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Controller\Admin;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Message\GenerateFeed;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\ProductFeedRepositoryInterface;

/** "Generate now": dispatched on the bus (handled synchronously unless you route it to a worker). */
final readonly class GenerateFeedController
{
    public function __construct(
        private ProductFeedRepositoryInterface $feedRepository,
        private MessageBusInterface $bus,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $feed = $this->feedRepository->find($id);
        if (null === $feed) {
            throw new NotFoundHttpException();
        }
        $token = $request->request->get('_csrf_token');
        if (!\is_string($token) || !$this->csrfTokenManager->isTokenValid(new CsrfToken('tmonier_agentic_feeds_generate_' . $id, $token))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $this->bus->dispatch(new GenerateFeed((string) $feed->getCode()));

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'tmonier_agentic_feeds.flash.generation_requested');
        }

        return new RedirectResponse($this->urlGenerator->generate('tmonier_agentic_feeds_admin_product_feed_index'));
    }
}
