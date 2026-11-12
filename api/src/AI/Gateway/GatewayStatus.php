<?php

declare(strict_types=1);

namespace App\AI\Gateway;

final readonly class GatewayStatus implements \JsonSerializable
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public bool $enabled,
        public bool $available,
        public bool $configured,
        public bool $realProvider,
        public string $provider,
        public string $model,
        public string $message,
        public array $capabilities = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'enabled' => $this->enabled,
            'available' => $this->available,
            'configured' => $this->configured,
            'realProvider' => $this->realProvider,
            'provider' => $this->provider,
            'model' => $this->model,
            'message' => $this->message,
            'capabilities' => $this->capabilities,
        ];
    }
}
