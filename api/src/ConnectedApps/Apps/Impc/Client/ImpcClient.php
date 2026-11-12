<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Impc\Client;

use App\Entity\ConnectedApp;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Read-only client for the IMPReSS (International Mouse Phenotyping Resource of
 * Standardised Screens) catalogue.
 *
 * The IMPReSS REST API (api.mousephenotype.org/impress) is a deeply-nested
 * pipeline → schedule → procedure → parameter tree with no keyword search. The
 * IMPC instead publishes a public Solr `pipeline` core that indexes one document
 * per parameter with the full pipeline/procedure/parameter context and supports
 * real keyword search, faceting and grouping. This client uses that core for
 * search, procedure detail (grouped parameters) and the pipeline list.
 *
 * Verified queries (base `https://www.ebi.ac.uk/mi/impc/solr/pipeline`):
 *   - /select?q=<term>&group.field=procedure_stable_id    -> procedures matching a term
 *   - /select?q=procedure_stable_id:"<id>"                -> a procedure's parameters
 *   - /select?q=*:*&group.field=pipeline_stable_id        -> pipelines
 *
 * @see https://www.mousephenotype.org/impress/
 * @see https://www.ebi.ac.uk/mi/impc/solr/pipeline/select
 */
final class ImpcClient implements ImpcClientInterface
{
    private const string DEFAULT_BASE_URL = 'https://www.ebi.ac.uk/mi/impc/solr/pipeline';
    // The previous (non-functional) default; treated as "use the Solr core" so
    // connected apps saved before this change keep working without re-editing.
    private const string LEGACY_BASE_URL = 'https://api.mousephenotype.org';
    private const string SELECT_PATH = '/select';
    // IMPReSS is slowly-changing reference data; a short TTL keeps the browser
    // responsive without serving badly stale results.
    private const int READ_CACHE_TTL = 300;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function getBaseUrl(ConnectedApp $connectedApp): string
    {
        $externalUrl = $connectedApp->getExternalUrl();
        if (null === $externalUrl || '' === trim($externalUrl)) {
            return self::DEFAULT_BASE_URL;
        }

        $externalUrl = rtrim(trim($externalUrl), '/');

        return self::LEGACY_BASE_URL === $externalUrl ? self::DEFAULT_BASE_URL : $externalUrl;
    }

    /**
     * @return array{totalItems: int, pipelines: list<array<string, mixed>>}
     */
    public function listPipelines(ConnectedApp $connectedApp, int $limit = 20, int $offset = 0, bool $useCache = true): array
    {
        $query = [
            'q' => '*:*',
            'group' => 'true',
            'group.field' => 'pipeline_stable_id',
            'group.ngroups' => 'true',
            'group.limit' => 1,
            'rows' => $limit,
            'start' => $offset,
            'fl' => 'pipeline_stable_id,pipeline_stable_key,pipeline_name,pipeline_id',
            'wt' => 'json',
        ];

        return $this->cachedRead($connectedApp, 'pipelines', $query, $useCache, function () use ($connectedApp, $query) {
            $payload = $this->request($connectedApp, $query);
            $group = $payload['grouped']['pipeline_stable_id'] ?? [];
            $pipelines = [];
            foreach ($group['groups'] ?? [] as $entry) {
                $doc = $entry['doclist']['docs'][0] ?? null;
                if (\is_array($doc)) {
                    $pipelines[] = [
                        'stableKey' => $doc['pipeline_stable_id'] ?? null,
                        'name' => $doc['pipeline_name'] ?? null,
                        'description' => null,
                        'pipelineId' => $doc['pipeline_id'] ?? null,
                    ];
                }
            }

            return [
                'totalItems' => \is_int($group['ngroups'] ?? null) ? $group['ngroups'] : \count($pipelines),
                'pipelines' => $pipelines,
            ];
        });
    }

    /**
     * Search procedures by keyword over their name and the names of their
     * standardized parameters, grouped so each procedure appears once.
     *
     * @return array{totalItems: int, procedures: list<array<string, mixed>>}
     */
    public function searchProcedures(ConnectedApp $connectedApp, string $searchTerm, int $limit = 20, int $offset = 0, bool $useCache = true): array
    {
        $query = [
            'q' => $this->buildSearchQuery($searchTerm),
            'group' => 'true',
            'group.field' => 'procedure_stable_id',
            'group.ngroups' => 'true',
            'group.limit' => 1,
            'rows' => $limit,
            'start' => $offset,
            'fl' => 'procedure_stable_id,procedure_stable_key,procedure_name,procedure_id,pipeline_stable_id,pipeline_name',
            'sort' => 'procedure_name asc',
            'wt' => 'json',
        ];

        return $this->cachedRead($connectedApp, 'procedures.search', $query, $useCache, function () use ($connectedApp, $query) {
            $payload = $this->request($connectedApp, $query);
            $group = $payload['grouped']['procedure_stable_id'] ?? [];
            $procedures = [];
            foreach ($group['groups'] ?? [] as $entry) {
                $doc = $entry['doclist']['docs'][0] ?? null;
                if (\is_array($doc)) {
                    $procedures[] = $this->normalizeProcedureHeader($doc);
                }
            }

            return [
                'totalItems' => \is_int($group['ngroups'] ?? null) ? $group['ngroups'] : \count($procedures),
                'procedures' => $procedures,
            ];
        });
    }

