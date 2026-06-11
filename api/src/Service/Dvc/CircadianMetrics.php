<?php

declare(strict_types=1);

namespace App\Service\Dvc;

/**
 * Computed circadian metrics for a single cage over a requested time window.
 *
 * All time values are UTC-based. Zeitgeber time (ZT) is used when a lights-on
 * hour is available; otherwise null.
 *
 * Scientific note: in standard rodent housing the "activity phase" is the dark
 * phase (ZT lightDurationHours → ZT 24).  Day / night ratio < 1 therefore
 * indicates normal nocturnal rhythm.
 */
final class CircadianMetrics
{
    public function __construct(
        /** UTC hour that was the peak mean activity (0-23). */
        public readonly ?int $acrophaseUtcHour,
        /** ZT hour of peak mean activity (0-23); null if light schedule not set. */
        public readonly ?int $acrophaseZeitgeber,
        /** Peak-to-trough difference of per-hour mean activity. */
        public readonly float $amplitude,
        /** Mean activity during the light phase / mean activity during the dark phase. */
        public readonly ?float $dayNightRatio,
        /**
         * Activity-direction change rate (transitions per interval).
         * Higher values indicate more fragmented activity.
         */
        public readonly float $fragmentation,
        /** Number of raw data points used in the computation. */
        public readonly int $dataPointCount,
        /** Configured lights-on hour (UTC, 0-23); null if not configured. */
        public readonly ?int $lightsOnUtcHour,
        /** Configured light-phase duration in hours; null if not configured. */
        public readonly ?int $lightDurationHours,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'acrophaseUtcHour' => $this->acrophaseUtcHour,
            'acrophaseZeitgeber' => $this->acrophaseZeitgeber,
            'amplitude' => $this->amplitude,
            'dayNightRatio' => $this->dayNightRatio,
            'fragmentation' => $this->fragmentation,
            'dataPointCount' => $this->dataPointCount,
            'lightsOnUtcHour' => $this->lightsOnUtcHour,
            'lightDurationHours' => $this->lightDurationHours,
        ];
    }
}
