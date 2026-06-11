<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\System;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\System>
 */
final class SystemFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    private const DEFAULT_SYSTEMS = [
        ['code' => 'SYS-01', 'manufacturer' => 'Tecniplast', 'model' => 'ZebTEC Active Blue', 'location' => 'room', 'conductivity' => 510.0, 'oxygen' => 7.8, 'waterFlow' => 12.5, 'filtrationStatus' => 'OK'],
        ['code' => 'SYS-02', 'manufacturer' => 'Pentair Aquatic Eco-Systems', 'model' => 'ZFS-400', 'location' => 'room', 'conductivity' => 495.0, 'oxygen' => 7.7, 'waterFlow' => 12.0, 'filtrationStatus' => 'OK'],
        ['code' => 'SYS-03', 'manufacturer' => 'AquaMedic', 'model' => 'LifeBreeder Pro', 'location' => 'rack', 'conductivity' => 502.0, 'oxygen' => 7.6, 'waterFlow' => 10.8, 'filtrationStatus' => 'MAINTENANCE'],
        ['code' => 'SYS-04', 'manufacturer' => 'Tecniplast', 'model' => 'ZebTEC Multilinking', 'location' => 'room', 'conductivity' => 518.0, 'oxygen' => 7.9, 'waterFlow' => 11.9, 'filtrationStatus' => 'OK'],
    ];

    private static int $sequence = 0;

    protected function defaults(): array
    {
        $defaults = self::DEFAULT_SYSTEMS[self::$sequence % \count(self::DEFAULT_SYSTEMS)];
        ++self::$sequence;

        return [
            'code' => $defaults['code'],
            'manufacturer' => $defaults['manufacturer'],
            'model' => $defaults['model'],
            'room' => RoomFactory::new(),
            'waterTemperature' => null,
            'pH' => null,
            'conductivity' => $defaults['conductivity'],
            'oxygen' => $defaults['oxygen'],
            'waterFlow' => $defaults['waterFlow'],
            'filtrationStatus' => $defaults['filtrationStatus'],
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (System $system): void {
            $system->setAccount($system->getRoom()->getAccount());
        });
    }

    public static function class(): string
    {
        return System::class;
    }
}
