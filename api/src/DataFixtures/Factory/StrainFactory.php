<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Strain;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Strain>
 */
class StrainFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'name' => 'Strain ' . self::faker()->word(),
            'link' => self::faker()->optional()->url(),
        ];
    }

    public static function class(): string
    {
        return Strain::class;
    }
}
