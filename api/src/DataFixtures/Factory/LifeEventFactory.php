<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\LifeEvent;
use App\Enum\HealthStatus;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\LifeEvent>
 */
final class LifeEventFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $eventAt = self::faker()->dateTimeBetween('-3 months', 'now');

        return [
            'subject' => SubjectFactory::randomOrCreate(),
            'eventType' => self::faker()->randomElement(HealthStatus::cases()),
            'eventAt' => $eventAt,
            'description' => self::faker()->optional()->paragraph(),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return LifeEvent::class;
    }
}
