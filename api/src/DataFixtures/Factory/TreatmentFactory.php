<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Treatment;
use App\Enum\ProcedureType;
use App\Enum\TreatmentCategory;
use App\Enum\TreatmentRoute;
use App\Enum\TreatmentType;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Treatment>
 */
final class TreatmentFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $start = self::faker()->dateTimeBetween('-1 year', '-1 day');
        $end = self::faker()->dateTimeBetween($start, 'now');

        return [
            'procedureType' => ProcedureType::TREATMENT,
            'experiment' => ExperimentFactory::randomOrCreate(),
            'startAt' => $start,
            'endAt' => $end,
            'duration' => $end ? $end->getTimestamp() - $start->getTimestamp() : null,
            'name' => 'Treatment: ' . self::faker()->word(),
            'protocol' => self::faker()->optional()->paragraph(),
            'protocolUri' => self::faker()->optional()->url(),
            'state' => self::faker()->randomElement(['draft', 'planned', 'in_progress', 'completed', 'cancelled']),
            'account' => AccountFactory::randomOrCreate(),
            'user' => UserFactory::randomOrCreate(),
            // Treatment-specific fields
            'treatmentType' => self::faker()->randomElement(TreatmentType::cases()),
            'category' => self::faker()->randomElement(TreatmentCategory::cases()),
            'drug' => self::faker()->word(),
            'dose' => self::faker()->randomFloat(2, 0.1, 500), // example dose range
            'doseUnit' => self::faker()->randomElement(['mg/kg', 'µg/ml', 'IU']), // Example units
            'route' => self::faker()->randomElement(TreatmentRoute::cases()),
        ];
    }

    public static function class(): string
    {
        return Treatment::class;
    }
}
