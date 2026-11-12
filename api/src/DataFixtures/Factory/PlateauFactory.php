<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Plateau;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Plateau>
 */
final class PlateauFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    private const DEFAULT_PLATEAUX = [
        ['name' => 'North Aquatics Platform', 'code' => 'ZF-NORTH'],
        ['name' => 'South Aquatics Platform', 'code' => 'ZF-SOUTH'],
        ['name' => 'Imaging Aquatics Platform', 'code' => 'ZF-IMG'],
        ['name' => 'Quarantine Aquatics Platform', 'code' => 'ZF-QUAR'],
    ];

    private static int $sequence = 0;

    protected function defaults(): array
    {
        $defaults = self::DEFAULT_PLATEAUX[self::$sequence % \count(self::DEFAULT_PLATEAUX)];
        ++self::$sequence;

        return [
            'name' => $defaults['name'],
            'code' => $defaults['code'],
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return Plateau::class;
    }
}
