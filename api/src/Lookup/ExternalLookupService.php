<?php

declare(strict_types=1);

namespace App\Lookup;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ExternalLookupService
{
    private const string ORCID_URL = 'https://pub.orcid.org/v3.0/expanded-search';
    private const string ROR_URL = 'https://api.ror.org/organizations';
    private const string OLS_URL = 'https://www.ebi.ac.uk/ols4/api/select';
    private const int ORCID_RESULT_LIMIT = 8;
    private const int LOOKUP_RESULT_LIMIT = 10;
    private const float LOOKUP_TIMEOUT_SECONDS = 5.0;
    private const float LOOKUP_MAX_DURATION_SECONDS = 10.0;

    /**
     * @var array<string, array{labels: list<string>, identifiers: list<string>}>
     */
    private const array SUPPORTED_SPECIES = [
        'mouse' => [
            'labels' => ['mouse', 'mus musculus'],
            'identifiers' => ['NCBITAXON:10090'],
        ],
        'rat' => [
            'labels' => ['rat', 'rattus norvegicus'],
            'identifiers' => ['NCBITAXON:10116'],
        ],
        'zebrafish' => [
            'labels' => ['zebrafish', 'danio rerio'],
            'identifiers' => ['NCBITAXON:7955'],
        ],
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return list<array{label: string, sublabel: ?string, value: string, externalId: ?string, scheme: string}>
     */
    public function search(string $source, string $query): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        return match ($source) {
            'orcid', 'orcid_public' => $this->searchOrcid($query),
            'ror' => $this->searchRor($query),
            'ols_ncbitaxon' => $this->searchNcbiTaxon($query),
            'ols_efo' => $this->searchEfo($query),
            default => throw new \InvalidArgumentException(\sprintf('Unsupported lookup source "%s".', $source)),
        };
    }

    /**
     * @return list<array{label: string, sublabel: ?string, value: string, externalId: ?string, scheme: string}>
     */
    private function searchOrcid(string $query): array
    {
        $orcidId = $this->normalizeOrcidId($query);
        if (null !== $orcidId) {
            return [[
                'label' => $orcidId,
                'sublabel' => 'ORCID iD',
                'value' => $orcidId,
                'externalId' => \sprintf('https://orcid.org/%s', $orcidId),
                'scheme' => 'ORCID',
            ]];
        }

        $payload = $this->httpClient->request('GET', self::ORCID_URL, [
            'timeout' => self::LOOKUP_TIMEOUT_SECONDS,
            'max_duration' => self::LOOKUP_MAX_DURATION_SECONDS,
            'query' => [
                'q' => $query,
                'rows' => self::ORCID_RESULT_LIMIT,
            ],
            'headers' => [
                'Accept' => 'application/vnd.orcid+json',
                'User-Agent' => 'Metadatapp/ORCID public lookup',
            ],
        ])->toArray(false);

        $results = $payload['expanded-result'] ?? [];
        if (!\is_array($results)) {
            return [];
        }

        $mapped = [];
        foreach (\array_slice($results, 0, self::ORCID_RESULT_LIMIT) as $result) {
            if (!\is_array($result)) {
                continue;
            }

            $orcid = trim((string) ($result['orcid-id'] ?? ''));
            if ('' === $orcid) {
                continue;
            }

            $givenNames = trim((string) ($result['given-names'] ?? ''));
            $familyNames = trim((string) ($result['family-names'] ?? ''));
            $label = trim($givenNames . ' ' . $familyNames);
            $institutions = $result['institution-name'] ?? [];
            $sublabel = \is_array($institutions) ? $this->toTrimmedStringOrNull($institutions[0] ?? null) : null;

            $mapped[] = [
                'label' => '' !== $label ? $label : $orcid,
                'sublabel' => $sublabel,
                'value' => $orcid,
                'externalId' => \sprintf('https://orcid.org/%s', $orcid),
                'scheme' => 'ORCID',
            ];
        }

        return $mapped;
    }

