<?php

declare(strict_types=1);

namespace App\AI\Gateway;

use App\AI\Credentials\LlmProviderCredentialService;
use App\AI\Governance\AiTraceContext;
use App\AI\Runtime\AssistantTask;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class AnthropicModelGateway implements ModelGatewayInterface, GatewayStatusAwareInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LlmProviderCredentialService $credentialService,
        private string $apiVersion = '2023-06-01',
    ) {
    }

    public function invoke(ModelRequest $request): ModelResponse
    {
        $config = $this->credentialService->resolveForCurrentUser('anthropic');
        if (!$config->isConfigured()) {
            return $this->notConfigured($request->outputSchemaVersion, $config->model);
        }

        $tools = $request->input['tools'] ?? null;
        $useTools = \is_array($tools) && [] !== $tools;

        try {
            $json = [
                'model' => $config->model,
                'system' => $useTools ? $this->agenticInstructions() : StructuredTaskPrompt::system($request->taskType),
                'messages' => $useTools ? $this->toolUseMessages($request) : $this->messages($request),
                'max_tokens' => StructuredTaskPrompt::maxTokens($request->taskType),
            ];
            if ($useTools) {
                $json['tools'] = $tools;
            }

            $providerResponse = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $config->apiKey,
                    'anthropic-version' => $this->apiVersion,
                    'Content-Type' => 'application/json',
                ],
                'json' => $json,
            ]);
            $response = $providerResponse->toArray(false);
            $this->assertSuccessfulProviderResponse($providerResponse->getStatusCode(), $response);
        } catch (\Throwable $exception) {
            return new ModelResponse(
                provider: 'anthropic',
                model: $config->model,
                outputSchemaVersion: $request->outputSchemaVersion,
                output: ['content' => ''],
                available: false,
                message: 'Anthropic request failed: ' . $exception->getMessage(),
                warnings: [$exception->getMessage()],
            );
        }

        if ($useTools) {
            // Native tool-use turn: surface tool calls for the orchestrator (and the
            // final text once the model stops requesting tools).
            $parsed = AnthropicToolUseTranslator::parseToolUse($response);
            $output = ['content' => $parsed['content'], 'tool_calls' => $parsed['tool_calls']];
        } else {
            $assistant = $this->parseAssistantPayload($this->extractText($response));
            $output = [
                'content' => $assistant['content'],
                'suggestions' => $assistant['suggestions'],
                'structured_output' => $assistant['structured_output'],
            ];
        }

        return new ModelResponse(
            provider: 'anthropic',
            model: (string) ($response['model'] ?? $config->model),
            outputSchemaVersion: $request->outputSchemaVersion,
            output: $output,
            available: true,
            message: 'Anthropic gateway invoked successfully.',
            provenance: [[
                'label' => 'Anthropic Messages API',
                'value' => $config->fromUserCredential ? 'user credential' : 'environment credential',
                'detail' => 'Message ID: ' . (string) ($response['id'] ?? 'n/a'),
            ]],
        );
    }

    private function agenticInstructions(): string
    {
        return "You are Metadatapp's governed lab assistant. Use the available tools to fulfil the request: search the connected reference databases, list the lab's imported library, standardize/harmonize references, and import results when asked. Call tools as needed; once you have the answer, reply with a concise plain-text summary. Never fabricate ids — only use ids returned by the tools.";
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toolUseMessages(ModelRequest $request): array
    {
        $base = \is_array($request->input['messages'] ?? null) ? $request->input['messages'] : [];
        $transcript = \is_array($request->input['toolTranscript'] ?? null) ? $request->input['toolTranscript'] : [];

        return AnthropicToolUseTranslator::messages(array_values($base), array_values($transcript));
    }

    public function status(AiTraceContext $traceContext, array $capabilities = []): GatewayStatus
    {
        $config = $this->credentialService->resolveForCurrentUser('anthropic');

        return new GatewayStatus(
            enabled: true,
            available: $config->isConfigured(),
            configured: $config->isConfigured(),
            realProvider: true,
            provider: 'anthropic',
            model: $config->model,
            message: $config->isConfigured()
                ? 'Anthropic gateway is configured. Use the provider test endpoint to verify the key.'
                : 'Anthropic gateway is missing an API key.',
            capabilities: [] !== $capabilities ? $capabilities : AssistantTask::interactiveCapabilities(),
        );
    }

    public function testConnection(): GatewayStatus
    {
        $config = $this->credentialService->resolveForCurrentUser('anthropic');
        if (!$config->isConfigured()) {
            return new GatewayStatus(true, false, false, true, 'anthropic', $config->model, 'Anthropic gateway is missing an API key.', []);
        }

        try {
            $providerResponse = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $config->apiKey,
                    'anthropic-version' => $this->apiVersion,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $config->model,
                    'messages' => [['role' => 'user', 'content' => 'Reply with OK.']],
                    'max_tokens' => 16,
                ],
            ]);
            $response = $providerResponse->toArray(false);
            $this->assertSuccessfulProviderResponse($providerResponse->getStatusCode(), $response);
        } catch (\Throwable $exception) {
            return new GatewayStatus(true, false, true, true, 'anthropic', $config->model, 'Anthropic connection failed: ' . $exception->getMessage(), []);
        }

        return new GatewayStatus(true, true, true, true, 'anthropic', $config->model, 'Anthropic connection verified.', []);
    }

    private function notConfigured(string $schemaVersion, string $model): ModelResponse
    {
        return new ModelResponse(
            provider: 'anthropic',
            model: $model,
            outputSchemaVersion: $schemaVersion,
            output: ['content' => ''],
            available: false,
            message: 'Anthropic gateway is missing an API key.',
            warnings: ['Anthropic gateway is missing an API key.'],
        );
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function messages(ModelRequest $request): array
    {
        $messages = $request->input['messages'] ?? null;
        if (\is_array($messages)) {
            $mapped = [];
            foreach ($messages as $message) {
                if (!\is_array($message)) {
                    continue;
                }

                $role = 'assistant' === ($message['role'] ?? null) ? 'assistant' : 'user';
                $content = trim((string) ($message['content'] ?? ''));
                if ('' !== $content) {
                    $mapped[] = ['role' => $role, 'content' => $content];
                }
            }

            if ([] !== $mapped) {
                return $mapped;
            }
        }

        return [['role' => 'user', 'content' => json_encode($request->input, \JSON_THROW_ON_ERROR)]];
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
        $chunks = [];
        foreach (($response['content'] ?? []) as $content) {
            if (\is_array($content) && 'text' === ($content['type'] ?? null) && isset($content['text'])) {
                $chunks[] = (string) $content['text'];
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
