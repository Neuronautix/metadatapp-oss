<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\TemplateFormCrosswalk;
use App\Enum\CrosswalkMappingType;
use App\Enum\MappingStatus;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\TemplateFormCrosswalk>
 */
final class TemplateFormCrosswalkFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        $account = AccountFactory::randomOrCreate();

        return [
            'externalTemplate' => ExternalTemplateFactory::new(['account' => $account]),
            'externalForm' => null,
            'canonicalForm' => CanonicalFormFactory::new(['account' => $account]),
            'mappingType' => CrosswalkMappingType::RELATED,
            'mappingStatus' => MappingStatus::SUGGESTED,
            'confidence' => self::faker()->optional()->randomFloat(2, 0, 1),
            'evidence' => null,
            'version' => '1.0.0',
            'notes' => null,
            'account' => $account,
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (TemplateFormCrosswalk $crosswalk): void {
            $account = $crosswalk->getAccount();
            $crosswalk->getExternalTemplate()->setAccount($account);
            $crosswalk->getCanonicalForm()->setAccount($account);
        });
    }

    public static function class(): string
    {
        return TemplateFormCrosswalk::class;
    }
}
