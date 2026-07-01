<?php

declare(strict_types=1);

namespace App\Zefix\Overview;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Zefix\Overview\State\ZefixOverviewProvider;

#[ApiResource(
    shortName: 'ZefixOverview',
    formats: ['json' => ['application/json']],
    operations: [
        new Get(
            uriTemplate: '/zefix/overview',
            provider: ZefixOverviewProvider::class,
            output: self::class,
        ),
    ],
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
)]
final readonly class ZefixOverview
{
    /**
     * @param list<array{line: string, fish: int}>               $populationByLine
     * @param list<array{date: string, mortalities: int}>        $mortalityTrend
     * @param list<array{timestamp: string, temperature: float}> $temperatureTrend
     * @param list<array{period: string, births: int}>           $breedingOutput
     */
    public function __construct(
        public int $totalLines,
        public int $totalBatches,
        public int $totalFish,
        public int $todaysMortalities,
        public int $activeAlerts,
        public array $populationByLine,
        public array $mortalityTrend,
        public array $temperatureTrend,
        public array $breedingOutput,
    ) {
    }
}
