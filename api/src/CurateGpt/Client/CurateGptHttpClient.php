<?php

declare(strict_types=1);

namespace App\CurateGpt\Client;

use App\CurateGpt\Client\Dto\CurateGptCandidateDto;
use App\CurateGpt\Client\Dto\CurateGptSearchRequestDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP adapter for the CurateGPT REST API.
 *
 * CurateGPT exposes a `/search` endpoint that accepts a query string and returns
 * a ranked list of ontology candidates.  The scoped client (`curate_gpt.client`)
 * is pre-configured with the base URL and an optional API key header in
 * `config/packages/framework.yaml`.
 *
 * @see https://github.com/monarch-initiative/curategpt (upstream project)
 */
readonly class CurateGptHttpClient implements CurateGptClientInterface
{
    public function __construct(
        private HttpClientInterface $curateGptClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * Calls `GET /search` with query params and maps the response to DTOs.
     *
     * @return CurateGptCandidateDto[]
     *
     * @throws \RuntimeException when the upstream service is unavailable or returns unexpected data
     */
    public function search(CurateGptSearchRequestDto $request): array
    {
        $params = [
            'query' => $request->query,
            'limit' => $request->limit,
        ];
        if (null !== $request->collection) {
            $params['collection'] = $request->collection;
        }

        $this->logger->debug('[CurateGpt] Sending search request.', ['query' => $request->query]);

        try {
            $response = $this->curateGptClient->request('GET', 'search', ['query' => $params]);
            $data = $response->toArray();
        } catch (ExceptionInterface $e) {
            $this->logger->error('[CurateGpt] Search request failed.', ['error' => $e->getMessage()]);
            throw new \RuntimeException('CurateGPT search request failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->parseCandidates($data);
    }

    /** @return CurateGptCandidateDto[] */
    private function parseCandidates(array $data): array
    {
        // CurateGPT wraps results in {"results": [...]} or returns a bare list.
        $items = \is_array($data['results'] ?? null) ? $data['results'] : (array) $data;

        $candidates = [];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $object = $item['object'] ?? $item;
            $candidates[] = new CurateGptCandidateDto(
                id: (string) ($object['id'] ?? $object['@id'] ?? ''),
                label: (string) ($object['label'] ?? $object['name'] ?? ''),
                score: (float) ($item['score'] ?? 0.0),
                metadata: array_filter($object, static fn ($k) => !\in_array($k, ['id', '@id', 'label', 'name'], true), \ARRAY_FILTER_USE_KEY),
            );
        }

        return $candidates;
    }
}
