<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\MortalityRecord;
use App\Enum\MortalityReason;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\MortalityRecord>
 */
final class MortalityRecordFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'batch' => ZebrafishBatchFactory::randomOrCreate(),
            'date' => self::faker()->dateTimeBetween('-30 days', 'now'),
            'count' => self::faker()->numberBetween(1, 6),
            'reason' => self::faker()->randomElement(MortalityReason::cases()),
            'notes' => self::faker()->optional()->sentence(),
            'user' => UserFactory::randomOrCreate(),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (MortalityRecord $record): void {
            $account = $record->getBatch()->getAccount();
            $record->setAccount($account);
            $record->getUser()->setAccount($account);
        });
    }

    public static function class(): string
    {
        return MortalityRecord::class;
    }
}
