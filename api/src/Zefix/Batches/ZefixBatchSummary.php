<?php

declare(strict_types=1);

namespace App\Zefix\Batches;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Zefix\Batches\State\ZefixBatchProvider;

#[ApiResource(
    shortName: 'ZefixBatch',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/zefix/batches',
            provider: ZefixBatchProvider::class,
            itemUriTemplate: '/zefix/batches/{batchID}',
        ),
        new Get(
            uriTemplate: '/zefix/batches/{batchID}',
            provider: ZefixBatchProvider::class,
            output: ZefixBatchDetail::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixBatchSummary implements ZefixBatchView
{
    /**
     * @param list<array<string, mixed>> $events
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $batchID,
        public string $lineID,
        public string $birthDate,
        public ZefixBatchSexRatio $sexRatio,
        public int $initialCount,
        public int $individualCount,
        public string $locationId,
        public string $systemID,
        public string $roomID,
        public array $events,
        public string $lineName,
        public string $roomName,
        public string $tankLocation,
        public int $ageDays,
        public string $ageLabel,
        public int $mortalityCount,
    ) {
    }
}
