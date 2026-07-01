<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\Elabftw\Client\ElabftwHttpClientInterface;
use App\ConnectedApps\Reference\Adapter\ElabftwTemplateReferenceAdapter;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use PHPUnit\Framework\TestCase;

final class ElabftwTemplateReferenceAdapterTest extends TestCase
{
    public function testSupportsOnlyElabftw(): void
    {
        $adapter = new ElabftwTemplateReferenceAdapter($this->createStub(ElabftwHttpClientInterface::class));

        $this->assertTrue($adapter->supports(AppCode::Elabftw));
        $this->assertFalse($adapter->supports(AppCode::Cedar));
    }

    public function testSearchFiltersByQueryAndMapsResults(): void
    {
        $client = $this->createMock(ElabftwHttpClientInterface::class);
        $client->method('listExperimentsTemplates')->willReturn([
            ['id' => 1, 'title' => 'Open Field Test', 'body' => '<p>Description</p>'],
            ['id' => 2, 'title' => 'Morris Water Maze', 'body' => ''],
        ]);
        $client->method('listItemsTypes')->willReturn([
            ['id' => 10, 'title' => 'Open Field Rig', 'body' => null],
        ]);

        $adapter = new ElabftwTemplateReferenceAdapter($client);
        $results = $adapter->search($this->makeApp(), 'open field', 20);

        $this->assertCount(2, $results);
        $this->assertSame('elabftw:template:1', $results[0]->id);
        $this->assertSame('template', $results[0]->type);
        $this->assertSame('Open Field Test', $results[0]->title);
        $this->assertSame('Experiment template', $results[0]->subtitle);
        $this->assertSame('elabftw:items_type:10', $results[1]->id);
        $this->assertSame('Item type', $results[1]->subtitle);
    }

    public function testSearchRespectsLimit(): void
    {
        $templates = array_map(
            static fn (int $i): array => ['id' => $i, 'title' => 'Template ' . $i, 'body' => ''],
            range(1, 10),
        );

        $client = $this->createMock(ElabftwHttpClientInterface::class);
        $client->method('listExperimentsTemplates')->willReturn($templates);
        $client->method('listItemsTypes')->willReturn([]);

        $adapter = new ElabftwTemplateReferenceAdapter($client);
        $results = $adapter->search($this->makeApp(), '', 3);

        $this->assertCount(3, $results);
    }

    public function testSearchReturnsEmptyWhenNoMatch(): void
    {
        $client = $this->createMock(ElabftwHttpClientInterface::class);
        $client->method('listExperimentsTemplates')->willReturn([
            ['id' => 1, 'title' => 'Morris Water Maze', 'body' => ''],
        ]);
        $client->method('listItemsTypes')->willReturn([]);

        $adapter = new ElabftwTemplateReferenceAdapter($client);
        $results = $adapter->search($this->makeApp(), 'open field', 20);

        $this->assertSame([], $results);
    }

    private function makeApp(): ConnectedApp
    {
        $app = $this->createStub(ConnectedApp::class);
        $app->method('getName')->willReturn('eLabFTW');

        return $app;
    }
}
