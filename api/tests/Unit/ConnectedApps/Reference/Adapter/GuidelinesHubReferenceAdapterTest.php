<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\GuidelinesHub\GuidelinesHubDataProvider;
use App\ConnectedApps\Reference\Adapter\GuidelinesHubReferenceAdapter;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use PHPUnit\Framework\TestCase;

final class GuidelinesHubReferenceAdapterTest extends TestCase
{
    private GuidelinesHubReferenceAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new GuidelinesHubReferenceAdapter(new GuidelinesHubDataProvider());
    }

    public function testSupportsOnlyGuidelinesHubCode(): void
    {
        $this->assertTrue($this->adapter->supports(AppCode::GuidelinesHub));
        $this->assertFalse($this->adapter->supports(AppCode::NihCde));
    }

    public function testSearchRandomisationReturnsArriveAndEqipdItems(): void
    {
        $results = $this->adapter->search($this->connectedApp(), 'randomisation', 50);

        $this->assertNotEmpty($results);

        $sources = array_unique(array_map(static fn ($r) => $r->identifiers['source'], $results));
        $this->assertContains('arrive', $sources);
        $this->assertContains('eqipd', $sources);
    }

    public function testResultTypeIsGuideline(): void
    {
        $results = $this->adapter->search($this->connectedApp(), 'study design', 10);

        $this->assertNotEmpty($results);
        foreach ($results as $result) {
            $this->assertSame('guideline', $result->type);
        }
    }

    public function testResultSubtitleIsOneOfKnownLabels(): void
    {
        $results = $this->adapter->search($this->connectedApp(), '', 50);

        $this->assertNotEmpty($results);

        $validLabels = ['ARRIVE 2.0', 'PREPARE', 'EQIPD'];
        foreach ($results as $result) {
            $this->assertContains($result->subtitle, $validLabels);
        }
    }

    public function testEmptyQueryReturnsAllItems(): void
    {
        $results = $this->adapter->search($this->connectedApp(), '', 100);

        // 21 ARRIVE + 8 PREPARE + 8 EQIPD = 37 total
        $this->assertCount(37, $results);
    }

    public function testLimitIsRespected(): void
    {
        $results = $this->adapter->search($this->connectedApp(), '', 5);

        $this->assertCount(5, $results);
    }

    private function connectedApp(): ConnectedApp
    {
        $app = $this->createStub(ConnectedApp::class);
        $app->method('getName')->willReturn('Guidelines Hub');
        $app->method('getCode')->willReturn(AppCode::GuidelinesHub);

        return $app;
    }
}
