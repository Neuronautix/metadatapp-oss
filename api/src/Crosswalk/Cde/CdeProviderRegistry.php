<?php

declare(strict_types=1);

namespace App\Crosswalk\Cde;

/**
 * Resolves a {@see CdeProviderInterface} by its {@see CdeProviderInterface::key()}.
 * Wired with a tagged iterator of all providers (tag `app.cde_provider`).
 */
final class CdeProviderRegistry
{
    /** @var array<string, CdeProviderInterface> */
    private array $providers = [];

    /**
     * @param iterable<CdeProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->key()] = $provider;
        }
    }

    public function get(string $key): ?CdeProviderInterface
    {
        return $this->providers[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->providers);
    }

    /**
     * @return array<string, CdeProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
