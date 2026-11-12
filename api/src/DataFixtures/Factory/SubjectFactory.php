<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\Subject;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Enum\Species;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\Subject>
 */
class SubjectFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    public static function class(): string
    {
        return Subject::class;
    }

    protected function defaults(): array
    {
        StrainFactory::createOne(); // Ensure a Strain object is created
        $sexOptions = [Sex::Male, Sex::Female];

        $birthAt = self::faker()->dateTimeBetween('-6 months', '-1 day');

        return [
            'name' => 'SM-' . self::faker()->bothify('??-####'),
            'species' => self::faker()->randomElement(Species::cases()),
            'sex' => self::faker()->randomElement(Sex::cases()),
            'birthAt' => $birthAt,
            'strain' => StrainFactory::randomOrCreate(),
            'genotype' => self::faker()->regexify('[A-Z]{2,3}\d{2}'),
            'cage' => CageFactory::randomOrCreate(),
            'weight' => self::faker()->randomFloat(2, 0.1, 500),
            'healthStatus' => self::faker()->randomElement(HealthStatus::cases()),
            'isPregnant' => self::faker()->optional(0.2)->boolean(),
            'account' => AccountFactory::randomOrCreate(),

            //            'procedures'   => BehaviorFactory::randomRange(0, 3),
            'externalId' => self::faker()->optional()->uuid(),
            'elaftwExternalId' => self::faker()->optional()->uuid(),
            'fair3rExternalId' => self::faker()->optional()->uuid(),
            'softmouseExternalId' => self::faker()->optional()->uuid(),
        ];
    }

    public function withAccount(?Proxy $account): self
    {
        return $this->with(['account' => $account ?? AccountFactory::randomOrCreate()]);
    }
}
