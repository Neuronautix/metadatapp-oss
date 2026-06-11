<?php

declare(strict_types=1);

namespace App\AI\Credentials;

use App\Entity\LlmProviderCredential;
use App\Entity\User;
use App\Repository\LlmProviderCredentialRepository;
use App\Secrets\SecretStoreInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class LlmProviderCredentialService
{
    private const array SUPPORTED_PROVIDERS = ['openai', 'anthropic'];
    private const array DEFAULT_MODELS = [
        'openai' => 'gpt-5',
        'anthropic' => 'claude-opus-4-1-20250805',
    ];

    public function __construct(
        private LlmProviderCredentialRepository $repository,
        private EntityManagerInterface $entityManager,
        private SecretStoreInterface $cipher,
        private Security $security,
        private string $openAiApiKey = '',
        private string $openAiModel = 'gpt-5',
        private string $anthropicApiKey = '',
        private string $anthropicModel = 'claude-opus-4-1-20250805',
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForCurrentUser(): array
    {
        $user = $this->currentUserOrNull();
        $credentialsByProvider = [];
        if ($user instanceof User) {
            foreach ($this->repository->findAllForUser($user) as $credential) {
                $credentialsByProvider[$credential->getProvider()] = $credential;
            }
        }

        return array_map(function (string $provider) use ($credentialsByProvider): array {
            /** @var LlmProviderCredential|null $credential */
            $credential = $credentialsByProvider[$provider] ?? null;
            $envConfig = $this->envConfig($provider);

            return [
                'provider' => $provider,
                'model' => $credential?->getModel() ?? $envConfig['model'],
                'configured' => null !== $credential?->getEncryptedApiKey() || '' !== $envConfig['apiKey'],
                'source' => null !== $credential?->getEncryptedApiKey() ? 'user' : ('' !== $envConfig['apiKey'] ? 'environment' : 'none'),
                'apiKeyHint' => $credential?->getApiKeyHint() ?? $this->maskSecret($envConfig['apiKey']),
                'active' => $credential?->isActive() ?? false,
                'updatedAt' => $credential?->getUpdatedAt()->format(\DATE_ATOM),
            ];
        }, self::SUPPORTED_PROVIDERS);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveForCurrentUser(string $provider, array $payload): array
    {
        $provider = $this->normalizeProvider($provider);
        $user = $this->managedCurrentUser();
        $credential = $this->repository->findOneBy([
            'provider' => $provider,
            'user' => $user,
        ]) ?? (new LlmProviderCredential())
            ->setProvider($provider)
            ->setUser($user)
            ->setAccount($user->getAccount())
        ;

        $model = isset($payload['model']) && \is_scalar($payload['model']) ? trim((string) $payload['model']) : '';
        if ('' !== $model) {
            $credential->setModel($model);
        } elseif ('' === $credential->getModel()) {
            $credential->setModel(self::DEFAULT_MODELS[$provider]);
        }

        if (\array_key_exists('apiKey', $payload) && \is_scalar($payload['apiKey'])) {
            $apiKey = trim((string) $payload['apiKey']);
            if ('' !== $apiKey) {
                $credential
                    ->setEncryptedApiKey($this->cipher->encrypt($apiKey))
                    ->setApiKeyHint($this->maskSecret($apiKey))
                ;
            }
        }

        if (\array_key_exists('active', $payload)) {
            $credential->setActive((bool) $payload['active']);
        } else {
            $credential->setActive(true);
        }

        if ('' === $credential->getModel()) {
            $credential->setModel(self::DEFAULT_MODELS[$provider]);
        }

        $credential->touch();
        $this->entityManager->persist($credential);
        $this->entityManager->flush();

        return $this->toPublicArray($credential);
    }

    public function resolveForCurrentUser(string $provider): LlmProviderCredentialConfig
    {
        $provider = $this->normalizeProvider($provider);
        $user = $this->currentUserOrNull();
        if ($user instanceof User) {
            $credential = $this->repository->findActiveForUser($provider, $user);
            if (null !== $credential && null !== $credential->getEncryptedApiKey()) {
                return new LlmProviderCredentialConfig(
                    provider: $provider,
                    model: $credential->getModel(),
                    apiKey: $this->cipher->decrypt($credential->getEncryptedApiKey()),
                    apiKeyHint: $credential->getApiKeyHint(),
                    fromUserCredential: true,
                );
            }
        }

        $envConfig = $this->envConfig($provider);

        return new LlmProviderCredentialConfig(
            provider: $provider,
            model: $envConfig['model'],
            apiKey: '' !== $envConfig['apiKey'] ? $envConfig['apiKey'] : null,
            apiKeyHint: $this->maskSecret($envConfig['apiKey']),
            fromUserCredential: false,
        );
    }

    public function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (!\in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            throw new \InvalidArgumentException(\sprintf('Unsupported LLM provider "%s".', $provider));
        }

        return $provider;
    }

    /**
     * @return array{apiKey: string, model: string}
     */
    private function envConfig(string $provider): array
    {
        return match ($provider) {
            'openai' => ['apiKey' => trim($this->openAiApiKey), 'model' => trim($this->openAiModel) ?: self::DEFAULT_MODELS['openai']],
            'anthropic' => ['apiKey' => trim($this->anthropicApiKey), 'model' => trim($this->anthropicModel) ?: self::DEFAULT_MODELS['anthropic']],
            default => ['apiKey' => '', 'model' => self::DEFAULT_MODELS[$provider] ?? ''],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function toPublicArray(LlmProviderCredential $credential): array
    {
        return [
            'provider' => $credential->getProvider(),
            'model' => $credential->getModel(),
            'configured' => null !== $credential->getEncryptedApiKey(),
            'source' => 'user',
            'apiKeyHint' => $credential->getApiKeyHint(),
            'active' => $credential->isActive(),
            'updatedAt' => $credential->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }

    private function currentUser(): User
    {
        $user = $this->currentUserOrNull();
        if (!$user instanceof User) {
            throw new \LogicException('LLM provider credentials require an authenticated Metadatapp user.');
        }

        return $user;
    }

    private function managedCurrentUser(): User
    {
        $user = $this->currentUser();
        if ($this->entityManager->contains($user)) {
            return $user;
        }

        $managedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        if (!$managedUser instanceof User) {
            throw new \LogicException('LLM provider credentials require a persisted authenticated user.');
        }

        return $managedUser;
    }

    private function currentUserOrNull(): ?UserInterface
    {
        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $user : null;
    }

    private function maskSecret(string $value): ?string
    {
        if ('' === $value) {
            return null;
        }

        $length = \strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', 6) . substr($value, -4);
    }
}
