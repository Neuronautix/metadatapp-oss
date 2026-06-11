<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Account;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Account>
 */
final class AccountFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->company() . ' Institute',
            'externalKey' => self::faker()->unique()->slug(2),
            'featurePreset' => null,
        ];
    }

    public static function class(): string
    {
        return Account::class;
    }
}
