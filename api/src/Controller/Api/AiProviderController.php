<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Manages the account-level LLM provider configuration.
 *
 * GET  /api/ai/provider-config  — returns the current provider settings (API key masked).
 * PATCH /api/ai/provider-config — updates provider, API key, and/or model for the account.
 *
 * Only organisation admins should be able to write to this endpoint.
 * All authenticated users can read it to check whether AI is configured.
 */
#[Route('/api/ai/provider-config', name: 'api_ai_provider_config_')]
#[Route('/api/llm/provider-config', name: 'api_llm_provider_config_')]
#[IsGranted(Roles::ROLE_USER)]
final class AiProviderController extends AbstractController
{
    private const SUPPORTED_PROVIDERS = ['openai', 'anthropic', 'gemini'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->hasAccount()) {
            return $this->json(['provider' => null, 'keyPreview' => null, 'model' => null, 'configured' => false]);
        }

        $account = $user->getAccount();

        return $this->json([
            'provider' => $account->getLlmProvider(),
            'keyPreview' => $account->getLlmApiKeyPreview(),
            'model' => $account->getLlmModel(),
            'configured' => null !== $account->getLlmProvider() && null !== $account->getLlmApiKey(),
        ]);
    }

    #[Route('', name: 'patch', methods: ['PATCH'])]
    #[IsGranted(Roles::ROLE_ADMIN)]
    public function patch(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->hasAccount()) {
            return $this->json(['error' => 'No account found for the current user.'], 400);
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) $request->toArray();

        $account = $user->getAccount();

        // Validate provider when provided
        if (\array_key_exists('provider', $payload)) {
            $provider = $payload['provider'];
            if (null !== $provider && !\in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
                return $this->json([
                    'error' => \sprintf('Unsupported provider "%s". Allowed: %s.', $provider, implode(', ', self::SUPPORTED_PROVIDERS)),
                ], 422);
            }
            $account->setLlmProvider(\is_string($provider) ? $provider : null);
        }

        // Update API key only when explicitly set (empty string clears it)
        if (\array_key_exists('apiKey', $payload)) {
            $key = $payload['apiKey'];
            $account->setLlmApiKey(\is_string($key) && '' !== $key ? $key : null);
        }

        // Update model name
        if (\array_key_exists('model', $payload)) {
            $model = $payload['model'];
            $account->setLlmModel(\is_string($model) && '' !== $model ? $model : null);
        }

        $this->entityManager->flush();

        return $this->json([
            'provider' => $account->getLlmProvider(),
            'keyPreview' => $account->getLlmApiKeyPreview(),
            'model' => $account->getLlmModel(),
            'configured' => null !== $account->getLlmProvider() && null !== $account->getLlmApiKey(),
        ]);
    }
}
