<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Project;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Project>
 */
final class ProjectFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'name' => 'Project ' . self::faker()->word(),
            'goal' => self::faker()->sentence(2),
            'startAt' => self::faker()->dateTimeBetween('-1 year', 'now'),
            'endAt' => self::faker()->optional()->dateTimeBetween('now', '+1 year'),
            'description' => self::faker()->paragraph(),
            'user' => UserFactory::randomOrCreate(),
            'account' => AccountFactory::randomOrCreate(),
            'repositoryLink' => self::faker()->optional()->url(),
            //            'laboratories'   => LaboratoryFactory::randomOrCreate(),
            //            'experiments'    => ExperimentFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return Project::class;
    }
}
