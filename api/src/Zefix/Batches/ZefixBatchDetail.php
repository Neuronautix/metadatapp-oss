<?php

declare(strict_types=1);

namespace App\Zefix\Batches;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Zefix\Batches\State\ZefixBatchProvider;

#[ApiResource(
    shortName: 'ZefixBatchDetail',
    formats: ['json' => ['application/json']],
    operations: [
        new Get(
            uriTemplate: '/zefix/batches/{batchID}',
            provider: ZefixBatchProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixBatchDetail implements ZefixBatchView
{
    /**
     * @param list<ZefixBatchTimelinePoint>  $timeline
     * @param list<ZefixBatchMortalityEvent> $mortalityEvents
     */
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $batchID,
        #[ApiProperty(readableLink: true, writableLink: false)]
        public ZefixBatchSummary $batch,
        public ZefixBatchLine $line,
        public ZefixBatchLocation $location,
        public array $timeline,
        public array $mortalityEvents,
    ) {
    }
}
