<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\JaxPhenome\Client;

use App\Entity\ConnectedApp;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Read-only client for the JAX Mouse Phenome Database (MPD) public REST API.
 *
 * The MPD public REST API has no keyword measure-search endpoint. The phenome.jax.org
 * search box is a client-side typeahead over a static term list rendered into the
 * homepage, and selecting a term navigates to the ontology-measures view. This client
 * mirrors that behaviour: free text is resolved to ontology terms (from the same term
 * list, cached), and measures are then fetched via `measures_by_ontology`.
 *
 * Verified endpoints:
 *   - GET /api/pheno/measures_by_ontology/{ontTerm}  -> { count, measures[], ontology_terms[] }
 *   - GET /api/pheno/measureinfo/{measnum}           -> { count, measures_info[] }
 *   - GET /api/straininfo?name={name}                -> { jaxinfo[], mpdinfo[] }
 *
 * @see https://phenome.jax.org/about/api
 */
final class JaxPhenomeClient implements JaxPhenomeClientInterface
{
    private const string DEFAULT_BASE_URL = 'https://phenome.jax.org';
    private const string MEASURES_BY_ONTOLOGY_PATH = '/api/pheno/measures_by_ontology/';
    private const string MEASURE_INFO_PATH = '/api/pheno/measureinfo/';
    private const string STRAIN_INFO_PATH = '/api/straininfo';
    // MPD is a public catalogue of slowly-changing reference data; a short TTL
    // keeps the browser responsive without serving badly stale results.
    private const int READ_CACHE_TTL = 300;
    // The typeahead term list is large and changes rarely; cache it for a day.
    private const int TERM_LIST_CACHE_TTL = 86400;

    // Classic inbred strains used as the default "browse strains" view, since
    // MPD exposes strain detail by name (straininfo) rather than a list endpoint.
    private const array DEFAULT_STRAIN_NAMES = [
        'C57BL/6J', 'BALB/cJ', 'DBA/2J', 'A/J', 'C3H/HeJ', 'AKR/J',
        '129S1/SvImJ', 'CBA/J', 'FVB/NJ', 'NOD/ShiLtJ', 'NZO/HlLtJ', 'WSB/EiJ',
    ];

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

