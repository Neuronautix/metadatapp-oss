<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Runtime;

use App\AI\Governance\AiFeatureFlags;
use App\AI\Runtime\AssistantTaskMapper;
use App\AI\Runtime\Dto\AiSuggestRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AssistantTaskMapperTest extends TestCase
{
    #[Test]
    public function itMapsSuggestionRequestsToCanonicalTaskMetadata(): void
    {
        $mapper = new AssistantTaskMapper(new AiFeatureFlags(
            assistantEnabled: true,
            provider: 'mock',
            defaultModel: 'assistant-preview.v1',
            enabledTasks: ['draft_query_filters'],
            sandboxEnabled: false,
            humanApprovalRequired: true,
            writeActionsEnabled: false,
        ));

        $mappedTask = $mapper->mapSuggest(new AiSuggestRequest(
            action: 'draft_query_filters',
            contextType: 'subjects_list',
            context: [
                'resourceType' => 'subject',
                'resourceId' => 'list',
                'filters' => ['search' => '', 'species' => '', 'consent' => ''],
            ],
            prompt: 'Show restricted rat subjects',
        ));

        self::assertSame('draft_query_filters', $mappedTask->request->taskType);
        self::assertSame('draft-query-filters.v1', $mappedTask->trace->promptVersion);
        self::assertSame(['context:subjects_list', 'resource:subject', 'resource:subject:list'], $mappedTask->trace->contextSources);
        self::assertSame('suggest', $mappedTask->trace->action);
        self::assertSame('/ai/suggest', $mappedTask->trace->route);
    }
}
