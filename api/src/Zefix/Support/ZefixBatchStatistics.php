<?php

declare(strict_types=1);

namespace App\Zefix\Support;

use App\Entity\MortalityRecord;
use App\Entity\ZebrafishBatch;
use Doctrine\Common\Collections\Collection;

final readonly class ZefixBatchStatistics
{
    public function getInitialPopulation(ZebrafishBatch $batch): int
    {
        return $batch->getMaleCount() + $batch->getFemaleCount() + $batch->getUnknownSexCount();
    }

    public function getCurrentPopulation(ZebrafishBatch $batch): int
    {
        return max(0, $this->getInitialPopulation($batch) - $this->getMortalityCount($batch));
    }

    public function getMortalityCount(ZebrafishBatch $batch): int
    {
        return $this->sumMortalityCounts($batch->getMortalityRecords());
    }

    public function getMortalityRate(ZebrafishBatch $batch): float
    {
        $initialPopulation = $this->getInitialPopulation($batch);
        if (0 === $initialPopulation) {
            return 0.0;
        }

        return round(($this->getMortalityCount($batch) / $initialPopulation) * 100, 1);
    }

    /**
     * @param Collection<int, MortalityRecord> $records
     */
    public function sumMortalityCounts(Collection $records): int
    {
        return array_reduce(
            $records->toArray(),
            static fn (int $sum, MortalityRecord $record): int => $sum + $record->getCount(),
            0
        );
    }
}
