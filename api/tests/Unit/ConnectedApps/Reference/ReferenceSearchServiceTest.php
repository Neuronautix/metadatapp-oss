<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference;

use App\ConnectedApps\Reference\ReferenceResult;
use App\ConnectedApps\Reference\ReferenceSearchAdapterInterface;
use App\ConnectedApps\Reference\ReferenceSearchService;
use App\ConnectedApps\Reference\Semantic\QueryExpansionService;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use App\Lookup\ExternalLookupService;
use App\Repository\ConnectedAppRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpClient\MockHttpClient;

final class ReferenceSearchServiceTest extends TestCase
{
    public function testAggregatesResultsAcrossAdaptersAndIsolatesFailures(): void
    {
        $service = $this->service(
            [$this->app(AppCode::JaxPhenome), $this->app(AppCode::Impc)],
            [
                $this->adapter(AppCode::JaxPhenome, [$this->makeResult('jax_phenome', 'measure'), $this->makeResult('jax_phenome', 'strain')]),
                $this->throwingAdapter(AppCode::Impc),
            ],
        );

        $results = $service->search('blood', [], 20);

        // JAX results kept; the IMPC adapter throwing must not break the search.
        $this->assertCount(2, $results);
        $this->assertSame('jax_phenome', $results[0]->source);
    }

    public function testRestrictsToRequestedAppCodes(): void
    {
        $jaxAdapter = $this->adapter(AppCode::JaxPhenome, [$this->makeResult('jax_phenome', 'measure')]);
        $impcAdapter = $this->adapter(AppCode::Impc, [$this->makeResult('impc', 'procedure')]);

        $service = $this->service(
            [$this->app(AppCode::JaxPhenome), $this->app(AppCode::Impc)],
            [$jaxAdapter, $impcAdapter],
        );

        $results = $service->search('blood', ['impc'], 20);

        $this->assertCount(1, $results);
        $this->assertSame('impc', $results[0]->source);
    }

    public function testReturnsEmptyForBlankQuery(): void
    {
        $service = $this->service([$this->app(AppCode::JaxPhenome)], [$this->adapter(AppCode::JaxPhenome, [$this->makeResult('jax_phenome', 'measure')])]);

        $this->assertSame([], $service->search('   ', [], 20));
    }

    public function testPublicAppsAreSearchedWithoutAnActiveConnectedApp(): void
    {
        // The account has no connected apps at all, yet the public NIH CDE
        // database is still searched via a default transient connection.
        $service = $this->service([], [$this->adapter(AppCode::NihCde, [$this->makeResult('nih_cde', 'cde_element')])]);

        $results = $service->search('glucose', [], 20);

        $this->assertCount(1, $results);
        $this->assertSame('nih_cde', $results[0]->source);
    }

    /**
     * @param list<ConnectedApp>                    $apps
     * @param list<ReferenceSearchAdapterInterface> $adapters
     */
    private function service(array $apps, array $adapters): ReferenceSearchService
    {
        $repo = $this->createStub(ConnectedAppRepository::class);
        $repo->method('findAllActiveForUser')->willReturn($apps);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($this->createStub(User::class));

        // Semantic query expansion is OFF by default, so this collaborator is never
        // invoked here; a real instance keeps the constructor happy without a mock.
        $queryExpansion = new QueryExpansionService(new ExternalLookupService(new MockHttpClient()), new NullLogger());

        return new ReferenceSearchService($repo, $security, new NullLogger(), $adapters, $queryExpansion);
    }

    private function app(AppCode $code): ConnectedApp
    {
        $app = $this->createStub(ConnectedApp::class);
        $app->method('getCode')->willReturn($code);
        $app->method('getName')->willReturn($code->value);

        return $app;
    }

    /**
     * @param list<ReferenceResult> $results
     */
    private function adapter(AppCode $code, array $results): ReferenceSearchAdapterInterface
    {
        $adapter = $this->createStub(ReferenceSearchAdapterInterface::class);
        $adapter->method('supports')->willReturnCallback(static fn (AppCode $c): bool => $c === $code);
        $adapter->method('search')->willReturn($results);

        return $adapter;
    }

    private function throwingAdapter(AppCode $code): ReferenceSearchAdapterInterface
    {
        $adapter = $this->createStub(ReferenceSearchAdapterInterface::class);
        $adapter->method('supports')->willReturnCallback(static fn (AppCode $c): bool => $c === $code);
        $adapter->method('search')->willThrowException(new \RuntimeException('upstream down'));

        return $adapter;
    }

    private function makeResult(string $source, string $type): ReferenceResult
    {
        return new ReferenceResult(
            id: $source . ':' . $type . ':1',
            source: $source,
            sourceName: $source,
            type: $type,
            title: 'Example',
        );
    }
}
