<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\JaxPhenome\Client\Mock;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Deterministic test double for the JAX Mouse Phenome Database (MPD) public REST API.
 *
 * Mirrors the real endpoints the client uses so the proxy controller and client can be
 * exercised without reaching the live (egress-blocked) host:
 *   - GET /                                          -> homepage typeahead term list
 *   - GET /api/pheno/measures_by_ontology/{ontTerm}  -> measures for an ontology term
 *   - GET /api/pheno/measureinfo/{measnum}           -> measure detail (measures_info[])
 *   - GET /api/straininfo?name={name}                -> strain detail (jaxinfo/mpdinfo)
 */
class HttpClientMock extends MockHttpClient
{
    public function __construct()
    {
        parent::__construct($this->handleRequests(...));
    }

    private function handleRequests(string $method, string $url): MockResponse
    {
        $request = Request::create($url, $method);
        $path = $request->getPathInfo();

        if ('/' === $path) {
            return new MockResponse($this->homepage(), [
                'http_code' => 200,
                'response_headers' => ['content-type' => 'text/html'],
            ]);
        }

        $json = match (true) {
            str_contains($path, '/api/pheno/measures_by_ontology/') => $this->measuresByOntology($this->lastSegment($path)),
            str_contains($path, '/api/pheno/measureinfo/') => $this->measureInfo($this->lastSegment($path)),
            str_ends_with($path, '/api/straininfo') => $this->strainInfo((string) ($request->query->get('name') ?? 'C57BL/6J')),
            default => '{}',
        };

        return new MockResponse($json, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    private function lastSegment(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => '' !== $segment));

        return $segments[\count($segments) - 1] ?? '';
    }

    /**
     * Minimal homepage carrying the same inline typeahead `source` array MPD ships,
     * from which the client builds its ontology-term index.
     */
    private function homepage(): string
    {
        return <<<'HTML'
            <!doctype html><html><body>
            <script>
            $("[id=typeahead-input]").typeahead({
                source: [ "C57BL/6J", "body weight ... VT:0001259", "blood ... MA:0000059", "blood glucose amount ... VT:0000188" ]
            });
            </script>
            </body></html>
            HTML;
    }

    private function measuresByOntology(string $ontTerm): string
    {
        $ontTerm = '' === $ontTerm ? 'VT:0001259' : $ontTerm;

        return <<<JSON
            {
                "count": 2,
                "measures": [
                    {
                        "measnum": 12345,
                        "varname": "bw_6wk",
                        "descrip": "Body weight at 6 weeks of age",
                        "units": "g",
                        "projsym": "Naggert1",
                        "intervention": "none"
                    },
                    {
                        "measnum": 67890,
                        "varname": "glucose",
                        "descrip": "Plasma glucose concentration",
                        "units": "mg/dL",
                        "projsym": "Paigen1",
                        "intervention": "high-fat diet"
                    }
                ],
                "ontology_terms": [
                    {"id": "{$ontTerm}", "descrip": "body weight", "parent": "VT:0000003"}
                ]
            }
            JSON;
    }

    private function measureInfo(string $measureId): string
    {
        $measureId = '' === $measureId ? '12345' : $measureId;

        return <<<JSON
            {
                "count": 1,
                "measures_info": [
                    {
                        "measnum": {$measureId},
                        "varname": "bw_6wk",
                        "descrip": "Body weight at 6 weeks of age",
                        "units": "g",
                        "projsym": "Naggert1",
                        "intervention": "none",
                        "sextested": "both"
                    }
                ]
            }
            JSON;
    }

    private function strainInfo(string $name): string
    {
        $name = json_encode($name) ?: '"C57BL/6J"';

        return <<<JSON
            {
                "jaxinfo": [
                    {"nomenclature": {$name}, "stocknum": "000664", "avl_status": "Readily Available"}
                ],
                "mpdinfo": [
                    {"id": 7, "aname": {$name}, "jaxavl": "Readily Available"}
                ]
            }
            JSON;
    }
}
