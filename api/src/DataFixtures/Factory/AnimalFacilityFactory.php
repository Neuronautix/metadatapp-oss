<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\AnimalFacility;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\AnimalFacility>
 */
final class AnimalFacilityFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        // Example sanitary statuses. Adjust as needed:
        $possibleStatuses = ['SPF', 'Conventional', 'Germ-free', 'Barrier', 'Unknown'];

        return [
            'sanitaryStatus' => self::faker()->randomElement($possibleStatuses),
            'name' => 'Facility ' . self::faker()->unique()->word(),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return AnimalFacility::class;
    }
}
