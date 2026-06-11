<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\AI\Credentials\LlmProviderCredentialService;
use App\AI\Gateway\AnthropicModelGateway;
use App\AI\Gateway\OpenAiModelGateway;
use App\AI\Runtime\AiAssistantService;
use App\AI\Runtime\Dto\AiChatRequest;
use App\AI\Runtime\Dto\AiSuggestRequest;
use App\Security\Roles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ai', name: 'api_ai_')]
#[Route('/llm', name: 'api_llm_')]
#[IsGranted(Roles::ROLE_USER)]
final class AiController extends AbstractController
{
    #[Route('/status', name: 'status', methods: ['GET'])]
    public function status(AiAssistantService $assistantService): JsonResponse
    {
        return $this->json($assistantService->status());
    }

    #[Route('/providers', name: 'providers', methods: ['GET'])]
    public function providers(LlmProviderCredentialService $credentialService): JsonResponse
    {
        return $this->json([
            'providers' => $credentialService->listForCurrentUser(),
        ]);
    }

    #[Route('/providers/{provider}', name: 'provider_save', methods: ['PATCH', 'POST'])]
    public function saveProvider(string $provider, Request $request, LlmProviderCredentialService $credentialService): JsonResponse
    {
        $payload = $this->decodeRequestPayload($request);

        try {
            return $this->json($credentialService->saveForCurrentUser($provider, $payload));
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        } catch (\LogicException $exception) {
            throw new ServiceUnavailableHttpException(null, $exception->getMessage(), $exception);
        }
    }

    #[Route('/providers/{provider}/test', name: 'provider_test', methods: ['POST'])]
    public function testProvider(string $provider, LlmProviderCredentialService $credentialService, OpenAiModelGateway $openAiGateway, AnthropicModelGateway $anthropicGateway): JsonResponse
    {
        try {
            $provider = $credentialService->normalizeProvider($provider);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $status = match ($provider) {
            'openai' => $openAiGateway->testConnection(),
            'anthropic' => $anthropicGateway->testConnection(),
            default => throw new BadRequestHttpException(\sprintf('Unsupported LLM provider "%s".', $provider)),
        };

        return $this->json(['status' => $status]);
    }

    #[Route('/chat', name: 'chat', methods: ['POST'])]
    public function chat(Request $request, AiAssistantService $assistantService): JsonResponse
    {
        $payload = $this->decodeRequestPayload($request);
        try {
            $requestDto = AiChatRequest::fromArray($payload);
            $response = $assistantService->chat($requestDto);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        } catch (\LogicException $exception) {
            throw new ServiceUnavailableHttpException(null, $exception->getMessage(), $exception);
        }

        return $this->json($response);
    }

    #[Route('/suggest', name: 'suggest', methods: ['POST'])]
    public function suggest(Request $request, AiAssistantService $assistantService): JsonResponse
    {
        $payload = $this->decodeRequestPayload($request);
        try {
            $requestDto = AiSuggestRequest::fromArray($payload);
            $response = $assistantService->suggest($requestDto);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        } catch (\LogicException $exception) {
            throw new ServiceUnavailableHttpException(null, $exception->getMessage(), $exception);
        }

        return $this->json($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeRequestPayload(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestHttpException('AI request payload must be valid JSON.', $exception);
        }

        if (!\is_array($payload)) {
            throw new BadRequestHttpException('AI request payload must be a JSON object.');
        }

        return $payload;
    }
}
