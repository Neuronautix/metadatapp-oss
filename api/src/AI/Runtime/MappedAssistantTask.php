<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Gateway\ModelRequest;
use App\AI\Governance\AiTraceContext;

final readonly class MappedAssistantTask
{
    public function __construct(
        public string $action,
        public ModelRequest $request,
        public AiTraceContext $trace,
    ) {
    }
}
