<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Cage;
use App\Enum\BeddingType;
use App\Enum\CageFormat;
use App\Enum\CageType;
use App\Enum\EnrichmentType;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Cage>
 */
final class CageFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'type' => self::faker()->randomElement(CageType::cases()),
            'format' => self::faker()->randomElement(CageFormat::cases()),
            'enrichmentType' => self::faker()->optional()->randomElement(EnrichmentType::cases()),
            'hasEnrichments' => self::faker()->boolean(50),
            'water' => true,
            'food' => true,
            'beddingType' => self::faker()->randomElement(BeddingType::cases()),
            'environmentHousing' => EnvironmentHousingFactory::randomOrCreate(),
            'animalFacility' => AnimalFacilityFactory::randomOrCreate(),
            'homeCageMonitoring' => HomeCageMonitoringFactory::randomOrCreate(),
            //            'subjects'           => SubjectFactory::randomRange(1, 5),
            'account' => AccountFactory::randomOrCreate(),
            'externalId' => self::faker()->optional()->uuid(),
            'elaftwExternalId' => self::faker()->optional()->uuid(),
            'fair3rExternalId' => self::faker()->optional()->uuid(),
        ];
    }

    public static function class(): string
    {
        return Cage::class;
    }
}
