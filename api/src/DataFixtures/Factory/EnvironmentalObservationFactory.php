<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\EnvironmentalObservation;
use App\Enum\EnvironmentalMetric;
use App\Enum\EnvironmentalObservationSource;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\EnvironmentalObservation>
 */
final class EnvironmentalObservationFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $timestamp = self::faker()->dateTimeBetween('-7 days', 'now');

        return [
            'system' => SystemFactory::new(),
            'room' => null,
            'metric' => self::faker()->randomElement(EnvironmentalMetric::cases()),
            'value' => self::faker()->randomFloat(2, 6.8, 28.5),
            'timestamp' => \DateTimeImmutable::createFromMutable($timestamp),
            'source' => EnvironmentalObservationSource::MANUAL,
            'notes' => self::faker()->optional()->sentence(),
            'user' => UserFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (EnvironmentalObservation $observation): void {
            $scopeAccount = $observation->getSystem()?->getAccount() ?? $observation->getRoom()?->getAccount();
            if (null !== $scopeAccount) {
                $observation->setAccount($scopeAccount);
                $observation->getUser()->setAccount($scopeAccount);
            }
        });
    }

    public static function class(): string
    {
        return EnvironmentalObservation::class;
    }
}