        return rtrim(trim($externalUrl), '/');
    }

    /**
     * Browse a default set of well-known strains. MPD has no list-all endpoint,
     * so this resolves a curated set of strain names through `straininfo`. Also
     * doubles as the connectivity probe used by the proxy's test-connection.
     *
     * @return array{totalItems: int, strains: list<array<string, mixed>>}
     */
    public function listStrains(ConnectedApp $connectedApp, int $limit = 20, int $offset = 0, bool $useCache = true): array
    {
        $names = \array_slice(self::DEFAULT_STRAIN_NAMES, max(0, $offset), max(1, $limit));

        return $this->cachedRead($connectedApp, 'strains', ['names' => $names], $useCache, function () use ($connectedApp, $names) {
            $strains = [];
            foreach ($names as $name) {
                $strain = $this->lookupStrain($connectedApp, $name);
                if ([] !== $strain) {
                    $strains[] = $strain;
                }
            }

            return [
                'totalItems' => \count(self::DEFAULT_STRAIN_NAMES),
                'strains' => $strains,
            ];
        });
    }

    /**
     * Resolve a free-text query to MPD ontology terms, then return the real
     * measures mapped to the best-matching term via `measures_by_ontology`. The
     * matched terms are returned alongside so the UI can offer refinements.
     *
     * @return array{totalItems: int, measures: list<array<string, mixed>>, ontologyTerms: list<array<string, string>>, resolvedTerm: array<string, mixed>|null}
     */
    public function searchMeasures(ConnectedApp $connectedApp, string $searchTerm, int $limit = 20, int $offset = 0, bool $useCache = true): array
    {
        $searchTerm = trim($searchTerm);
        if ('' === $searchTerm) {
            return ['totalItems' => 0, 'measures' => [], 'ontologyTerms' => [], 'resolvedTerm' => null];
        }

        $terms = $this->suggestOntologyTerms($connectedApp, $searchTerm, 10, $useCache);

        // Honour an explicitly-typed ontology id (e.g. "VT:0005311") first.
        $primary = $this->extractOntologyId($searchTerm) ?? ($terms[0]['id'] ?? null);

        $measures = [];
        $resolvedTerm = null;
        if (null !== $primary) {
            $byOntology = $this->measuresByOntology($connectedApp, $primary, $useCache);
            $measures = $byOntology['measures'];
            $resolvedTerm = $byOntology['ontologyTerm'];
        }

        return [
            'totalItems' => \count($measures),
            'measures' => \array_slice($measures, max(0, $offset), max(1, $limit)),
            'ontologyTerms' => $terms,
            'resolvedTerm' => $resolvedTerm,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeasure(ConnectedApp $connectedApp, string $measureId): array
    {
        $path = self::MEASURE_INFO_PATH . rawurlencode($measureId);

        return $this->cachedRead($connectedApp, $path, [], true, function () use ($connectedApp, $path) {
            $payload = $this->request($connectedApp, 'GET', $path);
            $info = $payload['measures_info'] ?? [];
            $first = \is_array($info) && isset($info[0]) && \is_array($info[0]) ? $info[0] : [];

            return [] === $first ? $payload : $this->normalizeMeasure($first);
        });
    }

    /**
     * Fetch a single strain's detail by nomenclature via `straininfo`.
     *
     * @return array<string, mixed>
     */
    public function lookupStrain(ConnectedApp $connectedApp, string $name): array
    {
        $payload = $this->request($connectedApp, 'GET', self::STRAIN_INFO_PATH, ['name' => $name]);

        $mpd = $payload['mpdinfo'][0] ?? null;
        $jax = $payload['jaxinfo'][0] ?? null;
        if (!\is_array($mpd) && !\is_array($jax)) {
            return [];
        }

        $mpd ??= [];
        $jax ??= [];

        return [
            'id' => $mpd['id'] ?? null,
            'strainid' => $mpd['id'] ?? null,
            'name' => $mpd['aname'] ?? ($jax['nomenclature'] ?? $name),
            'symbol' => $jax['nomenclature'] ?? ($mpd['aname'] ?? $name),
            'stocknum' => $jax['stocknum'] ?? null,
            'availability' => $jax['avl_status'] ?? ($mpd['jaxavl'] ?? null),
        ];
    }

    /**
     * Resolve free text to MPD ontology terms using the homepage typeahead list.
     *
     * @return list<array{id: string, descrip: string}>
     */
    public function suggestOntologyTerms(ConnectedApp $connectedApp, string $searchTerm, int $limit = 10, bool $useCache = true): array
    {
        $needle = mb_strtolower(trim($searchTerm));
        if ('' === $needle) {
            return [];
        }

        $terms = $this->ontologyTermIndex($connectedApp, $useCache);

        $matches = [];
        foreach ($terms as $term) {
            if (str_contains(mb_strtolower($term['descrip']), $needle) || str_contains(mb_strtolower($term['id']), $needle)) {
                $matches[] = $term;
                if (\count($matches) >= $limit) {
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * @return array{totalItems: int, measures: list<array<string, mixed>>, ontologyTerm: array<string, mixed>|null}
     */
    private function measuresByOntology(ConnectedApp $connectedApp, string $ontologyTerm, bool $useCache): array
    {
        $path = self::MEASURES_BY_ONTOLOGY_PATH . rawurlencode($ontologyTerm);

        return $this->cachedRead($connectedApp, $path, [], $useCache, function () use ($connectedApp, $path) {
            $payload = $this->request($connectedApp, 'GET', $path);
            $measures = array_map($this->normalizeMeasure(...), $this->normalizeList($payload['measures'] ?? []));
            $term = $payload['ontology_terms'][0] ?? null;

            return [
                'totalItems' => \is_int($payload['count'] ?? null) ? $payload['count'] : \count($measures),
                'measures' => $measures,
                'ontologyTerm' => \is_array($term) ? $term : null,
            ];
        });
    }

    /**
     * Build (and cache) the {id, descrip} ontology-term index parsed from the
     * MPD homepage typeahead `source` array.
     *
     * @return list<array{id: string, descrip: string}>
     */
    private function ontologyTermIndex(ConnectedApp $connectedApp, bool $useCache): array
    {
        $loader = function () use ($connectedApp): array {
            $html = $this->requestRaw($connectedApp, '/');
            // Entries look like:  "blood amino acid amount ... VT:0005311"
            if (!preg_match_all('/"([^"]+?)\s*\.\.\.\s*([A-Z]{2,}:\d+)"/', $html, $m, \PREG_SET_ORDER)) {
                return [];
            }

            $terms = [];
            $seen = [];
            foreach ($m as $match) {
                $id = $match[2];
                if (isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $terms[] = ['id' => $id, 'descrip' => trim($match[1])];
            }

            return $terms;
        };

        if (!$useCache || null === $this->cache) {
            return $loader();
        }

        $item = $this->cache->getItem('jax_phenome.ontology_term_index.' . hash('sha256', $this->getBaseUrl($connectedApp)));
        $cached = $item->get();
        if ($item->isHit() && \is_array($cached)) {
            /** @var list<array{id: string, descrip: string}> $cached */
            return $cached;
        }

        $value = $loader();
        if ([] !== $value) {
            $item->set($value)->expiresAfter(self::TERM_LIST_CACHE_TTL);
            $this->cache->save($item);
        }

        return $value;
    }

    private function extractOntologyId(string $value): ?string
    {
        return preg_match('/\b([A-Z]{2,}:\d+)\b/', $value, $m) ? $m[1] : null;
    }

    /**
     * Normalize an MPD measure record to the stable shape the browser reads,
     * while preserving the raw upstream fields.
     *
     * @param array<string, mixed> $measure
     *
     * @return array<string, mixed>
     */
    private function normalizeMeasure(array $measure): array
    {
        $measnum = $measure['measnum'] ?? $measure['keymeasnum'] ?? null;

        // MPD encodes some text/units as HTML entities (e.g. "&micro;L").
        $decode = static fn (mixed $v): mixed => \is_string($v) ? html_entity_decode($v, \ENT_QUOTES | \ENT_HTML5, 'UTF-8') : $v;

        return [
            // Raw upstream fields first, so the normalized keys below win.
            ...$measure,
            'id' => $measnum,
            'measnum' => $measnum,
            'name' => $decode($measure['descrip'] ?? null),
            'description' => $decode($measure['descrip2'] ?? ($measure['method'] ?? null)),
            'units' => $decode($measure['units'] ?? null),
            'projectsym' => $measure['projsym'] ?? null,
            'intervention' => $measure['intervention'] ?? null,
            'ageweeks' => $measure['ageweeks'] ?? null,
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
    private function cachedRead(ConnectedApp $connectedApp, string $path, array $query, bool $useCache, callable $loader): array
    {
        if (!$useCache || null === $this->cache) {
            return $loader();
        }

        $key = 'jax_phenome.read.' . hash('sha256', implode('|', [
            (string) $connectedApp->getId(),
            $this->getBaseUrl($connectedApp),
            $path,
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
    private function request(ConnectedApp $connectedApp, string $method, string $path, array $query = []): array
    {
        $options = [
            'headers' => ['Accept' => 'application/json'],
        ];

        if ([] !== $query) {
            $options['query'] = $query;
        }

        $response = $this->httpClient->request($method, $this->getBaseUrl($connectedApp) . $path, $options);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(\sprintf('JAX Phenome (MPD) API request failed with HTTP %d.', $response->getStatusCode()));
        }

        return $response->toArray(false);
    }

    /**
     * Fetch a raw (non-JSON) document body, e.g. the homepage used to build the
     * typeahead term index.
     */
    private function requestRaw(ConnectedApp $connectedApp, string $path): string
    {
        $response = $this->httpClient->request('GET', $this->getBaseUrl($connectedApp) . $path, [
            'headers' => ['Accept' => 'text/html'],
        ]);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(\sprintf('JAX Phenome (MPD) request failed with HTTP %d.', $response->getStatusCode()));
        }

        return $response->getContent(false);
    }
}
