<?php

declare(strict_types=1);

namespace App\AI\Gateway;

final class ModelGatewayRegistry
{
    /**
     * @var array<string, ModelGatewayInterface>
     */
    private array $gateways = [];

    /**
     * @param iterable<string, ModelGatewayInterface> $gateways
     */
    public function __construct(
        iterable $gateways,
        private readonly NullModelGateway $nullGateway,
        private readonly string $defaultGateway = 'null',
    ) {
        foreach ($gateways as $name => $gateway) {
            $this->gateways[$name] = $gateway;
        }
    }

    public function configuredProvider(): string
    {
        return $this->defaultGateway;
    }

    public function hasConfiguredGateway(): bool
    {
        return isset($this->gateways[$this->defaultGateway]);
    }

    public function getDefaultGateway(): ModelGatewayInterface
    {
        return $this->gateways[$this->defaultGateway] ?? $this->nullGateway;
    }
}
