<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\ConnectedApp;
use App\Enum\AppCode;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\ConnectedApp>
 */
final class ConnectedAppFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array
    {
        return [
            'code' => self::faker()->randomElement(AppCode::cases()),
            'name' => self::faker()->company() . ' App',
            'description' => self::faker()->optional()->sentence(),
            'user' => UserFactory::randomOrCreate(),
            'isActive' => self::faker()->boolean(80),
            'token' => self::faker()->sha256(),
            'lastSyncAt' => self::faker()->optional()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
        // ->afterInstantiate(function(ConnectedApp $connectedApp): void {})
    }

    public static function class(): string
    {
        return ConnectedApp::class;
    }
}
