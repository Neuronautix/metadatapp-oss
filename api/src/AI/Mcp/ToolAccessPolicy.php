<?php

declare(strict_types=1);

namespace App\AI\Mcp;

final readonly class ToolAccessPolicy
{
    public function __construct(
        public string $toolName,
        public bool $allowsRead = true,
        public bool $allowsWrite = false,
        public bool $requiresHumanApproval = true,
    ) {
        if ($this->allowsWrite && !$this->requiresHumanApproval) {
            throw new \InvalidArgumentException('Write-enabled MCP tools must require human approval.');
        }
    }
}
