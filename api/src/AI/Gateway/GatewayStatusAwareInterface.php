<?php

declare(strict_types=1);

namespace App\AI\Gateway;

use App\AI\Governance\AiTraceContext;

interface GatewayStatusAwareInterface
{
    /**
     * @param list<string> $capabilities
     */
    public function status(AiTraceContext $traceContext, array $capabilities = []): GatewayStatus;
}
