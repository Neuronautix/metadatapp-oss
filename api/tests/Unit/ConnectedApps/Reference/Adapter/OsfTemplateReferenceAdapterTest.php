<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\Osf\Client\OsfClient;
use App\ConnectedApps\Apps\Osf\Client\OsfClientInterface;
use App\ConnectedApps\Reference\Adapter\OsfTemplateReferenceAdapter;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class OsfTemplateReferenceAdapterTest extends TestCase
{
    public function testSupportsOnlyOsf(): void
    {
        $adapter = new OsfTemplateReferenceAdapter(new OsfClient(new MockHttpClient()));

        $this->assertTrue($adapter->supports(AppCode::Osf));
        $this->assertFalse($adapter->supports(AppCode::Cedar));
    }

    public function testSearchFiltersByQueryAndMapsResults(): void
    {
        $client = $this->createMock(OsfClientInterface::class);
        $client->method('listRegistrationSchemas')->willReturn([
            [
                'id' => 'schema-abc',
                'attributes' => [
                    'title' => 'Open Science Framework Preregistration',
                    'description' => 'Standard preregistration template',
                    'schema_version' => '2',
                    'active' => true,
                ],
            ],
            [
                'id' => 'schema-xyz',
                'attributes' => [
                    'title' => 'Qualitative Preregistration',
                    'description' => 'For qualitative studies',
                    'active' => true,
                ],
            ],
        ]);

        $adapter = new OsfTemplateReferenceAdapter($client);
        $results = $adapter->search($this->makeApp(), 'preregistration', 20);

        $this->assertCount(2, $results);
        $this->assertSame('osf:template:schema-abc', $results[0]->id);
        $this->assertSame('template', $results[0]->type);
        $this->assertSame('Open Science Framework Preregistration', $results[0]->title);
        $this->assertSame('v2', $results[0]->subtitle);
        $this->assertSame('osf', $results[0]->source);
    }

    public function testSearchRespectsLimit(): void
    {
        $schemas = array_map(
            static fn (int $i): array => [
                'id' => 'schema-' . $i,
                'attributes' => ['title' => 'Schema ' . $i, 'description' => 'desc'],
            ],
            range(1, 10),
        );

        $client = $this->createMock(OsfClientInterface::class);
        $client->method('listRegistrationSchemas')->willReturn($schemas);

        $adapter = new OsfTemplateReferenceAdapter($client);
        $results = $adapter->search($this->makeApp(), '', 4);

        $this->assertCount(4, $results);
    }

    public function testSearchReturnsEmptyWhenNoMatch(): void
    {
        $client = $this->createMock(OsfClientInterface::class);
        $client->method('listRegistrationSchemas')->willReturn([
            [
                'id' => 'schema-abc',
                'attributes' => ['title' => 'Qualitative Study', 'description' => 'For qualitative work'],
            ],
        ]);

        $adapter = new OsfTemplateReferenceAdapter($client);
        $results = $adapter->search($this->makeApp(), 'quantitative preregistration', 20);

        $this->assertSame([], $results);
    }

    private function makeApp(): ConnectedApp
    {
        $app = $this->createStub(ConnectedApp::class);
        $app->method('getName')->willReturn('OSF');

        return $app;
    }
}
