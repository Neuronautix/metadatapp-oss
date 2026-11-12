<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Fair3r\Resources;

use App\ConnectedApps\Apps\Fair3r\Client\Dto\Fair3rResponseDto;
use App\ConnectedApps\Apps\Fair3r\Client\Fair3rHttpClientInterface;
use App\ConnectedApps\Apps\Fair3r\Client\Resources\Datastores;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class DatastoresUpsertDeleteTest extends TestCase
{
    #[Test]
    public function deletesByDateBeforeUpsert(): void
    {
        $fake = new class implements Fair3rHttpClientInterface {
            public array $calls = [];

            public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): Fair3rResponseDto
            {
                $this->calls[] = [
                    'method' => $method,
                    'uri' => $uri,
                    'options' => $options,
                ];

                if (str_ends_with($uri, '/action/datastore_delete')) {
                    return new Fair3rResponseDto(help: null, success: true, result: ['records_deleted' => true]);
                }

                if (str_ends_with($uri, '/action/datastore_upsert')) {
                    $records = $options['json']['records'] ?? [];

                    return new Fair3rResponseDto(help: null, success: true, result: [
                        'resource_id' => 'd6a08190-3058-4ec7-8bd0-d0e93975d458',
                        'records' => $records,
                    ]);
                }

                return new Fair3rResponseDto(help: null, success: true, result: []);
            }

            public function denormalizeResponse(array $content): Fair3rResponseDto
            {
                return new Fair3rResponseDto(help: $content['help'] ?? null, success: (bool) ($content['success'] ?? false), result: $content['result'] ?? []);
            }
        };

        $datastores = new Datastores($fake);

        $records = [
            ['subject' => 's1', 'weight' => 25.5, 'date' => '2024-10-01'],
            ['subject' => 's2', 'weight' => 24.5, 'date' => '2024-10-01'],
            ['subject' => 's3', 'weight' => 23.5, 'date' => '2024-10-02'],
        ];

        $datastores->upsert('d6a08190-3058-4ec7-8bd0-d0e93975d458', $records);

        // Expect two delete calls (for two unique dates), then one upsert call
        $this->assertGreaterThanOrEqual(3, \count($fake->calls));

        // First two calls should be datastore_delete with proper filters
        $this->assertStringEndsWith('/action/datastore_delete', $fake->calls[0]['uri']);
        $this->assertStringEndsWith('/action/datastore_delete', $fake->calls[1]['uri']);

        $datesSeen = [
            $fake->calls[0]['options']['json']['filters']['date'] ?? null,
            $fake->calls[1]['options']['json']['filters']['date'] ?? null,
        ];
        sort($datesSeen);
        $this->assertSame(['2024-10-01T00:00:00', '2024-10-02T00:00:00'], $datesSeen);

        // Last call should be the upsert
        $last = end($fake->calls);
        $this->assertStringEndsWith('/action/datastore_upsert', $last['uri']);
        $this->assertSame($records, $last['options']['json']['records']);
    }

    #[Test]
    public function overrideDatesAreUsedInsteadOfAutoDetection(): void
    {
        $fake = new class implements Fair3rHttpClientInterface {
            public array $calls = [];

            public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): Fair3rResponseDto
            {
                $this->calls[] = [
                    'method' => $method,
                    'uri' => $uri,
                    'options' => $options,
                ];

                if (str_ends_with($uri, '/action/datastore_delete')) {
                    return new Fair3rResponseDto(help: null, success: true, result: ['records_deleted' => true]);
                }

                if (str_ends_with($uri, '/action/datastore_upsert')) {
                    return new Fair3rResponseDto(help: null, success: true, result: [
                        'resource_id' => 'resource-override',
                        'records' => $options['json']['records'] ?? [],
                    ]);
                }

                return new Fair3rResponseDto(help: null, success: true, result: []);
            }

            public function denormalizeResponse(array $content): Fair3rResponseDto
            {
                return new Fair3rResponseDto(help: $content['help'] ?? null, success: (bool) ($content['success'] ?? false), result: $content['result'] ?? []);
            }
        };

        $datastores = new Datastores($fake);

        // Records contain other dates, but we override with a single explicit date
        $records = [
            ['subject' => 's1', 'weight' => 20.0, 'date' => '2024-01-01'],
            ['subject' => 's2', 'weight' => 21.0, 'date' => '2024-01-02'],
        ];

        $datastores->upsert('resource-override', $records, '2025-07-05');

        // Expect one delete call for the override date only
        $this->assertGreaterThanOrEqual(2, \count($fake->calls));
        $this->assertStringEndsWith('/action/datastore_delete', $fake->calls[0]['uri']);
        $this->assertSame('2025-07-05T00:00:00', $fake->calls[0]['options']['json']['filters']['date'] ?? null);
        $this->assertStringEndsWith('/action/datastore_upsert', $fake->calls[1]['uri']);
    }
}
