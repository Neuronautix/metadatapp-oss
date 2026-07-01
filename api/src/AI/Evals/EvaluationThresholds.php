<?php

declare(strict_types=1);

namespace App\AI\Evals;

final readonly class EvaluationThresholds
{
    public function __construct(
        public float $structuredOutputValidity = 0.99,
        public float $schemaValidationPassRate = 1.0,
        public float $policyValidationPassRate = 1.0,
        public float $provenanceCoverage = 1.0,
        public float $humanApprovalRateForWrites = 1.0,
    ) {
        foreach ([
            $this->structuredOutputValidity,
            $this->schemaValidationPassRate,
            $this->policyValidationPassRate,
            $this->provenanceCoverage,
            $this->humanApprovalRateForWrites,
        ] as $threshold) {
            if ($threshold < 0 || $threshold > 1) {
                throw new \InvalidArgumentException('Evaluation thresholds must be between 0 and 1.');
            }
        }
    }
}
