<?php

declare(strict_types=1);

namespace App\Zefix\Mortality;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Zefix\Mortality\State\ZefixMortalityTrendProvider;

#[ApiResource(
    shortName: 'ZefixMortalityTrend',
    formats: ['json' => ['application/json']],
    operations: [
        new GetCollection(
            uriTemplate: '/zefix/mortality-trends',
            provider: ZefixMortalityTrendProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixMortalityTrendPoint
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public string $date,
        public int $mortalities,
    ) {
    }
}
