<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Impc\Client;

use App\ConnectedApps\Apps\Impc\Client\ImpcClient;
use App\Entity\ConnectedApp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ImpcClientTest extends TestCase
{
    private function connectedApp(?string $externalUrl = null): ConnectedApp
    {
        $connectedApp = $this->createStub(ConnectedApp::class);
        $connectedApp->method('getExternalUrl')->willReturn($externalUrl);

        return $connectedApp;
    }

    /**
     * Routes Solr `/select` requests to representative responses based on the
     * query shape the client issues (procedure detail / pipeline / procedure group).
     */
    private function solrClient(?callable $tap = null): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url, array $options) use ($tap): MockResponse {
            if (null !== $tap) {
                $tap($method, $url, $options);
            }

            // PHP mangles dotted query keys (group.field -> group_field), so route
            // on the decoded raw URL instead of a parsed parameter bag.
            $decoded = urldecode($url);

            $body = match (true) {
                str_contains($decoded, 'q=procedure_stable_id:') => json_encode([
                    'response' => ['numFound' => 2, 'docs' => [
                        [
                            'parameter_stable_id' => 'IMPC_OFD_009_001', 'parameter_name' => 'Distance travelled',
                            'data_type' => 'FLOAT', 'unit_x' => 'cm', 'required' => true, 'experiment_level' => 'experiment',
                            'mp_id' => ['MP:0001392'], 'mp_term' => ['abnormal locomotor activity'],
                            'procedure_stable_id' => 'IMPC_OFD_001', 'procedure_name' => 'Open Field', 'procedure_id' => 499,
                        ],
                        [
                            'parameter_stable_id' => 'IMPC_OFD_049_001', 'parameter_name' => 'Equipment manufacturer',
                            'data_type' => 'TEXT', 'unit_x' => null, 'experiment_level' => 'procedureMetadata',
                            'procedure_stable_id' => 'IMPC_OFD_001', 'procedure_name' => 'Open Field', 'procedure_id' => 499,
                        ],
                    ]],
                ], \JSON_THROW_ON_ERROR),
                str_contains($decoded, 'group.field=pipeline_stable_id') => json_encode([
                    'grouped' => ['pipeline_stable_id' => ['ngroups' => 79, 'groups' => [
                        ['doclist' => ['docs' => [['pipeline_stable_id' => 'IMPC_001', 'pipeline_name' => 'IMPC Pipeline', 'pipeline_id' => 7]]]],
                        ['doclist' => ['docs' => [['pipeline_stable_id' => 'CCP_001', 'pipeline_name' => 'CCP Pipeline', 'pipeline_id' => 9]]]],
                    ]]],
                ], \JSON_THROW_ON_ERROR),
                str_contains($decoded, 'group.field=procedure_stable_id') => json_encode([
                    'grouped' => ['procedure_stable_id' => ['ngroups' => 150, 'groups' => [
                        ['doclist' => ['docs' => [[
                            'procedure_stable_id' => 'IMPC_OFD_001', 'procedure_stable_key' => 'OFD',
                            'procedure_name' => 'Open Field', 'procedure_id' => 499,
                            'pipeline_stable_id' => 'IMPC_002', 'pipeline_name' => 'IMPC Behaviour Pipeline',
                        ]]]],
                    ]]],
                ], \JSON_THROW_ON_ERROR),
                default => '{"response":{"numFound":0,"docs":[]}}',
            };

            return new MockResponse($body, ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
        });
    }

    private function statusClient(int $httpCode): MockHttpClient
    {
        return new MockHttpClient(static fn (): MockResponse => new MockResponse('{}', [
            'http_code' => $httpCode,
            'response_headers' => ['content-type' => 'application/json'],
        ]));
    }

    #[Test]
    public function getBaseUrlDefaultsToSolrAndMapsTheLegacyHost(): void
    {
        $client = new ImpcClient(new MockHttpClient());

        $this->assertSame('https://www.ebi.ac.uk/mi/impc/solr/pipeline', $client->getBaseUrl($this->connectedApp()));
        // Apps saved before the Solr switch keep working.
        $this->assertSame('https://www.ebi.ac.uk/mi/impc/solr/pipeline', $client->getBaseUrl($this->connectedApp('https://api.mousephenotype.org')));
        $this->assertSame('https://impc.example.org', $client->getBaseUrl($this->connectedApp('https://impc.example.org/')));
    }

    #[Test]
    public function listPipelinesParsesGroupedResultsAndTrustsNgroups(): void
    {
        $result = (new ImpcClient($this->solrClient()))->listPipelines($this->connectedApp(), 20, 0);

        $this->assertSame(79, $result['totalItems']);
        $this->assertCount(2, $result['pipelines']);
        $this->assertSame('IMPC_001', $result['pipelines'][0]['stableKey']);
        $this->assertSame('IMPC Pipeline', $result['pipelines'][0]['name']);
    }

    #[Test]
    public function searchProceduresParsesGroupedProceduresAndTrustsNgroups(): void
    {
        $result = (new ImpcClient($this->solrClient()))->searchProcedures($this->connectedApp(), 'open field');

        $this->assertSame(150, $result['totalItems']);
        $this->assertCount(1, $result['procedures']);
        $this->assertSame('Open Field', $result['procedures'][0]['name']);
        // The detail lookup is keyed on the stable id.
        $this->assertSame('IMPC_OFD_001', $result['procedures'][0]['procedureId']);
    }

    #[Test]
    public function searchProceduresBuildsACaseInsensitiveSubstringQuery(): void
    {
        $captured = null;
        (new ImpcClient($this->solrClient(static function (string $method, string $url) use (&$captured): void {
            $captured = urldecode($url);
        })))->searchProcedures($this->connectedApp(), 'Open Field');

        // Lowercased substring wildcards against the analyzed default field (the
        // procedure_name/parameter_name string fields are case-sensitive and would
        // return nothing for a lowercased wildcard).
        $this->assertStringContainsString('q=*open* AND *field*', (string) $captured);
        $this->assertStringNotContainsString('procedure_name:*', (string) $captured);
    }

    #[Test]
    public function getProcedureAssemblesParametersFromTheSolrDocuments(): void
    {
        $result = (new ImpcClient($this->solrClient()))->getProcedure($this->connectedApp(), 'IMPC_OFD_001');

        $this->assertSame('IMPC_OFD_001', $result['stableKey']);
        $this->assertSame('Open Field', $result['name']);
        $this->assertCount(2, $result['parameters']);

        $first = $result['parameters'][0];
        $this->assertSame('IMPC_OFD_009_001', $first['stableKey']);
        $this->assertSame('FLOAT', $first['datatype']);
        $this->assertSame('cm', $first['unit']);
        $this->assertSame('MP:0001392', $first['ontologyMapping'][0]['id']);
        $this->assertFalse($first['isMetadata']);

        $this->assertTrue($result['parameters'][1]['isMetadata']);
    }

    #[Test]
    public function nonSuccessStatusRaisesARuntimeException(): void
    {
        $client = new ImpcClient($this->statusClient(429));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 429');
        $client->listPipelines($this->connectedApp());
    }

    #[Test]
    public function cacheServesRepeatedReadsWithoutHittingTheUpstreamAgain(): void
    {
        $calls = 0;
        $client = new ImpcClient($this->solrClient(static function () use (&$calls): void {
            ++$calls;
        }), new ArrayAdapter());
        $app = $this->connectedApp();

        $client->listPipelines($app, 20, 0);
        $client->listPipelines($app, 20, 0);

        $this->assertSame(1, $calls, 'Second identical read should be served from cache.');
    }

    #[Test]
    public function cacheIsBypassedWhenUseCacheIsFalse(): void
    {
        $calls = 0;
        $client = new ImpcClient($this->solrClient(static function () use (&$calls): void {
            ++$calls;
        }), new ArrayAdapter());
        $app = $this->connectedApp();

        $client->listPipelines($app, 1, 0, false);
        $client->listPipelines($app, 1, 0, false);

        $this->assertSame(2, $calls, 'Uncached reads must always hit the upstream.');
    }
}