    private function normalizeOrcidId(string $query): ?string
    {
        $candidate = preg_replace('#^https?://orcid\.org/#i', '', trim($query));
        if (!\is_string($candidate)) {
            return null;
        }

        $candidate = strtoupper($candidate);

        return 1 === preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $candidate) ? $candidate : null;
    }

    /**
     * @return list<array{label: string, sublabel: ?string, value: string, externalId: ?string, scheme: string}>
     */
    private function searchRor(string $query): array
    {
        $payload = $this->httpClient->request('GET', self::ROR_URL, [
            'timeout' => self::LOOKUP_TIMEOUT_SECONDS,
            'max_duration' => self::LOOKUP_MAX_DURATION_SECONDS,
            'query' => ['query' => $query],
        ])->toArray(false);

        $items = $payload['items'] ?? [];
        if (!\is_array($items)) {
            return [];
        }

        $mapped = [];
        foreach (\array_slice($items, 0, self::LOOKUP_RESULT_LIMIT) as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $label = $this->extractRorLabel($item);
            if (null === $label) {
                continue;
            }

            $mapped[] = [
                'label' => $label,
                'sublabel' => $this->buildRorSublabel($item),
                'value' => $label,
                'externalId' => $this->toTrimmedStringOrNull($item['id'] ?? null),
                'scheme' => 'ROR',
            ];
        }

        return $mapped;
    }

    /**
     * @return list<array{label: string, sublabel: ?string, value: string, externalId: ?string, scheme: string}>
     */
    private function searchNcbiTaxon(string $query): array
    {
        $payload = $this->httpClient->request('GET', self::OLS_URL, [
            'timeout' => self::LOOKUP_TIMEOUT_SECONDS,
            'max_duration' => self::LOOKUP_MAX_DURATION_SECONDS,
            'query' => [
                'q' => $query,
                'ontology' => 'ncbitaxon',
                'rows' => self::LOOKUP_RESULT_LIMIT,
            ],
        ])->toArray(false);

        $docs = $payload['response']['docs'] ?? [];
        if (!\is_array($docs)) {
            return [];
        }

        $mapped = [];
        foreach ($docs as $doc) {
            if (!\is_array($doc)) {
                continue;
            }

            $value = $this->mapSpeciesValue($doc);
            if (null === $value || isset($mapped[$value])) {
                continue;
            }

            $label = trim((string) ($doc['label'] ?? ''));
            if ('' === $label) {
                continue;
            }

            $mapped[$value] = [
                'label' => $label,
                'sublabel' => $this->toTrimmedStringOrNull($doc['obo_id'] ?? $doc['short_form'] ?? null),
                'value' => $value,
                'externalId' => $this->normalizeOntologyExternalId($doc),
                'scheme' => 'NCBITaxon',
            ];
        }

        return array_values($mapped);
    }

    /**
     * @return list<array{label: string, sublabel: ?string, value: string, externalId: ?string, scheme: string}>
     */
    private function searchEfo(string $query): array
    {
        $payload = $this->httpClient->request('GET', self::OLS_URL, [
            'timeout' => self::LOOKUP_TIMEOUT_SECONDS,
            'max_duration' => self::LOOKUP_MAX_DURATION_SECONDS,
            'query' => [
                'q' => $query,
                'ontology' => 'efo',
                'rows' => self::LOOKUP_RESULT_LIMIT,
            ],
        ])->toArray(false);

        $docs = $payload['response']['docs'] ?? [];
        if (!\is_array($docs)) {
            return [];
        }

        $mapped = [];
        foreach ($docs as $doc) {
            if (!\is_array($doc)) {
                continue;
            }

            $label = trim((string) ($doc['label'] ?? ''));
            if ('' === $label) {
                continue;
            }

            $mapped[] = [
                'label' => $label,
                'sublabel' => $this->toTrimmedStringOrNull($doc['obo_id'] ?? $doc['short_form'] ?? null),
                'value' => $label,
                'externalId' => $this->normalizeOntologyExternalId($doc),
                'scheme' => 'EFO',
            ];
        }

        return $mapped;
    }

    private function mapSpeciesValue(array $doc): ?string
    {
        $label = strtolower(trim((string) ($doc['label'] ?? '')));
        $identifier = strtoupper(trim((string) ($doc['obo_id'] ?? $doc['short_form'] ?? '')));

        foreach (self::SUPPORTED_SPECIES as $value => $supportedSpecies) {
            if (\in_array($label, $supportedSpecies['labels'], true) || \in_array($identifier, $supportedSpecies['identifiers'], true)) {
                return $value;
            }
        }

        return null;
    }

    private function extractRorLabel(array $item): ?string
    {
        $names = $item['names'] ?? null;
        if (!\is_array($names)) {
            return null;
        }

        foreach (['ror_display', 'label'] as $type) {
            foreach ($names as $name) {
                if (!\is_array($name)) {
                    continue;
                }

                $types = $name['types'] ?? [];
                if (!\is_array($types) || !\in_array($type, $types, true)) {
                    continue;
                }

                $value = $this->toTrimmedStringOrNull($name['value'] ?? null);
                if (null !== $value) {
                    return $value;
                }
            }
        }

        foreach ($names as $name) {
            if (!\is_array($name)) {
                continue;
            }

            $value = $this->toTrimmedStringOrNull($name['value'] ?? null);
            if (null !== $value) {
                return $value;
            }
        }

        return null;
    }

    private function buildRorSublabel(array $item): ?string
    {
        $city = null;
        $addresses = $item['addresses'] ?? null;
        if (\is_array($addresses) && \is_array($addresses[0] ?? null)) {
            $city = $this->toTrimmedStringOrNull($addresses[0]['city'] ?? null);
        }

        $country = null;
        $countryPayload = $item['country'] ?? null;
        if (\is_array($countryPayload)) {
            $country = $this->toTrimmedStringOrNull($countryPayload['country_name'] ?? null);
        }

        if (null !== $city && null !== $country) {
            return $city . ', ' . $country;
        }

        return $city ?? $country;
    }

    private function normalizeOntologyExternalId(array $doc): ?string
    {
        $iri = $this->toTrimmedStringOrNull($doc['iri'] ?? null);
        if (null !== $iri) {
            return $iri;
        }

        $oboId = $this->toTrimmedStringOrNull($doc['obo_id'] ?? $doc['short_form'] ?? null);
        if (null === $oboId || 1 !== preg_match('/^[A-Za-z][A-Za-z0-9]*:\d+$/', $oboId)) {
            return null;
        }

        return 'http://purl.obolibrary.org/obo/' . str_replace(':', '_', $oboId);
    }

    private function toTrimmedStringOrNull(mixed $value): ?string
    {
        if (!\is_scalar($value)) {
            return null;
        }

        $string = trim((string) $value);

        return '' === $string ? null : $string;
    }
}
