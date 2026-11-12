<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Room;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Room>
 */
final class RoomFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    private const DEFAULT_ROOM_NAMES = [
        'Room 101',
        'Room 102',
        'Room 201',
        'Room 202',
        'Room Q1',
        'Room Q2',
    ];

    private static int $sequence = 0;

    protected function defaults(): array
    {
        $name = self::DEFAULT_ROOM_NAMES[self::$sequence % \count(self::DEFAULT_ROOM_NAMES)];
        ++self::$sequence;

        return [
            'name' => $name,
            'plateau' => PlateauFactory::new(),
            'account' => AccountFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (Room $room): void {
            $room->setAccount($room->getPlateau()->getAccount());
        });
    }

    public static function class(): string
    {
        return Room::class;
    }
}
