<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Laboratory;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Laboratory>
 */
final class LaboratoryFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->company() . ' Lab',
            'account' => AccountFactory::randomOrCreate(),
            //            'projects' => ProjectFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return Laboratory::class;
    }
}
