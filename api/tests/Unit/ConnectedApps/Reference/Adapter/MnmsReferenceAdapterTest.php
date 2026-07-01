<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Reference\Adapter;

use App\ConnectedApps\Apps\Mnms\Client\MnmsClientInterface;
use App\ConnectedApps\Reference\Adapter\MnmsReferenceAdapter;
use App\Entity\ConnectedApp;
use App\Enum\AppCode;
use PHPUnit\Framework\TestCase;

final class MnmsReferenceAdapterTest extends TestCase
{
    public function testSupportsOnlyMnmsCode(): void
    {
        $adapter = new MnmsReferenceAdapter($this->createStub(MnmsClientInterface::class));

        $this->assertTrue($adapter->supports(AppCode::Mnms));
        $this->assertFalse($adapter->supports(AppCode::NihCde));
    }

    public function testEmptyQueryReturnsAllMockedFields(): void
    {
        $fields = [
            ['name' => 'RepetitionTime', 'description' => 'TR description.', 'type' => 'number', 'unit' => 's'],
            ['name' => 'EchoTime', 'description' => 'TE description.', 'type' => 'number', 'unit' => 's'],
        ];

        $client = $this->createMock(MnmsClientInterface::class);
        $client->method('searchFields')->willReturn($fields);

        $adapter = new MnmsReferenceAdapter($client);
        $results = $adapter->search($this->connectedApp(), '', 20);

        $this->assertCount(2, $results);
    }

    public function testQueryFiltersByTitleCaseInsensitive(): void
    {
        $fields = [
            ['name' => 'RepetitionTime', 'description' => 'TR description.', 'type' => 'number', 'unit' => 's'],
        ];

        $client = $this->createMock(MnmsClientInterface::class);
        $client->method('searchFields')->with(
            $this->anything(),
            'repetitiontime',
            $this->anything()
        )->willReturn($fields);

        $adapter = new MnmsReferenceAdapter($client);
        $results = $adapter->search($this->connectedApp(), 'repetitiontime', 20);

        $this->assertCount(1, $results);
        $this->assertSame('RepetitionTime', $results[0]->title);
    }

    public function testResultTypeIsSchema(): void
    {
        $fields = [
            ['name' => 'FlipAngle', 'description' => 'Flip angle.', 'type' => 'number', 'unit' => 'degrees'],
        ];

        $client = $this->createMock(MnmsClientInterface::class);
        $client->method('searchFields')->willReturn($fields);

        $adapter = new MnmsReferenceAdapter($client);
        $results = $adapter->search($this->connectedApp(), '', 20);

        $this->assertSame('schema', $results[0]->type);
    }

    public function testResultIdStartsWithMnmsSchema(): void
    {
        $fields = [
            ['name' => 'FlipAngle', 'description' => 'Flip angle.', 'type' => 'number', 'unit' => 'degrees'],
        ];

        $client = $this->createMock(MnmsClientInterface::class);
        $client->method('searchFields')->willReturn($fields);

        $adapter = new MnmsReferenceAdapter($client);
        $results = $adapter->search($this->connectedApp(), '', 20);

        $this->assertStringStartsWith('mnms:schema:', $results[0]->id);
    }

    public function testNullUnitProducesNullSubtitle(): void
    {
        $fields = [
            ['name' => 'Manufacturer', 'description' => 'Manufacturer name.', 'type' => 'string', 'unit' => null],
        ];

        $client = $this->createMock(MnmsClientInterface::class);
        $client->method('searchFields')->willReturn($fields);

        $adapter = new MnmsReferenceAdapter($client);
        $results = $adapter->search($this->connectedApp(), '', 20);

        $this->assertNull($results[0]->subtitle);
    }

    private function connectedApp(): ConnectedApp
    {
        $app = $this->createStub(ConnectedApp::class);
        $app->method('getName')->willReturn('MNMS');
        $app->method('getCode')->willReturn(AppCode::Mnms);

        return $app;
    }
}
