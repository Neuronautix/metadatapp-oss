<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\BioPortal\Client\BioPortalClientInterface;
use App\ConnectedApps\Reference\Adapter\BioPortalReferenceAdapter;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use PHPUnit\Framework\TestCase;

final class BioPortalReferenceAdapterTest extends TestCase
{
    public function testSupportsOnlyBioPortalCode(): void
    {
        $adapter = new BioPortalReferenceAdapter($this->createStub(BioPortalClientInterface::class));

        $this->assertTrue($adapter->supports(AppCode::BioPortal));
        $this->assertFalse($adapter->supports(AppCode::NihCde));
    }

    public function testResultTypeIsOntologyClass(): void
    {
        $client = $this->createMock(BioPortalClientInterface::class);
        $client->method('searchOntologyTerms')->willReturn([
            'collection' => [$this->hitFixture()],
            'totalCount' => 1,
        ]);

        $adapter = new BioPortalReferenceAdapter($client);
        $results = $adapter->search($this->connectedApp(), 'housing', 10);

        $this->assertCount(1, $results);
        $this->assertSame('ontology_class', $results[0]->type);
    }

    public function testExternalUrlContainsBioPortalDomain(): void
    {
        $client = $this->createMock(BioPortalClientInterface::class);
        $client->method('searchOntologyTerms')->willReturn([
            'collection' => [$this->hitFixture()],
            'totalCount' => 1,
        ]);

        $adapter = new BioPortalReferenceAdapter($client);
        $results = $adapter->search($this->connectedApp(), 'housing', 10);

        $this->assertNotNull($results[0]->externalUrl);
        $this->assertStringContainsString('bioportal.bioontology.org', $results[0]->externalUrl);
    }

    public function testEmptyDefinitionArrayProducesNullDescription(): void
    {
        $hit = $this->hitFixture();
        $hit['definition'] = [];

        $client = $this->createMock(BioPortalClientInterface::class);
        $client->method('searchOntologyTerms')->willReturn([
            'collection' => [$hit],
            'totalCount' => 1,
        ]);

        $adapter = new BioPortalReferenceAdapter($client);
        $results = $adapter->search($this->connectedApp(), 'housing', 10);

        $this->assertNull($results[0]->description);
    }

    public function testHitWithoutIriIsSkipped(): void
    {
        $client = $this->createMock(BioPortalClientInterface::class);
        $client->method('searchOntologyTerms')->willReturn([
            'collection' => [['prefLabel' => 'No IRI']],
            'totalCount' => 1,
        ]);

        $adapter = new BioPortalReferenceAdapter($client);
        $results = $adapter->search($this->connectedApp(), 'housing', 10);

        $this->assertCount(0, $results);
    }

    /** @return array<string, mixed> */
    private function hitFixture(): array
    {
        return [
            '@id' => 'http://purl.obolibrary.org/obo/HCMO_0000001',
            'prefLabel' => 'Housing condition',
            'definition' => ['A condition describing the housing of laboratory animals.'],
            'links' => [
                'ontology' => 'https://data.bioontology.org/ontologies/HCMO',
            ],
        ];
    }

    private function connectedApp(): ConnectedApp
    {
        $app = $this->createStub(ConnectedApp::class);
        $app->method('getName')->willReturn('BioPortal');
        $app->method('getCode')->willReturn(AppCode::BioPortal);

        return $app;
    }
}
