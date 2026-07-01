<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Impc\Client\Mock;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Deterministic test double for the IMPC Solr `pipeline` core that backs the
 * IMPReSS (IMPC) standardized-screen catalogue.
 *
 * Mirrors the three query shapes the client issues against /select so the proxy
 * controller and client can be exercised without reaching the live host:
 *   - q=procedure_stable_id:"…"          -> a procedure's parameter documents
 *   - group.field=pipeline_stable_id     -> grouped pipelines
 *   - group.field=procedure_stable_id    -> grouped procedures (keyword search)
 *
 * The sample data is behaviour-focused (Open Field) to reflect the standardized
 * resources used in behavioral studies.
 */
class HttpClientMock extends MockHttpClient
{
    public function __construct()
    {
        parent::__construct($this->handleRequests(...));
    }

    private function handleRequests(string $method, string $url): MockResponse
    {
        // PHP mangles dotted query keys (group.field -> group_field), so route on
        // the decoded raw URL instead of a parsed parameter bag.
        $decoded = urldecode($url);

        $json = match (true) {
            str_contains($decoded, 'q=procedure_stable_id:') => $this->procedureParameters(),
            str_contains($decoded, 'group.field=pipeline_stable_id') => $this->pipelines(),
            str_contains($decoded, 'group.field=procedure_stable_id') => $this->procedures(),
            default => '{"response":{"numFound":0,"docs":[]}}',
        };

        return new MockResponse($json, [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    private function pipelines(): string
    {
        return <<<'JSON'
            {
                "grouped": {
                    "pipeline_stable_id": {
                        "matches": 2,
                        "ngroups": 2,
                        "groups": [
                            {"groupValue": "IMPC_001", "doclist": {"numFound": 1, "docs": [
                                {"pipeline_stable_id": "IMPC_001", "pipeline_name": "IMPC Pipeline", "pipeline_id": 7}
                            ]}},
                            {"groupValue": "IMPC_002", "doclist": {"numFound": 1, "docs": [
                                {"pipeline_stable_id": "IMPC_002", "pipeline_name": "IMPC Behaviour Pipeline", "pipeline_id": 8}
                            ]}}
                        ]
                    }
                }
            }
            JSON;
    }

    private function procedures(): string
    {
        return <<<'JSON'
            {
                "grouped": {
                    "procedure_stable_id": {
                        "matches": 1,
                        "ngroups": 1,
                        "groups": [
                            {"groupValue": "IMPC_OFD_001", "doclist": {"numFound": 1, "docs": [
                                {
                                    "procedure_stable_id": "IMPC_OFD_001",
                                    "procedure_stable_key": "OFD",
                                    "procedure_name": "Open Field",
                                    "procedure_id": 499,
                                    "pipeline_stable_id": "IMPC_002",
                                    "pipeline_name": "IMPC Behaviour Pipeline"
                                }
                            ]}}
                        ]
                    }
                }
            }
            JSON;
    }

    private function procedureParameters(): string
    {
        return <<<'JSON'
            {
                "response": {
                    "numFound": 2,
                    "docs": [
                        {
                            "parameter_stable_id": "IMPC_OFD_009_001",
                            "parameter_name": "Distance travelled - total",
                            "data_type": "FLOAT",
                            "unit_x": "cm",
                            "required": true,
                            "experiment_level": "experiment",
                            "mp_id": ["MP:0001392"],
                            "mp_term": ["abnormal locomotor activity"],
                            "procedure_stable_id": "IMPC_OFD_001",
                            "procedure_stable_key": "OFD",
                            "procedure_name": "Open Field",
                            "procedure_id": 499,
                            "pipeline_stable_id": "IMPC_002",
                            "pipeline_name": "IMPC Behaviour Pipeline"
                        },
                        {
                            "parameter_stable_id": "IMPC_OFD_049_001",
                            "parameter_name": "Equipment manufacturer",
                            "data_type": "TEXT",
                            "unit_x": null,
                            "required": false,
                            "experiment_level": "procedureMetadata",
                            "procedure_stable_id": "IMPC_OFD_001",
                            "procedure_name": "Open Field",
                            "procedure_id": 499,
                            "pipeline_stable_id": "IMPC_002",
                            "pipeline_name": "IMPC Behaviour Pipeline"
                        }
                    ]
                }
            }
            JSON;
    }
}
