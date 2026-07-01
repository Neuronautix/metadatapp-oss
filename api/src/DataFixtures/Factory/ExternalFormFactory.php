<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\ExternalForm;
use App\Enum\ExternalFormSource;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\ExternalForm>
 */
final class ExternalFormFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'source' => ExternalFormSource::METADATAPP,
            'externalId' => self::faker()->optional()->bothify('FORM-####'),
            'externalUrl' => self::faker()->optional()->url(),
            'title' => self::faker()->sentence(3),
            'description' => self::faker()->optional()->sentence(),
            'version' => '1.0.0',
            'rawPayload' => null,
            'user' => UserFactory::randomOrCreate(),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (ExternalForm $form): void {
            $form->getUser()->setAccount($form->getAccount());
        });
    }

    public static function class(): string
    {
        return ExternalForm::class;
    }
}
