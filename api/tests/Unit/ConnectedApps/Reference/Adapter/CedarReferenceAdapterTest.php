<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\Cedar\Client\CedarClientInterface;
use App\ConnectedApps\Reference\Adapter\CedarReferenceAdapter;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use PHPUnit\Framework\TestCase;

final class CedarReferenceAdapterTest extends TestCase
{
    public function testSupportsOnlyCedar(): void
    {
        $adapter = new CedarReferenceAdapter($this->createStub(CedarClientInterface::class));

        $this->assertTrue($adapter->supports(AppCode::Cedar));
        $this->assertFalse($adapter->supports(AppCode::NihCde));
    }

    public function testSearchMapsResourcesIntoResults(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->method('searchArtifacts')->willReturn([
            'totalCount' => 1,
            'resources' => [
                [
                    '@id' => 'https://repo.metadatacenter.org/templates/abc-123',
                    'schema:name' => 'Mouse Phenotyping Template',
                    'resourceType' => 'Template',
                    'pav:version' => '1.0.0',
                    'bibo:status' => 'bibo:published',
                ],
            ],
        ]);

        $connectedApp = $this->makeApp();
        $adapter = new CedarReferenceAdapter($client);
        $results = $adapter->search($connectedApp, 'phenotyping', 20);

        $this->assertCount(1, $results);
        $this->assertSame('cedar:schema:abc-123', $results[0]->id);
        $this->assertSame('schema', $results[0]->type);
        $this->assertSame('Mouse Phenotyping Template', $results[0]->title);
        $this->assertSame('cedar', $results[0]->source);
    }

    public function testSearchSkipsResourcesWithNoIri(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->method('searchArtifacts')->willReturn([
            'totalCount' => 1,
            'resources' => [
                ['schema:name' => 'No IRI resource'],
            ],
        ]);

        $adapter = new CedarReferenceAdapter($client);
        $results = $adapter->search($this->makeApp(), 'test', 20);

        $this->assertCount(0, $results);
    }

    public function testSearchReturnsEmptyWhenNoResources(): void
    {
        $client = $this->createMock(CedarClientInterface::class);
        $client->method('searchArtifacts')->willReturn(['totalCount' => 0, 'resources' => []]);

        $adapter = new CedarReferenceAdapter($client);
        $results = $adapter->search($this->makeApp(), 'nothing', 20);

        $this->assertSame([], $results);
    }

    private function makeApp(): ConnectedApp
    {
        $app = $this->createStub(ConnectedApp::class);
        $app->method('getName')->willReturn('CEDAR');

        return $app;
    }
}
