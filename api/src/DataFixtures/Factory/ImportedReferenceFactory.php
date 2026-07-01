<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\ImportedReference;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\ImportedReference>
 */
final class ImportedReferenceFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $measnum = self::faker()->numberBetween(1000, 99999);

        return [
            'account' => AccountFactory::randomOrCreate(),
            'referenceId' => 'jax_phenome:measure:' . $measnum,
            'source' => 'jax_phenome',
            'sourceName' => 'JAX Phenome (MPD)',
            'type' => 'measure',
            'title' => self::faker()->words(3, true),
            'externalUrl' => 'https://phenome.jax.org/measureset/' . $measnum,
            'identifiers' => ['measnum' => $measnum],
            'raw' => ['measnum' => $measnum],
        ];
    }

    public static function class(): string
    {
        return ImportedReference::class;
    }
}
