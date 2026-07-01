<?php

declare(strict_types=1);

namespace App\Crosswalk\Cde;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Queries the NIH CDE repository API (https://cde.nlm.nih.gov/api). NIH CDE is one
 * provider among several. Degrades gracefully: on no connectivity / non-2xx /
 * malformed payloads it returns [] or null rather than throwing.
 */
final class NihCdeProvider implements CdeProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $baseUrl = 'https://cde.nlm.nih.gov',
    ) {
    }

    public function key(): string
    {
        return 'nih_cde';
    }

    public function searchCdes(string $query): array
    {
        $payload = $this->safeJson('POST', '/server/de/search', json: ['searchTerm' => $query]);
        if (null === $payload) {
            return [];
        }

        $items = $payload['cdes'] ?? $payload['items'] ?? $payload['results'] ?? [];
        if (!\is_array($items)) {
            return [];
        }

        $candidates = [];
        foreach ($items as $item) {
            if (\is_array($item)) {
                /** @var array<string, mixed> $item */
                $candidates[] = $this->mapCde($item);
            }
        }

        return $candidates;
    }

    public function getCde(string $externalId): ?CdeCandidate
    {
        $payload = $this->safeJson('GET', '/api/de/' . rawurlencode($externalId));
        if (null === $payload) {
            return null;
        }

        return $this->mapCde($payload);
    }

    public function searchForms(string $query): array
    {
        $payload = $this->safeJson('POST', '/server/form/search', json: ['searchTerm' => $query]);
        if (null === $payload) {
            return [];
        }

        $items = $payload['forms'] ?? $payload['items'] ?? $payload['results'] ?? [];
        if (!\is_array($items)) {
            return [];
        }

        $candidates = [];
        foreach ($items as $item) {
            if (\is_array($item)) {
                /** @var array<string, mixed> $item */
                $candidates[] = $this->mapForm($item);
            }
        }

        return $candidates;
    }

    public function getForm(string $externalId): ?FormCandidate
    {
        $payload = $this->safeJson('GET', '/api/form/' . rawurlencode($externalId));
        if (null === $payload) {
            return null;
        }

        return $this->mapForm($payload);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function mapCde(array $item): CdeCandidate
    {
        $tinyId = $this->str($item['tinyId'] ?? $item['_id'] ?? null);
        $label = $this->designation($item) ?? ($tinyId ?? 'NIH CDE');
        $version = $this->str($item['version'] ?? null);

        $datatype = null;
        $unit = null;
        $permissible = [];
        $valueDomain = $item['valueDomain'] ?? null;
        if (\is_array($valueDomain)) {
            $datatype = $this->str($valueDomain['datatype'] ?? null);
            $unit = $this->str($valueDomain['uom'] ?? null);
            $pvs = $valueDomain['permissibleValues'] ?? [];
            if (\is_array($pvs)) {
                foreach ($pvs as $pv) {
                    if (!\is_array($pv)) {
                        continue;
                    }
                    $value = $this->str($pv['permissibleValue'] ?? $pv['valueMeaningName'] ?? null);
                    if (null === $value) {
                        continue;
                    }
                    $permissible[] = array_filter([
                        'value' => $value,
                        'code' => $this->str($pv['valueMeaningCode'] ?? $pv['code'] ?? null),
                        'ontologyTerm' => $this->str($pv['codeSystemName'] ?? null),
                    ], static fn ($v): bool => null !== $v);
                }
            }
        }

        return new CdeCandidate(
            source: 'nih_cde',
            label: $label,
            externalId: $tinyId,
            externalUrl: null !== $tinyId ? 'https://cde.nlm.nih.gov/deView?tinyId=' . $tinyId : null,
            version: $version,
            definition: $this->definition($item),
            questionText: $this->designation($item),
            datatype: $datatype,
            unitConstraint: $unit,
            status: $this->str($item['registrationState']['registrationStatus'] ?? null),
            localKey: null,
            permissibleValues: $permissible,
            ontologyMappings: [],
            provenance: [
                'provider' => 'nih_cde',
                'tinyId' => $tinyId,
                'fetchedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ],
            rawPayload: $item,
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function mapForm(array $item): FormCandidate
    {
        $tinyId = $this->str($item['tinyId'] ?? $item['_id'] ?? null);
        $title = $this->designation($item) ?? ($tinyId ?? 'NIH CDE Form');

        return new FormCandidate(
            source: 'nih_cde',
            title: $title,
            externalId: $tinyId,
            externalUrl: null !== $tinyId ? 'https://cde.nlm.nih.gov/formView?tinyId=' . $tinyId : null,
            version: $this->str($item['version'] ?? null),
            description: $this->definition($item),
            provenance: [
                'provider' => 'nih_cde',
                'tinyId' => $tinyId,
                'fetchedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ],
            rawPayload: $item,
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function designation(array $item): ?string
    {
        $designations = $item['designations'] ?? null;
        if (\is_array($designations) && isset($designations[0]['designation'])) {
            return $this->str($designations[0]['designation']);
        }

        return $this->str($item['name'] ?? $item['title'] ?? null);
    }

    /**
     * @param array<string, mixed> $item
     */
    private function definition(array $item): ?string
    {
        $definitions = $item['definitions'] ?? null;
        if (\is_array($definitions) && isset($definitions[0]['definition'])) {
            return $this->str($definitions[0]['definition']);
        }

        return $this->str($item['definition'] ?? null);
    }

    /**
     * @param array<string, mixed>      $options
     * @param array<string, mixed>|null $json
     *
     * @return array<string, mixed>|null
     */
    private function safeJson(string $method, string $path, array $options = [], ?array $json = null): ?array
    {
        try {
            $requestOptions = ['timeout' => 10] + $options;
            if (null !== $json) {
                $requestOptions['json'] = $json;
            }
            $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/') . $path, $requestOptions);

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            return $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->info('NIH CDE provider request failed; degrading gracefully.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function str(mixed $value): ?string
    {
        if (\is_string($value)) {
            return '' === $value ? null : $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