    /**
     * Fetch a single IMPReSS procedure (by its stable id, e.g. `IMPC_OFD_001`)
     * and its standardized parameters, assembled from the parameter documents.
     *
     * @return array<string, mixed>
     */
    public function getProcedure(ConnectedApp $connectedApp, string $procedureId): array
    {
        $query = [
            'q' => 'procedure_stable_id:"' . $this->escapeSolrLiteral($procedureId) . '"',
            'rows' => 1000,
            'fl' => 'parameter_stable_id,parameter_stable_key,parameter_name,data_type,unit_x,required,experiment_level,'
                . 'mp_id,mp_term,procedure_stable_id,procedure_stable_key,procedure_name,procedure_id,pipeline_stable_id,pipeline_name',
            'sort' => 'parameter_name asc',
            'wt' => 'json',
        ];

        return $this->cachedRead($connectedApp, 'procedure.' . $procedureId, $query, true, function () use ($connectedApp, $query) {
            $payload = $this->request($connectedApp, $query);
            $docs = $this->normalizeList($payload['response']['docs'] ?? []);
            if ([] === $docs) {
                return [];
            }

            $procedure = $this->normalizeProcedureHeader($docs[0]);
            $procedure['parameters'] = array_map($this->normalizeParameter(...), $docs);

            return $procedure;
        });
    }

    /**
     * Build a Solr query that matches the term across procedure and parameter
     * names. Each whitespace-separated word must match (AND), as a wildcard
     * substring, so multi-word queries narrow rather than broaden.
     */
    private function buildSearchQuery(string $searchTerm): string
    {
        $words = preg_split('/\s+/', trim(mb_strtolower($searchTerm))) ?: [];
        $clauses = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/u', '', $word) ?? '';
            if ('' === $word) {
                continue;
            }
            // Substring wildcard against the analyzed default field, which is
            // lowercased at index time — so a lowercase wildcard matches case
            // -insensitively (e.g. "open" matches "Open Field"). The explicit
            // procedure_name/parameter_name fields are case-sensitive *string*
            // fields, so a lowercased wildcard against them never matches (it
            // returned zero results for "open field"); the default field spans
            // both procedure and parameter text anyway.
            $clauses[] = \sprintf('*%s*', $word);
        }

        return [] === $clauses ? '*:*' : implode(' AND ', $clauses);
    }

    private function escapeSolrLiteral(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\"'], $value);
    }

    /**
     * @param array<string, mixed> $doc
     *
     * @return array<string, mixed>
     */
    private function normalizeProcedureHeader(array $doc): array
    {
        $stableId = $doc['procedure_stable_id'] ?? null;

        return [
            // The frontend keys the detail lookup on procedureId, so expose the
            // stable id there (getProcedure queries by procedure_stable_id).
            'procedureId' => $stableId,
            'procID' => $doc['procedure_id'] ?? null,
            'stableKey' => $stableId,
            'procedureKey' => $doc['procedure_stable_key'] ?? null,
            'name' => $doc['procedure_name'] ?? null,
            'pipelineKey' => $doc['pipeline_stable_id'] ?? null,
            'pipelineName' => $doc['pipeline_name'] ?? null,
            'description' => null,
        ];
    }

    /**
     * @param array<string, mixed> $doc
     *
     * @return array<string, mixed>
     */
    private function normalizeParameter(array $doc): array
    {
        $ontologyMapping = [];
        $mpIds = (array) ($doc['mp_id'] ?? []);
        $mpTerms = (array) ($doc['mp_term'] ?? []);
        foreach ($mpTerms as $i => $term) {
            $ontologyMapping[] = [
                'id' => $mpIds[$i] ?? null,
                'term' => $term,
            ];
        }

        return [
            'stableKey' => $doc['parameter_stable_id'] ?? null,
            'name' => $doc['parameter_name'] ?? null,
            'datatype' => $doc['data_type'] ?? null,
            'unit' => $doc['unit_x'] ?? null,
            'isMetadata' => 'procedureMetadata' === ($doc['experiment_level'] ?? null),
            'required' => (bool) ($doc['required'] ?? false),
            'ontologyMapping' => $ontologyMapping,
        ];
    }

    /**
     * Cache a pure GET read under a key scoped to the connection, path and query.
     * No-ops when caching is disabled for this call or no pool is configured.
     *
     * @template T of array<string, mixed>
     *
     * @param array<string, mixed> $query
     * @param callable(): T        $loader
     *
     * @return T
     */
    private function cachedRead(ConnectedApp $connectedApp, string $cacheKey, array $query, bool $useCache, callable $loader): array
    {
        if (!$useCache || null === $this->cache) {
            return $loader();
        }

        $key = 'impc.read.' . hash('sha256', implode('|', [
            (string) $connectedApp->getId(),
            $this->getBaseUrl($connectedApp),
            $cacheKey,
            json_encode($query),
        ]));

        $item = $this->cache->getItem($key);
        $cached = $item->get();
        if ($item->isHit() && \is_array($cached)) {
            /** @var T $cached */
            return $cached;
        }

        $value = $loader();
        $item->set($value)->expiresAfter(self::READ_CACHE_TTL);
        $this->cache->save($item);

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (\is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function request(ConnectedApp $connectedApp, array $query): array
    {
        $response = $this->httpClient->request('GET', $this->getBaseUrl($connectedApp) . self::SELECT_PATH, [
            'headers' => ['Accept' => 'application/json'],
            'query' => $query,
        ]);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(\sprintf('IMPReSS (IMPC) API request failed with HTTP %d.', $response->getStatusCode()));
        }

        return $response->toArray(false);
    }
}
