<?php

declare(strict_types=1);

namespace App\AI\Runtime\Dto;

use App\AI\Gateway\GatewayStatus;
use App\AI\Governance\AiTraceContext;

final readonly class AiSuggestResponse implements \JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $suggestions
     * @param array<string, mixed>       $structuredOutput
     * @param list<array<string, mixed>> $provenance
     * @param list<string>               $warnings
     */
    public function __construct(
        public string $action,
        public string $summary,
        public GatewayStatus $status,
        public AiTraceContext $trace,
        public array $suggestions = [],
        public array $structuredOutput = [],
        public array $provenance = [],
        public array $warnings = [],
        public bool $reviewRequired = true,
        public bool $canApplyDirectly = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'action' => $this->action,
            'summary' => $this->summary,
            'status' => $this->status,
            'trace' => $this->trace,
            'suggestions' => $this->suggestions,
            'structuredOutput' => $this->structuredOutput,
            'provenance' => $this->provenance,
            'warnings' => $this->warnings,
            'reviewRequired' => $this->reviewRequired,
            'canApplyDirectly' => $this->canApplyDirectly,
        ];
    }
}
