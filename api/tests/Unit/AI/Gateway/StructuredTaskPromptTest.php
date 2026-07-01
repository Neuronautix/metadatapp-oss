<?php

declare(strict_types=1);

namespace App\Tests\Unit\AI\Gateway;

use App\AI\Gateway\StructuredTaskPrompt;
use App\AI\Runtime\AssistantTask;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StructuredTaskPromptTest extends TestCase
{
    #[Test]
    public function theStandardizePromptDescribesTheClusterAndHarmonizationSchema(): void
    {
        $prompt = StructuredTaskPrompt::system(AssistantTask::STANDARDIZE_REFERENCES);

        self::assertStringContainsString('structured_output.clusters', $prompt);
        self::assertStringContainsString('canonicalTerm', $prompt);
        self::assertStringContainsString('memberRefIds', $prompt);
        self::assertStringContainsString('harmonizedFields', $prompt);
        self::assertStringContainsString('groundedCandidates', $prompt);
        // More room for structured clustering than a chat reply.
        self::assertGreaterThan(1200, StructuredTaskPrompt::maxTokens(AssistantTask::STANDARDIZE_REFERENCES));
    }

    #[Test]
    public function theRefinePromptDescribesTheRankingsSchema(): void
    {
        $prompt = StructuredTaskPrompt::system(AssistantTask::REFINE_CROSSWALK_MAPPING);

        self::assertStringContainsString('structured_output.rankings', $prompt);
        self::assertStringContainsString('ref', $prompt);
    }

    #[Test]
    public function unknownTasksFallBackToTheGenericContract(): void
    {
        $prompt = StructuredTaskPrompt::system(AssistantTask::CHAT);

        self::assertStringContainsString('content', $prompt);
        self::assertStringContainsString('suggestions', $prompt);
        self::assertStringNotContainsString('structured_output.clusters', $prompt);
        self::assertSame(1200, StructuredTaskPrompt::maxTokens(AssistantTask::CHAT));
    }
}
