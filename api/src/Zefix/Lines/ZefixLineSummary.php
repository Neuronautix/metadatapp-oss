<?php

declare(strict_types=1);

namespace App\Zefix\Lines;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use App\Zefix\Lines\State\ZefixLineProvider;

#[ApiResource(
    shortName: 'ZefixLine',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/zefix/lines',
            provider: ZefixLineProvider::class,
            itemUriTemplate: '/zefix/lines/{lineID}',
        ),
        new Get(
            uriTemplate: '/zefix/lines/{lineID}',
            provider: ZefixLineProvider::class,
            output: ZefixLineDetail::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixLineSummary implements ZefixLineView
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $lineID,
        public string $name,
        public string $genotype,
        public string $origin,
        public string $description,
        public string $creationDate,
        public string $responsibleScientist,
        public int $totalAnimals,
        public int $activeBatches,
        public float $mortalityRate,
        public string $reproductionStatus,
    ) {
    }
}
