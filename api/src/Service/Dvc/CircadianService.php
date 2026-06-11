<?php

declare(strict_types=1);

namespace App\Service\Dvc;

use App\Entity\Cage;
use App\Entity\TimeSeries;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Computes circadian-rhythm metrics from cage time-series data.
 *
 * All computations are UTC-based to avoid browser/server timezone assumptions.
 * Zeitgeber time (ZT) is computed relative to the configured lights-on hour.
 *   ZT 0   = lights on
 *   ZT {lightDurationHours} = lights off / dark phase begins
 */
final readonly class CircadianService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Compute metrics for a cage over an optional time window.
     *
     * @param \DateTimeImmutable|null $from window start (UTC), defaults to 7 days ago
     * @param \DateTimeImmutable|null $to   window end  (UTC), defaults to now
     */
    public function computeForCage(
        Cage $cage,
        ?int $lightsOnUtcHour,
        ?int $lightDurationHours,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
    ): CircadianMetrics {
        $from ??= new \DateTimeImmutable('-7 days');
        $to ??= new \DateTimeImmutable('now');
        $lightDurationHours ??= 12;

        /** @var TimeSeries[] $rows */
        $rows = $this->entityManager
            ->createQueryBuilder()
            ->select('t')
            ->from(TimeSeries::class, 't')
            ->join('t.subject', 's')
            ->join('t.variable', 'v')
            ->andWhere('s.cage = :cage')
            ->andWhere('t.account = :account')
            ->andWhere('LOWER(v.name) LIKE :activity')
            ->andWhere('t.recordedAt BETWEEN :from AND :to')
            ->setParameter('cage', $cage)
            ->setParameter('account', $cage->getAccount())
            ->setParameter('activity', '%activity%')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('t.recordedAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        if ([] === $rows) {
            return new CircadianMetrics(
                acrophaseUtcHour: null,
                acrophaseZeitgeber: null,
                amplitude: 0.0,
                dayNightRatio: null,
                fragmentation: 0.0,
                dataPointCount: 0,
                lightsOnUtcHour: $lightsOnUtcHour,
                lightDurationHours: $lightDurationHours,
            );
        }

        // --- bucket values by UTC hour (0–23) ---
        /** @var array<int, float> $hourlySums */
        $hourlySums = array_fill(0, 24, 0.0);
        /** @var array<int, int> $hourlyCounts */
        $hourlyCounts = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            $utcHour = (int) \DateTimeImmutable::createFromInterface($row->getRecordedAt())
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('G')
            ;
            $hourlySums[$utcHour] += (float) $row->getValue();
            ++$hourlyCounts[$utcHour];
        }

        /** @var array<int, float> $hourlyMeans  keyed 0-23 */
        $hourlyMeans = [];
        for ($h = 0; $h < 24; ++$h) {
            if (0 === $hourlyCounts[$h]) {
                continue;
            }

            $hourlyMeans[$h] = $hourlySums[$h] / $hourlyCounts[$h];
        }

        if ([] === $hourlyMeans) {
            return new CircadianMetrics(
                acrophaseUtcHour: null,
                acrophaseZeitgeber: null,
                amplitude: 0.0,
                dayNightRatio: null,
                fragmentation: 0.0,
                dataPointCount: \count($rows),
                lightsOnUtcHour: $lightsOnUtcHour,
                lightDurationHours: $lightDurationHours,
            );
        }

        $acrophaseUtcHour = (int) array_keys($hourlyMeans, max($hourlyMeans), true)[0];
        $amplitude = max($hourlyMeans) - min($hourlyMeans);

        // --- Zeitgeber-time acrophase ---
        $acrophaseZt = null;
        if (null !== $lightsOnUtcHour) {
            $acrophaseZt = ($acrophaseUtcHour - $lightsOnUtcHour + 24) % 24;
        }

        // --- Day / Night ratio ---
        $dayNightRatio = null;
        if (null !== $lightsOnUtcHour) {
            $dayValues = [];
            $nightValues = [];
            foreach ($hourlyMeans as $utcH => $mean) {
                $zt = ($utcH - $lightsOnUtcHour + 24) % 24;
                if ($zt < $lightDurationHours) {
                    $dayValues[] = $mean;
                } else {
                    $nightValues[] = $mean;
                }
            }
            $dayMean = \count($dayValues) > 0 ? array_sum($dayValues) / \count($dayValues) : 0.0;
            $nightMean = \count($nightValues) > 0 ? array_sum($nightValues) / \count($nightValues) : 0.0;
            $dayNightRatio = $nightMean > 0.0 ? round($dayMean / $nightMean, 4) : null;
        }

        // --- Fragmentation: activity direction changes per interval ---
        $fragmentation = 0.0;
        $values = array_map(static fn (TimeSeries $row): float => (float) $row->getValue(), $rows);
        $n = \count($values);
        if ($n >= 2) {
            $transitions = 0;
            $wasIncreasing = null;
            for ($i = 1; $i < $n; ++$i) {
                $delta = $values[$i] - $values[$i - 1];
                if (abs($delta) < 1e-9) {
                    continue;
                }
                $increasing = $delta > 0;
                if (null !== $wasIncreasing && $increasing !== $wasIncreasing) {
                    ++$transitions;
                }
                $wasIncreasing = $increasing;
            }
            $fragmentation = round($transitions / ($n - 1), 4);
        }

        return new CircadianMetrics(
            acrophaseUtcHour: $acrophaseUtcHour,
            acrophaseZeitgeber: $acrophaseZt,
            amplitude: round($amplitude, 4),
            dayNightRatio: $dayNightRatio,
            fragmentation: $fragmentation,
            dataPointCount: $n,
            lightsOnUtcHour: $lightsOnUtcHour,
            lightDurationHours: $lightDurationHours,
        );
    }
}
