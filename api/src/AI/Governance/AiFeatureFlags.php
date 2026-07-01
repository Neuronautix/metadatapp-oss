<?php

declare(strict_types=1);

namespace App\AI\Governance;

final readonly class AiFeatureFlags
{
    /**
     * @param list<string> $enabledTasks
     */
    public function __construct(
        private bool $assistantEnabled,
        private string $provider,
        private string $defaultModel,
        private array $enabledTasks,
        private bool $sandboxEnabled,
        private bool $humanApprovalRequired,
        private bool $writeActionsEnabled,
        private bool $agenticModeEnabled = false,
    ) {
        if ($this->writeActionsEnabled && !$this->humanApprovalRequired) {
            throw new \InvalidArgumentException('AI write actions must require human approval.');
        }
    }

    public function isAssistantEnabled(): bool
    {
        return $this->assistantEnabled;
    }

    /**
     * Whether the bounded multi-step tool-use (agentic) loop is enabled.
     * Off by default; when off the assistant degrades to single-shot chat.
     */
    public function isAgenticModeEnabled(): bool
    {
        return $this->assistantEnabled && $this->agenticModeEnabled;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function defaultModel(): string
    {
        return $this->defaultModel;
    }

    public function sandboxEnabled(): bool
    {
        return $this->sandboxEnabled;
    }

    public function humanApprovalRequired(): bool
    {
        return $this->humanApprovalRequired;
    }

    public function allowsTask(string $taskType): bool
    {
        return \in_array($taskType, $this->enabledTasks, true);
    }

    /**
     * @return list<string>
     */
    public function enabledTasks(): array
    {
        return $this->enabledTasks;
    }

    public function allowsWriteBack(): bool
    {
        return $this->writeActionsEnabled && $this->humanApprovalRequired;
    }
}
