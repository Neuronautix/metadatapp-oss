<?php

declare(strict_types=1);

namespace App\Service;

interface SensorAgentClientInterface
{
    public function isEnabled(): bool;

    /**
     * @return array<string, mixed>
     */
    public function getHealth(): array;

    /**
     * @return array<string, mixed>
     */
    public function getLatestObservation(): array;

    /**
     * @return array<string, mixed>
     */
    public function getThresholdStatus(): array;
}
