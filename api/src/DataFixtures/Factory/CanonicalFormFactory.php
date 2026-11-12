<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\CanonicalForm;
use App\Enum\CanonicalFormStatus;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\CanonicalForm>
 */
final class CanonicalFormFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'title' => self::faker()->sentence(3),
            'description' => self::faker()->optional()->sentence(),
            'domain' => self::faker()->randomElement(['chemistry', 'biology', 'materials', 'general']),
            'version' => '1.0.0',
            'status' => CanonicalFormStatus::DRAFT,
            'sourceLinks' => [],
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return CanonicalForm::class;
    }
}
