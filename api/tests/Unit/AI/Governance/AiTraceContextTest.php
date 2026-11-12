<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Governance;

use App\AI\Governance\AiTraceContext;
use App\AI\Governance\HumanApprovalState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AiTraceContextTest extends TestCase
{
    #[Test]
    public function itProducesTheCanonicalStructuredLogFieldsWithRouteContext(): void
    {
        $recordedAt = new \DateTimeImmutable('2026-04-03T10:00:00+00:00');
        $context = AiTraceContext::create(
            taskType: 'draft_query_filters',
            provider: 'mock',
            model: 'assistant-preview.v1',
            promptVersion: 'draft-query-filters.v1',
            outputSchemaVersion: 'ai.assistant.suggest.v1',
            humanApprovalState: HumanApprovalState::Required,
            contextSources: ['context:subjects_list', 'resource:subject:list'],
            action: 'suggest',
            route: '/ai/suggest',
            resourceType: 'subject',
            resourceId: 'list',
            evaluatorResult: 'passed',
            requestId: 'req-123',
            recordedAt: $recordedAt,
        );

        self::assertSame([
            'request_id' => 'req-123',
            'task_type' => 'draft_query_filters',
            'provider' => 'mock',
            'model' => 'assistant-preview.v1',
            'prompt_version' => 'draft-query-filters.v1',
            'context_sources' => ['context:subjects_list', 'resource:subject:list'],
            'output_schema_version' => 'ai.assistant.suggest.v1',
            'evaluator_result' => 'passed',
            'human_approval_state' => 'required',
            'action' => 'suggest',
            'route' => '/ai/suggest',
            'resource_type' => 'subject',
            'resource_id' => 'list',
            'recorded_at' => '2026-04-03T10:00:00+00:00',
        ], $context->toLogContext());
    }
}
