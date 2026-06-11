<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Variable;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Variable>
 */
final class VariableFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        // Generate unique variable names
        return [
            'name' => 'Variable_' . self::faker()->unique()->word(),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return Variable::class;
    }
}
