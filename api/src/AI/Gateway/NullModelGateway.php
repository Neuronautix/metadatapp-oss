<?php

declare(strict_types=1);

namespace App\AI\Gateway;

use App\AI\Governance\AiTraceContext;

final readonly class NullModelGateway implements ModelGatewayInterface, GatewayStatusAwareInterface
{
    public function __construct(
        private string $message = 'AI assistant is disabled or no provider is configured.',
        private string $modelName = 'unconfigured',
    ) {
    }

    public function invoke(ModelRequest $request): ModelResponse
    {
        throw new \LogicException(\sprintf('No AI provider adapter is configured for task "%s" (request "%s").', $request->taskType, $request->requestId));
    }

    public function status(AiTraceContext $traceContext, array $capabilities = []): GatewayStatus
    {
        return new GatewayStatus(
            enabled: false,
            available: false,
            configured: false,
            realProvider: false,
            provider: 'null',
            model: $this->modelName,
            message: $this->message,
            capabilities: $capabilities,
        );
    }
}
