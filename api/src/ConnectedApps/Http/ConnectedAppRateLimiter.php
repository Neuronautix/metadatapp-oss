<?php

declare(strict_types=1);

namespace App\ConnectedApps\Http;

use App\Entity\ConnectedApp;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Lightweight per-(account, app, bucket) fixed-window rate limiter for the
 * external-service proxy endpoints. Backed by the shared application cache so it
 * needs no extra infrastructure; it protects upstream public APIs (CEDAR, JAX
 * Phenome) from a single tenant exhausting them through the proxy.
 *
 * A fixed window keyed on `floor(now / window)` keeps the implementation
 * stateless beyond the counter and resets cleanly each window.
 */
final class ConnectedAppRateLimiter
{
    public const int DEFAULT_LIMIT = 60;
    private const int WINDOW_SECONDS = 60;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Count one request against the (account, app, bucket) window, throwing
     * {@see TooManyRequestsHttpException} (HTTP 429) once the limit is reached.
     */
    public function consume(ConnectedApp $connectedApp, string $bucket, int $limit = self::DEFAULT_LIMIT): void
    {
        $now = $this->clock->now()->getTimestamp();
        $windowId = intdiv($now, self::WINDOW_SECONDS);

        $key = \sprintf(
            'capp_rl.%s.%s.%s.%d',
            preg_replace('/[^a-z0-9_]+/i', '_', $bucket) ?? 'bucket',
            (string) $connectedApp->getAccount()->getId(),
            (string) $connectedApp->getId(),
            $windowId,
        );

        $item = $this->cache->getItem($key);
        $count = $item->isHit() && \is_int($item->get()) ? (int) $item->get() : 0;

        if ($count >= $limit) {
            $retryAfter = self::WINDOW_SECONDS - ($now % self::WINDOW_SECONDS);

            throw new TooManyRequestsHttpException($retryAfter, 'Too many requests for this connected app. Please retry shortly.');
        }

        // Keep the window entry alive a little past its boundary so a burst that
        // straddles the edge is still counted against the right window.
        $item->set($count + 1)->expiresAfter(self::WINDOW_SECONDS * 2);
        $this->cache->save($item);
    }
}
