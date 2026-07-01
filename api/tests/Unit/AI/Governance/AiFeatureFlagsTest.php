<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Governance;

use App\AI\Governance\AiFeatureFlags;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AiFeatureFlagsTest extends TestCase
{
    #[Test]
    public function itAllowsConfiguredTasksAndWriteBackOnlyWhenApprovalIsRequired(): void
    {
        $flags = new AiFeatureFlags(
            assistantEnabled: true,
            provider: 'mock',
            defaultModel: 'assistant-preview.v1',
            enabledTasks: ['assistant_chat', 'draft_query_filters'],
            sandboxEnabled: false,
            humanApprovalRequired: true,
            writeActionsEnabled: true,
        );

        self::assertTrue($flags->isAssistantEnabled());
        self::assertTrue($flags->allowsTask('assistant_chat'));
        self::assertFalse($flags->allowsTask('generate_export_content'));
        self::assertTrue($flags->allowsWriteBack());
    }

    #[Test]
    public function itFailsClosedWhenWriteBackWouldBypassHumanApproval(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new AiFeatureFlags(
            assistantEnabled: true,
            provider: 'mock',
            defaultModel: 'assistant-preview.v1',
            enabledTasks: ['assistant_chat'],
            sandboxEnabled: false,
            humanApprovalRequired: false,
            writeActionsEnabled: true,
        );
    }
}
