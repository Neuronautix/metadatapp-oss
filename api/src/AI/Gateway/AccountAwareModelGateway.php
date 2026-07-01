<?php

declare(strict_types=1);

namespace App\AI\Gateway;

use App\AI\Governance\AiTraceContext;
use App\Entity\Account;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Gateway that reads LLM provider configuration from the current user's Account
 * and dispatches to OpenAI, Anthropic, or Google Gemini at request time.
 *
 * Activated by setting AI_MODEL_PROVIDER=account in the environment.
 *
 * The API key is read directly from the Account entity and is never logged
 * or returned through the API (only a masked preview is exposed).
 */
final readonly class AccountAwareModelGateway implements ModelGatewayInterface, GatewayStatusAwareInterface
{
    private const OPENAI_BASE_URL = 'https://api.openai.com';
    private const ANTHROPIC_BASE_URL = 'https://api.anthropic.com';
    private const GEMINI_BASE_URL = 'https://generativelanguage.googleapis.com';

    private const DEFAULT_MODELS = [
        'openai' => 'gpt-4o-mini',
        'anthropic' => 'claude-3-5-haiku-20241022',
        'gemini' => 'gemini-1.5-flash',
    ];

    private const SUPPORTED_PROVIDERS = ['openai', 'anthropic', 'gemini'];

    public function __construct(
        private Security $security,
        private HttpClientInterface $httpClient,
    ) {
    }

    public function invoke(ModelRequest $request): ModelResponse
    {
        $account = $this->resolveAccount();
        if (null === $account) {
            return $this->notConfiguredResponse('No authenticated account found.');
        }

        $provider = $account->getLlmProvider();
        $apiKey = $account->getLlmApiKey();

        if (null === $provider || '' === $provider || null === $apiKey || '' === $apiKey) {
            return $this->notConfiguredResponse('No LLM provider is configured for this account.');
        }

        $model = $account->getLlmModel() ?? self::DEFAULT_MODELS[$provider] ?? null;
        if (null === $model) {
            return $this->notConfiguredResponse(\sprintf('Unknown provider "%s".', $provider));
        }

        $systemPrompt = $this->buildSystemPrompt($request);
        $userMessage = $this->buildUserMessage($request);

        try {
            $rawContent = match ($provider) {
                'openai' => $this->callOpenAi($apiKey, $model, $systemPrompt, $userMessage),
                'anthropic' => $this->callAnthropic($apiKey, $model, $systemPrompt, $userMessage),
                'gemini' => $this->callGemini($apiKey, $model, $systemPrompt, $userMessage),
                default => throw new \InvalidArgumentException(\sprintf('Unsupported LLM provider "%s".', $provider)),
            };
        } catch (ClientException $exception) {
            $statusCode = $exception->getResponse()->getStatusCode();
            if (401 === $statusCode || 403 === $statusCode) {
                return $this->errorResponse($provider, $model, 'Authentication failed — check your API key.');
            }

            return $this->errorResponse($provider, $model, \sprintf('Provider returned HTTP %d.', $statusCode));
        } catch (TransportException $exception) {
            return $this->errorResponse($provider, $model, 'Could not reach the LLM provider endpoint: ' . $exception->getMessage());
        } catch (\Throwable $exception) {
            return $this->errorResponse($provider, $model, 'Unexpected error: ' . $exception->getMessage());
        }

        return $this->buildModelResponse($provider, $model, $request, $rawContent);
    }

    public function status(AiTraceContext $traceContext, array $capabilities = []): GatewayStatus
    {
        $account = $this->resolveAccount();
        $provider = $account?->getLlmProvider();
        $apiKey = $account?->getLlmApiKey();
        $hasKey = null !== $apiKey && '' !== $apiKey;
        $configured = null !== $provider && $hasKey && \in_array($provider, self::SUPPORTED_PROVIDERS, true);
        $model = $account?->getLlmModel() ?? (null !== $provider ? (self::DEFAULT_MODELS[$provider] ?? 'unknown') : 'unknown');

        return new GatewayStatus(
            enabled: true,
            available: $configured,
            configured: $configured,
            realProvider: true,
            provider: $provider ?? 'account',
            model: $model,
            message: $configured
                ? \sprintf('Using %s (%s) from account configuration.', $provider, $model)
                : 'No LLM provider is configured for this account. Add one in Settings → AI Provider.',
            capabilities: $capabilities,
        );
    }

    // -------------------------------------------------------------------------
    // Provider adapters
    // -------------------------------------------------------------------------

    private function callOpenAi(string $apiKey, string $model, string $systemPrompt, string $userMessage): string
    {
        $response = $this->httpClient->request('POST', self::OPENAI_BASE_URL . '/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'max_tokens' => 2048,
            ],
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return (string) ($data['choices'][0]['message']['content'] ?? '');
    }

    private function callAnthropic(string $apiKey, string $model, string $systemPrompt, string $userMessage): string
    {
        $response = $this->httpClient->request('POST', self::ANTHROPIC_BASE_URL . '/v1/messages', [
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'max_tokens' => 2048,
            ],
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return (string) ($data['content'][0]['text'] ?? '');
    }

    private function callGemini(string $apiKey, string $model, string $systemPrompt, string $userMessage): string
    {
        $url = self::GEMINI_BASE_URL . '/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    [
                        'parts' => [['text' => $userMessage]],
                    ],
                ],
            ],
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray();

        return (string) ($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    // -------------------------------------------------------------------------
    // Prompt construction
    // -------------------------------------------------------------------------

    private function buildSystemPrompt(ModelRequest $request): string
    {
        return 'You are a helpful metadata assistant for a scientific data management platform called Metadatapp. '
            . 'You help researchers manage, curate, and improve their experimental metadata. '
            . 'Your responses should be concise, accurate, and actionable. '
            . 'Always clarify that your suggestions are for review only and should be verified by the user.';
    }

    private function buildUserMessage(ModelRequest $request): string
    {
        return match ($request->taskType) {
            'assistant_chat' => $this->buildChatMessage($request->input),
            'suggest_form_inputs' => $this->buildSuggestMessage($request->input),
            'explain_missing_metadata' => $this->buildExplainMessage($request->input),
            'draft_query_filters' => $this->buildDraftFiltersMessage($request->input),
            'generate_export_content' => $this->buildExportContentMessage($request->input),
            default => $this->buildGenericMessage($request->input),
        };
    }

    /**
     * @param array<string, mixed> $input
     */
    private function buildChatMessage(array $input): string
    {
        $messages = \is_array($input['messages'] ?? null) ? $input['messages'] : [];
        $lines = [];
        foreach ($messages as $message) {
            if (!\is_array($message)) {
                continue;
            }
            $role = (string) ($message['role'] ?? 'user');
            $content = (string) ($message['content'] ?? '');
            if ('system' !== $role && '' !== $content) {
                $lines[] = \sprintf('[%s]: %s', strtoupper($role), $content);
            }
        }

        $contextParts = [];
        if (\is_array($input['context'] ?? null)) {
            $contextParts[] = 'Context: ' . json_encode($input['context'], \JSON_THROW_ON_ERROR);
        }

        return implode("\n", array_merge($contextParts, $lines));
    }

    /**
     * @param array<string, mixed> $input
     */
    private function buildSuggestMessage(array $input): string
    {
        $contextType = (string) ($input['contextType'] ?? 'unknown');
        $context = \is_array($input['context'] ?? null) ? json_encode($input['context'], \JSON_THROW_ON_ERROR) : '{}';
        $prompt = (string) ($input['prompt'] ?? '');

        return \sprintf(
            "Suggest appropriate form field values for a %s record.\n\nExisting context:\n%s\n\n%s\n\n"
            . 'Return a JSON object with field names as keys and suggested values as values, preceded by a short explanation.',
            $contextType,
            $context,
            '' !== $prompt ? 'Additional request: ' . $prompt : '',
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function buildExplainMessage(array $input): string
    {
        $contextType = (string) ($input['contextType'] ?? 'unknown');
        $context = \is_array($input['context'] ?? null) ? json_encode($input['context'], \JSON_THROW_ON_ERROR) : '{}';

        return \sprintf(
            "Explain what metadata is missing or incomplete for this %s record, and why it matters for scientific reproducibility.\n\nRecord data:\n%s",
            $contextType,
            $context,
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function buildDraftFiltersMessage(array $input): string
    {
        $prompt = (string) ($input['prompt'] ?? '');
        $context = \is_array($input['context'] ?? null) ? json_encode($input['context'], \JSON_THROW_ON_ERROR) : '{}';

        return \sprintf(
            "Draft search filter criteria based on this natural-language request.\n\nRequest: %s\nContext: %s\n\n"
            . 'Return a JSON object with filter field names and values, preceded by a short explanation.',
            $prompt,
            $context,
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function buildExportContentMessage(array $input): string
    {
        $contextType = (string) ($input['contextType'] ?? 'unknown');
        $context = \is_array($input['context'] ?? null) ? json_encode($input['context'], \JSON_THROW_ON_ERROR) : '{}';

        return \sprintf(
            "Generate export-ready structured content (abstract, highlights, keywords, metadata checklist) for this %s record.\n\nRecord data:\n%s\n\n"
            . 'Return a JSON object with keys: abstract, highlights (array), keywords (array), metadataChecklist (array).',
            $contextType,
            $context,
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function buildGenericMessage(array $input): string
    {
        return json_encode($input, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
    }

    // -------------------------------------------------------------------------
    // Response helpers
    // -------------------------------------------------------------------------

    private function buildModelResponse(string $provider, string $model, ModelRequest $request, string $rawContent): ModelResponse
    {
        // Try to extract structured JSON from the response
        $structuredOutput = [];
        $suggestions = [];
        $content = $rawContent;

        if (str_contains($rawContent, '{') || str_contains($rawContent, '[')) {
            // Attempt to parse embedded JSON block
            if (preg_match('/```(?:json)?\s*(\{[\s\S]*?\}|\[[\s\S]*?\])\s*```/i', $rawContent, $matches)) {
                try {
                    $parsed = json_decode($matches[1], true, 512, \JSON_THROW_ON_ERROR);
                    if (\is_array($parsed)) {
                        $structuredOutput = $parsed;
                    }
                } catch (\JsonException) {
                    // Not parseable — keep raw content
                }
            }
        }

        return new ModelResponse(
            provider: $provider,
            model: $model,
            outputSchemaVersion: $request->outputSchemaVersion,
            output: [
                'content' => $content,
                'suggestions' => $suggestions,
                'structured_output' => $structuredOutput,
            ],
            available: true,
            message: \sprintf('Response from %s (%s).', $provider, $model),
        );
    }

    private function notConfiguredResponse(string $message): ModelResponse
    {
        return new ModelResponse(
            provider: 'account',
            model: 'unconfigured',
            outputSchemaVersion: 'ai.assistant.v1',
            output: [
                'content' => $message,
                'suggestions' => [],
                'structured_output' => [],
            ],
            available: false,
            message: $message,
            warnings: [$message],
        );
    }

    private function errorResponse(string $provider, string $model, string $message): ModelResponse
    {
        return new ModelResponse(
            provider: $provider,
            model: $model,
            outputSchemaVersion: 'ai.assistant.v1',
            output: [
                'content' => 'An error occurred while contacting the AI provider.',
                'suggestions' => [],
                'structured_output' => [],
            ],
            available: false,
            message: $message,
            warnings: [$message],
        );
    }

    private function resolveAccount(): ?Account
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->hasAccount() ? $user->getAccount() : null;
    }
}
