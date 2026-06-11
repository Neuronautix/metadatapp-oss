<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Resources;

use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreCreateRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreCreateResponseDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreDumpResponseDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreUpsertRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreUpsertResponseDto;
use App\ConnectedApps\Apps\Fair3r\Client\Fair3rHttpClientInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Datastores
{
    public function __construct(private readonly Fair3rHttpClientInterface $httpClient)
    {
    }

    public function create(DatastoreCreateRequestDto|array $request): DatastoreCreateResponseDto
    {
        try {
            $options = ['json' => $request];
            $response = $this->httpClient->sendRequest('POST', '/api/3/action/datastore_create', $options);
        } catch (\Throwable $t) {
            throw $t; // improve error handling
        }

        // https://chatgpt.com/g/g-p-676212f8d8848191aba02f03781b8dab-mapp/c/683b2d03-dd94-8006-8182-8583d65dd428
        $normalizer = new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter());
        $serializer = new Serializer([$normalizer], [new JsonEncoder()]);

        try {
            /** @var DatastoreCreateResponseDto $datastore */
            $datastore = $serializer->denormalize($response->result, DatastoreCreateResponseDto::class);
        } catch (\Throwable $e) {
            throw $e; // improve error handling
        }

        return $datastore;
    }

    /**
     * Upsert records.
     *
     * Accepts either a fully-formed DTO or a shorthand: resource_id + records.
     *
     * @param array<array<string, mixed>>|null $records
     * @param string|array<string>|null        $deleteDatesOverride dates to delete before upsert; overrides auto-detection from records
     */
    public function upsert(DatastoreUpsertRequestDto|string $dtoOrResourceId, ?array $records = null, string|array|null $deleteDatesOverride = null): DatastoreUpsertResponseDto
    {
        $payload = match (true) {
            $dtoOrResourceId instanceof DatastoreUpsertRequestDto => $dtoOrResourceId,
            null !== $records => [
                'resource_id' => $dtoOrResourceId,
                'records' => $records,
                'method' => 'insert',
            ],
            default => throw new \InvalidArgumentException('Invalid upsert arguments'),
        };

        // Pre-clean existing rows for the dates we are about to upsert
        // by issuing datastore_delete with a date filter, one per unique date.
        try {
            // Determine resource_id and records from either DTO or guaranteed array payload
            $resourceId = $payload instanceof DatastoreUpsertRequestDto ? $payload->resource_id : $payload['resource_id'];
            $recordsList = $payload instanceof DatastoreUpsertRequestDto ? ($payload->records ?? []) : $payload['records'];

            if ($resourceId && [] !== $recordsList) {
                $dates = [];
                if (null !== $deleteDatesOverride) {
                    $overrideDates = \is_array($deleteDatesOverride) ? $deleteDatesOverride : [$deleteDatesOverride];
                    foreach ($overrideDates as $dateValue) {
                        if ('' === $dateValue) {
                            continue;
                        }
                        $normalized = str_contains($dateValue, 'T') ? $dateValue : ($dateValue . 'T00:00:00');
                        $dates[$normalized] = true;
                    }
                } else {
                    foreach ($recordsList as $rec) {
                        if (isset($rec['date']) && \is_string($rec['date']) && '' !== $rec['date']) {
                            $dateValue = $rec['date'];
                            // Normalize to CKAN timestamp format if only a date is provided
                            $normalized = str_contains($dateValue, 'T') ? $dateValue : ($dateValue . 'T00:00:00');
                            $dates[$normalized] = true; // use associative array as a set
                        }
                    }
                }

                foreach (array_keys($dates) as $date) {
                    $this->delete($resourceId, ['date' => $date]);
                }
            }
        } catch (\Throwable $t) {
            // If cleanup fails, propagate for now; could be configurable later
            throw $t;
        }

        $response = $this->httpClient->sendRequest('POST', '/api/3/action/datastore_upsert', [
            'json' => $payload,
        ]);

        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        try {
            /** @var DatastoreUpsertResponseDto $datastore
             */
            $datastore = $serializer->denormalize($response->result, DatastoreUpsertResponseDto::class);
        } catch (\Throwable $e) {
            throw $e; // improve error handling
        }

        return $datastore;
    }

    /**
     * Dump datastore contents for a resource.
     */
    public function dump(string $resourceId): DatastoreDumpResponseDto
    {
        $response = $this->httpClient->sendRequest('GET', '/datastore/dump/' . $resourceId);

        $serializer = new Serializer([
            new ObjectNormalizer(),
            new ArrayDenormalizer(),
        ], [new JsonEncoder()]);

        return $serializer->denormalize($response->result, DatastoreDumpResponseDto::class);
    }

    /**
     * Delete records from a datastore using filters.
     * Example: delete('resource-id', ['date' => '2025-07-05T00:00:00']).
     */
    public function delete(string $resourceId, array $filters = []): void
    {
        $this->httpClient->sendRequest('POST', '/api/3/action/datastore_delete', [
            'json' => [
                'resource_id' => $resourceId,
                'filters' => $filters,
            ],
        ]);
    }
}
