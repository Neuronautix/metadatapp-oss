<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public static function createOneAdmin(array $attributes = []): User
    {
        return self::createOne(['roles' => ['ROLE_ADMIN']] + $attributes);
    }

    public static function createOneSuperAdmin(array $attributes = []): User
    {
        return self::createOne(['roles' => ['ROLE_SUPER_ADMIN']] + $attributes);
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->email(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'roles' => ['ROLE_USER'],
            'orcid' => self::faker()->optional(0.3)->regexify('\d{4}-\d{4}-\d{4}-\d{3}[0-9X]'),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return User::class;
    }
}
