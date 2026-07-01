<?php

declare(strict_types=1);

namespace App\AI\Credentials;

final readonly class LlmProviderCredentialConfig
{
    public function __construct(
        public string $provider,
        public string $model,
        public ?string $apiKey,
        public ?string $apiKeyHint,
        public bool $fromUserCredential,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->apiKey && '' !== trim($this->apiKey);
    }
}
