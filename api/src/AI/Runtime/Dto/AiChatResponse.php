<?php

declare(strict_types=1);

namespace App\AI\Runtime\Dto;

use App\AI\Gateway\GatewayStatus;
use App\AI\Governance\AiTraceContext;

final readonly class AiChatResponse implements \JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $provenance
     * @param list<string>               $warnings
     * @param array<string, mixed>       $structuredOutput
     */
    public function __construct(
        public string $reply,
        public GatewayStatus $status,
        public AiTraceContext $trace,
        public array $provenance = [],
        public array $warnings = [],
        public array $structuredOutput = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'reply' => $this->reply,
            'status' => $this->status,
            'trace' => $this->trace,
            'provenance' => $this->provenance,
            'warnings' => $this->warnings,
            'structuredOutput' => $this->structuredOutput,
        ];
    }
}
