<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Experiment;
use App\Enum\ExperimentType;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Experiment>
 */
final class ExperimentFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $start = self::faker()->dateTimeBetween('-6 months', '-1 month');

        return [
            'name' => self::faker()->word(),
            'goal' => self::faker()->optional()->sentence(),
            'type' => self::faker()->randomElement(ExperimentType::cases()),
            'protocol' => self::faker()->optional()->url(),
            'startAt' => $start,
            'endAt' => self::faker()->optional()->dateTimeBetween($start, '+3 months'),
            'description' => self::faker()->paragraph(),
            'project' => ProjectFactory::randomOrCreate(),
            'repositoryLink' => self::faker()->optional()->url(),
            'user' => UserFactory::randomOrCreate(),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return Experiment::class;
    }
}
