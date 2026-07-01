<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Rack;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Rack>
 */
final class RackFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    private const DEFAULT_RACK_CODES = [
        'RACK-A1',
        'RACK-A2',
        'RACK-B1',
        'RACK-B2',
        'RACK-Q1',
        'RACK-Q2',
    ];

    private static int $sequence = 0;

    protected function defaults(): array
    {
        $code = self::DEFAULT_RACK_CODES[self::$sequence % \count(self::DEFAULT_RACK_CODES)];
        ++self::$sequence;

        return [
            'code' => $code,
            'room' => RoomFactory::new(),
            'account' => AccountFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Rack $rack): void {
            $rack->setAccount($rack->getRoom()->getAccount());
        });
    }

    public static function class(): string
    {
        return Rack::class;
    }
}
