<?php

declare(strict_types=1);

namespace Tmonier\SyliusAgenticFeedsPlugin\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tmonier\SyliusAgenticFeedsPlugin\Format\FormatRegistry;
use Tmonier\SyliusAgenticFeedsPlugin\Repository\ProductFeedRepositoryInterface;
use Tmonier\SyliusAgenticFeedsPlugin\Storage\FeedStorageInterface;

/**
 * Stable URL of a feed file: /feeds/{code} (main file) or /feeds/{code}/{role} (e.g.
 * /feeds/stripe/inventory). A feed with an access token answers 404 without it (query
 * "token" or header X-Feed-Token), like a missing or disabled feed.
 */
final class FeedDownloadController
{
    public function __construct(
        private readonly ProductFeedRepositoryInterface $feedRepository,
        private readonly FormatRegistry $formats,
        private readonly FeedStorageInterface $storage,
    ) {
    }

    public function __invoke(Request $request, string $code, string $role = 'main'): Response
    {
        $feed = $this->feedRepository->findOneByCode($code);
        if (null === $feed || !$feed->isEnabled() || !$this->formats->has((string) $feed->getFormat())) {
            throw new NotFoundHttpException();
        }
        $token = $feed->getAccessToken();
        $given = $request->query->get('token') ?? $request->headers->get('X-Feed-Token');
        if (null !== $token && (!\is_string($given) || !hash_equals($token, $given))) {
            throw new NotFoundHttpException();
        }
        $format = $this->formats->get((string) $feed->getFormat());
        $suffix = $format->getFiles()[$role] ?? null;
        if (null === $suffix) {
            throw new NotFoundHttpException();
        }
        $path = FormatRegistry::fileName($code, $suffix);
        $modified = $this->storage->lastModified($path);
        if (null === $modified) {
            throw new NotFoundHttpException();
        }

        $response = new StreamedResponse();
        $response->setLastModified($modified);
        $response->headers->set('Content-Type', $format->getContentType($role));
        $response->headers->set('Content-Disposition', sprintf('inline; filename="%s"', $path));
        $response->headers->set('X-Robots-Tag', 'noindex');
        null === $token ? $response->setPublic() : $response->setPrivate();
        $response->setMaxAge(0);
        $size = $this->storage->size($path);
        if (null !== $size) {
            $response->headers->set('Content-Length', (string) $size);
        }
        if ($response->isNotModified($request)) {
            return $response;
        }

        $storage = $this->storage;
        $response->setCallback(static function () use ($storage, $path): void {
            $stream = $storage->read($path);
            if (null === $stream) {
                return;
            }
            $out = fopen('php://output', 'wb');
            if (false !== $out) {
                stream_copy_to_stream($stream, $out);
                fclose($out);
            }
            fclose($stream);
        });

        return $response;
    }
}
