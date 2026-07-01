<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Gateway\ModelResponse;

/**
 * Outcome of a bounded {@see ToolUseOrchestrator} run: the final model response,
 * the ordered provenance trail of every tool call dispatched along the way, the
 * number of loop iterations consumed, and any warnings (e.g. the iteration cap was
 * hit or a write tool was refused because write-back is disabled).
 */
final readonly class AgenticChatResult
{
    /**
     * @param list<array<string, mixed>> $toolProvenance
     * @param list<string>               $warnings
     */
    public function __construct(
        public ModelResponse $finalResponse,
        public array $toolProvenance = [],
        public int $iterations = 0,
        public bool $iterationCapReached = false,
        public array $warnings = [],
    ) {
    }
}
