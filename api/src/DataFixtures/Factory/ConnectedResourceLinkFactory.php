<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\ConnectedResourceLink;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\ConnectedResourceLink>
 */
final class ConnectedResourceLinkFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $experiment = ExperimentFactory::randomOrCreate();

        return [
            'provider' => self::faker()->randomElement(['elabftw', 'tecniplast_dvc', 'osf']),
            'externalId' => self::faker()->unique()->bothify('EXT-####'),
            'resourceType' => self::faker()->randomElement(['protocol', 'experiment', 'animal_record', 'extraction_task', 'repository']),
            'label' => self::faker()->optional()->sentence(3),
            'url' => self::faker()->optional()->url(),
            'subjectExternalId' => null,
            'syncedAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-30 days', 'now')),
            'experiment' => $experiment,
            'account' => $experiment->getAccount(),
        ];
    }

    public static function class(): string
    {
        return ConnectedResourceLink::class;
    }
}
