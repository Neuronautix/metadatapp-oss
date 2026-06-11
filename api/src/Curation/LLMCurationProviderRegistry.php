<?php

declare(strict_types=1);

namespace App\Curation;

final class LLMCurationProviderRegistry
{
    /**
     * @var array<string, LLMCurationProvider>
     */
    private array $providers = [];

    /**
     * @param iterable<string, LLMCurationProvider> $providers
     */
    public function __construct(
        iterable $providers,
        private readonly string $defaultProvider = 'mock',
    ) {
        foreach ($providers as $name => $provider) {
            $this->providers[$name] = $provider;
        }
    }

    public function getDefaultProvider(): LLMCurationProvider
    {
        return $this->providers[$this->getDefaultProviderName()];
    }

    public function getDefaultProviderName(): string
    {
        if (isset($this->providers[$this->defaultProvider])) {
            return $this->defaultProvider;
        }

        if ([] === $this->providers) {
            throw new \RuntimeException('No LLM curation providers are configured.');
        }

        return (string) array_key_first($this->providers);
    }
}
