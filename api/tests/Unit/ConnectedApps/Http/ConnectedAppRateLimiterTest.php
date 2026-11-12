<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Http;

use App\ConnectedApps\Http\ConnectedAppRateLimiter;
use App\Entity\ConnectedApp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Uid\Uuid;

final class ConnectedAppRateLimiterTest extends TestCase
{
    private function connectedApp(string $accountId, string $appId): ConnectedApp
    {
        $account = $this->createStub(\App\Entity\Account::class);
        $account->method('getId')->willReturn(Uuid::fromString($accountId));

        $app = $this->createStub(ConnectedApp::class);
        $app->method('getId')->willReturn(Uuid::fromString($appId));
        $app->method('getAccount')->willReturn($account);

        return $app;
    }

    #[Test]
    public function allowsRequestsUpToTheLimitThenThrows(): void
    {
        $limiter = new ConnectedAppRateLimiter(new ArrayAdapter(), new MockClock('2026-06-28 10:00:00'));
        $app = $this->connectedApp('00000000-0000-0000-0000-0000000000a1', '00000000-0000-0000-0000-0000000000b1');

        for ($i = 0; $i < 3; ++$i) {
            $limiter->consume($app, 'cedar', 3);
        }

        $this->expectException(TooManyRequestsHttpException::class);
        $limiter->consume($app, 'cedar', 3);
    }

    #[Test]
    public function countersAreIsolatedPerAccountAppAndBucket(): void
    {
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-06-28 10:00:00');
        $limiter = new ConnectedAppRateLimiter($cache, $clock);

        $appA = $this->connectedApp('00000000-0000-0000-0000-0000000000a1', '00000000-0000-0000-0000-0000000000b1');
        $appB = $this->connectedApp('00000000-0000-0000-0000-0000000000a2', '00000000-0000-0000-0000-0000000000b2');

        $limiter->consume($appA, 'cedar', 1);
        // Different account/app — its own fresh counter.
        $limiter->consume($appB, 'cedar', 1);
        // Different bucket on the same app — also fresh.
        $limiter->consume($appA, 'jax_phenome', 1);

        $this->expectException(TooManyRequestsHttpException::class);
        $limiter->consume($appA, 'cedar', 1);
    }

    #[Test]
    public function theWindowResetsAfterItElapses(): void
    {
        $clock = new MockClock('2026-06-28 10:00:00');
        $limiter = new ConnectedAppRateLimiter(new ArrayAdapter(), $clock);
        $app = $this->connectedApp('00000000-0000-0000-0000-0000000000a1', '00000000-0000-0000-0000-0000000000b1');

        $limiter->consume($app, 'cedar', 1);

        // Advance past the 60s window; the next request lands in a fresh window.
        $clock->sleep(61);
        $limiter->consume($app, 'cedar', 1);

        $this->addToAssertionCount(1);
    }
}
