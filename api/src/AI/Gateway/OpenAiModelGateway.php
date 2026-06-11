<?php

declare(strict_types=1);

namespace App\AI\Gateway;

use App\AI\Credentials\LlmProviderCredentialService;
use App\AI\Governance\AiTraceContext;
use App\AI\Runtime\AssistantTask;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class OpenAiModelGateway implements ModelGatewayInterface, GatewayStatusAwareInterface
{
    private const string BASE_URL = 'https://api.openai.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LlmProviderCredentialService $credentialService,
    ) {
    }

    public function invoke(ModelRequest $request): ModelResponse
    {
        $config = $this->credentialService->resolveForCurrentUser('openai');
        if (!$config->isConfigured()) {
            return $this->notConfigured($request->outputSchemaVersion, $config->model);
        }

        try {
            $payload = $this->requestPayload($request, $config->model);
            $providerResponse = $this->httpClient->request('POST', self::BASE_URL . '/v1/responses', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $config->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
            $response = $providerResponse->toArray(false);
            $this->assertSuccessfulProviderResponse($providerResponse->getStatusCode(), $response);
        } catch (\Throwable $exception) {
            return new ModelResponse(
                provider: 'openai',
                model: $config->model,
                outputSchemaVersion: $request->outputSchemaVersion,
                output: ['content' => ''],
                available: false,
                message: 'OpenAI request failed: ' . $exception->getMessage(),
                warnings: [$exception->getMessage()],
            );
        }

        $text = $this->extractText($response);
        $parsed = $this->parseAssistantPayload($text);

        return new ModelResponse(
            provider: 'openai',
            model: (string) ($response['model'] ?? $config->model),
            outputSchemaVersion: $request->outputSchemaVersion,
            output: [
                'content' => $parsed['content'],
                'suggestions' => $parsed['suggestions'],
                'structured_output' => $parsed['structured_output'],
            ],
            available: true,
            message: 'OpenAI gateway invoked successfully.',
            provenance: [[
                'label' => 'OpenAI Responses API',
                'value' => $config->fromUserCredential ? 'user credential' : 'environment credential',
                'detail' => 'Response ID: ' . (string) ($response['id'] ?? 'n/a'),
            ]],
        );
    }

    public function status(AiTraceContext $traceContext, array $capabilities = []): GatewayStatus
    {
        $config = $this->credentialService->resolveForCurrentUser('openai');

        return new GatewayStatus(
            enabled: true,
            available: $config->isConfigured(),
            configured: $config->isConfigured(),
            realProvider: true,
            provider: 'openai',
            model: $config->model,
            message: $config->isConfigured()
                ? 'OpenAI gateway is configured. Use the provider test endpoint to verify the key.'
                : 'OpenAI gateway is missing an API key.',
            capabilities: [] !== $capabilities ? $capabilities : AssistantTask::interactiveCapabilities(),
        );
    }

    public function testConnection(): GatewayStatus
    {
        $config = $this->credentialService->resolveForCurrentUser('openai');
        if (!$config->isConfigured()) {
            return new GatewayStatus(true, false, false, true, 'openai', $config->model, 'OpenAI gateway is missing an API key.', []);
        }

        try {
            $providerResponse = $this->httpClient->request('GET', self::BASE_URL . '/v1/models/' . rawurlencode($config->model), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $config->apiKey,
                ],
            ]);
            $response = $providerResponse->toArray(false);
            $this->assertSuccessfulProviderResponse($providerResponse->getStatusCode(), $response);
        } catch (\Throwable $exception) {
            return new GatewayStatus(true, false, true, true, 'openai', $config->model, 'OpenAI connection failed: ' . $exception->getMessage(), []);
        }

        return new GatewayStatus(true, true, true, true, 'openai', $config->model, 'OpenAI connection verified.', []);
    }

    private function notConfigured(string $schemaVersion, string $model): ModelResponse
    {
        return new ModelResponse(
            provider: 'openai',
            model: $model,
            outputSchemaVersion: $schemaVersion,
            output: ['content' => ''],
            available: false,
            message: 'OpenAI gateway is missing an API key.',
            warnings: ['OpenAI gateway is missing an API key.'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(ModelRequest $request, string $model): array
    {
        return [
            'model' => $model,
            'instructions' => $this->instructions($request),
            'input' => $this->inputText($request),
            'max_output_tokens' => 1200,
        ];
    }

    private function instructions(ModelRequest $request): string
    {
        return "You are Metadatapp's governed metadata assistant. Return only a JSON object with keys content, suggestions, and structured_output. suggestions must be an array.";
    }

    private function inputText(ModelRequest $request): string
    {
        $messages = $request->input['messages'] ?? null;
        if (\is_array($messages)) {
            return implode("\n", array_map(static function (mixed $message): string {
                if (!\is_array($message)) {
                    return '';
                }

                return strtoupper((string) ($message['role'] ?? 'user')) . ': ' . (string) ($message['content'] ?? '');
            }, $messages));
        }

        return json_encode($request->input, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function assertSuccessfulProviderResponse(int $statusCode, array $response): void
    {
        if ($statusCode < 400) {
            return;
        }

        $message = $response['error']['message'] ?? $response['message'] ?? 'Provider returned HTTP ' . $statusCode . '.';

        throw new \RuntimeException(\is_scalar($message) ? (string) $message : 'Provider returned HTTP ' . $statusCode . '.');
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractText(array $response): string
    {
        if (isset($response['output_text']) && \is_string($response['output_text'])) {
            return $response['output_text'];
        }

        $chunks = [];
        foreach (($response['output'] ?? []) as $item) {
            if (!\is_array($item)) {
                continue;
            }
            foreach (($item['content'] ?? []) as $content) {
                if (\is_array($content) && isset($content['text']) && \is_string($content['text'])) {
                    $chunks[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $chunks));
    }

    /**
     * @return array{content: string, suggestions: list<array<string, mixed>>, structured_output: array<string, mixed>}
     */
    private function parseAssistantPayload(string $text): array
    {
        try {
            $decoded = json_decode($text, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = null;
        }

        if (!\is_array($decoded)) {
            return ['content' => $text, 'suggestions' => [], 'structured_output' => []];
        }

        return [
            'content' => isset($decoded['content']) && \is_scalar($decoded['content']) ? (string) $decoded['content'] : $text,
            'suggestions' => isset($decoded['suggestions']) && \is_array($decoded['suggestions']) ? array_values(array_filter($decoded['suggestions'], static fn (mixed $entry): bool => \is_array($entry))) : [],
            'structured_output' => isset($decoded['structured_output']) && \is_array($decoded['structured_output']) ? $decoded['structured_output'] : [],
        ];
    }
}
