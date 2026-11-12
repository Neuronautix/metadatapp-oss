<?php

declare(strict_types=1);

namespace App\AI\Runtime\Dto;

use App\AI\Gateway\GatewayStatus;
use App\AI\Governance\AiTraceContext;

final readonly class AiStatusResponse implements \JsonSerializable
{
    public function __construct(
        public GatewayStatus $status,
        public AiTraceContext $trace,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'status' => $this->status,
            'trace' => $this->trace,
        ];
    }
}
