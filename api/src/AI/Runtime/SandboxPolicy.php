<?php

declare(strict_types=1);

namespace App\AI\Runtime;

final readonly class SandboxPolicy
{
    /**
     * @param list<string> $allowedTaskTypes
     */
    public function __construct(
        public bool $enabled,
        public array $allowedTaskTypes,
        public bool $requiresIsolation = true,
    ) {
    }

    public function allows(string $taskType, bool $requestsCodeExecution): bool
    {
        if (!$this->enabled) {
            return false;
        }

        if (!\in_array($taskType, $this->allowedTaskTypes, true)) {
            return false;
        }

        return !$requestsCodeExecution || $this->requiresIsolation;
    }
}
