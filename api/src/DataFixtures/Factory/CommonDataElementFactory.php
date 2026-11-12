<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\CommonDataElement;
use App\Enum\CdeSource;
use App\Enum\CdeStatus;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\CommonDataElement>
 */
final class CommonDataElementFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $label = self::faker()->unique()->words(2, true);

        return [
            'source' => CdeSource::METADATAPP,
            'externalId' => null,
            'externalUrl' => null,
            'version' => '1.0.0',
            'label' => ucfirst($label),
            'definition' => self::faker()->optional()->sentence(),
            'questionText' => null,
            'datatype' => self::faker()->randomElement(['string', 'decimal', 'integer', 'boolean']),
            'unitConstraint' => null,
            'permissibleValues' => [],
            'ontologyMappings' => [],
            'provenance' => ['provider' => 'metadatapp'],
            'rawPayload' => null,
            'status' => CdeStatus::DRAFT,
            'localKey' => str_replace(' ', '_', strtolower($label)),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return CommonDataElement::class;
    }
}
