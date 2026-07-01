<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory;

use App\Entity\ZebrafishLine;
use App\Enum\ZebrafishLineStatus;

/**
 * @extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory<\App\Entity\ZebrafishLine>
 */
final class ZebrafishLineFactory extends \Zenstruck\Foundry\Persistence\PersistentObjectFactory
{
    private const DEFAULT_LINES = [
        [
            'name' => 'Tg(elavl3:GCaMP6s)',
            'genotype' => 'Tg(elavl3:GCaMP6s)zf410',
            'purpose' => 'Pan-neuronal calcium imaging reporter line.',
            'status' => ZebrafishLineStatus::ACTIVE,
            'createdAt' => '2024-01-10T09:00:00+00:00',
        ],
        [
            'name' => 'Tg(ins:mCherry)',
            'genotype' => 'Tg(ins:mCherry)s960',
            'purpose' => 'Endocrine pancreas reporter for islet screening.',
            'status' => ZebrafishLineStatus::ACTIVE,
            'createdAt' => '2024-02-15T08:30:00+00:00',
        ],
        [
            'name' => 'Tg(mpx:GFP)',
            'genotype' => 'Tg(mpx:GFP)i114',
            'purpose' => 'Neutrophil tracking line for inflammation assays.',
            'status' => ZebrafishLineStatus::ACTIVE,
            'createdAt' => '2024-03-05T11:15:00+00:00',
        ],
        [
            'name' => 'Tg(gfap:EGFP)',
            'genotype' => 'Tg(gfap:EGFP)mi2001',
            'purpose' => 'Glial lineage reporter currently paused for re-derivation.',
            'status' => ZebrafishLineStatus::PAUSED,
            'createdAt' => '2024-04-02T14:00:00+00:00',
        ],
        [
            'name' => 'Tg(kdrl:ras-mCherry)',
            'genotype' => 'Tg(kdrl:ras-mCherry)s896',
            'purpose' => 'Archived vascular reporter retained for reference batches.',
            'status' => ZebrafishLineStatus::ARCHIVED,
            'createdAt' => '2023-11-20T10:45:00+00:00',
        ],
    ];

    private static int $sequence = 0;

    protected function defaults(): array
    {
        $defaults = self::DEFAULT_LINES[self::$sequence % \count(self::DEFAULT_LINES)];
        ++self::$sequence;

        return [
            'name' => $defaults['name'],
            'genotype' => $defaults['genotype'],
            'purpose' => $defaults['purpose'],
            'status' => $defaults['status'],
            'createdAt' => new \DateTimeImmutable($defaults['createdAt']),
            'account' => AccountFactory::randomOrCreate(),
        ];
    }

    public static function class(): string
    {
        return ZebrafishLine::class;
    }
}
