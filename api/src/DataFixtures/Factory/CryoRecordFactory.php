<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\CryoRecord;
use App\Enum\CryoRecordStatus;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\CryoRecord>
 */
final class CryoRecordFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    protected function defaults(): array
    {
        return [
            'line' => ZebrafishLineFactory::new(),
            'storageLocation' => self::faker()->bothify('Cryo rack ??-#'),
            'protocolUsed' => self::faker()->sentence(4),
            'storageDate' => new \DateTimeImmutable(self::faker()->date('Y-m-d')),
            'notes' => self::faker()->optional()->sentence(),
            'status' => CryoRecordStatus::STORED,
            'account' => AccountFactory::new(),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(static function (CryoRecord $record): void {
            $line = $record->getLine();
            if (null !== $line) {
                $record->setAccount($line->getAccount());
            }
        });
    }

    public static function class(): string
    {
        return CryoRecord::class;
    }
}
