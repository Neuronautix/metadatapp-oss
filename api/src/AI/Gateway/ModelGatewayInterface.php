<?php

declare(strict_types=1);

namespace App\AI\Gateway;

interface ModelGatewayInterface
{
    public function invoke(ModelRequest $request): ModelResponse;
}
