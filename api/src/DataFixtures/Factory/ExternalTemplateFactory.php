<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\ExternalTemplate;
use App\Enum\ExternalTemplateFormat;
use App\Enum\ExternalTemplateSource;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\ExternalTemplate>
 */
final class ExternalTemplateFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'source' => ExternalTemplateSource::ELN_FILE,
            'format' => ExternalTemplateFormat::ELN_RO_CRATE,
            'title' => self::faker()->sentence(3),
            'description' => self::faker()->optional()->sentence(),
            'version' => '1.0.0',
            'externalUrl' => self::faker()->optional()->url(),
            'roCrateMetadata' => ['@context' => 'https://w3id.org/ro/crate/1.1/context', '@graph' => []],
            'rawPayload' => null,
            'fileHash' => self::faker()->sha256(),
            'user' => UserFactory::randomOrCreate(),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (ExternalTemplate $template): void {
            $template->getUser()->setAccount($template->getAccount());
        });
    }

    public static function class(): string
    {
        return ExternalTemplate::class;
    }
}
