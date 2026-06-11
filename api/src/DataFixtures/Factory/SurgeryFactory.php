<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Surgery;
use App\Enum\InvasivenessType;
use App\Enum\ProcedureType;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Surgery>
 */
final class SurgeryFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $start = self::faker()->dateTimeBetween('-1 year', 'now');
        $end = self::faker()->dateTimeBetween($start, 'now');

        return [
            'procedureType' => ProcedureType::SURGERY,
            'user' => UserFactory::randomOrCreate(),
            'account' => AccountFactory::randomOrCreate(),

            'invasivenessType' => self::faker()->randomElement(InvasivenessType::cases()),
            'experiment' => ExperimentFactory::randomOrCreate(),
            'startAt' => $start,
            'endAt' => $end,
            'duration' => $end ? $end->getTimestamp() - $start->getTimestamp() : null,
            'name' => 'Surgery: ' . self::faker()->word(),
            'protocol' => self::faker()->optional()->paragraph(),
            'protocolUri' => self::faker()->optional()->url(),
            'state' => self::faker()->randomElement(['draft', 'planned', 'in_progress', 'completed', 'cancelled']),
            'surgeryName' => 'Surg' . self::faker()->word() . 'erificator',
            //            'user' => UserFactory::randomOrCreate()->object(),
        ];
    }

    public static function class(): string
    {
        return Surgery::class;
    }
}
