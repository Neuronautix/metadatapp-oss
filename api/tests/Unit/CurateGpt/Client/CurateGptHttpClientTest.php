<?php

declare(strict_types=1);

namespace App\Tests\Unit\CurateGpt\Client;

use App\CurateGpt\Client\CurateGptHttpClient;
use App\CurateGpt\Client\Dto\CurateGptSearchRequestDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CurateGptHttpClientTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private CurateGptHttpClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->client = new CurateGptHttpClient($this->httpClient, $this->logger);
    }

    #[Test]
    public function searchReturnsListOfCandidateDtos(): void
    {
        $responseData = [
            'results' => [
                [
                    'object' => [
                        'id' => 'http://purl.obolibrary.org/obo/NCBITaxon_10090',
                        'label' => 'Mus musculus',
                        'definition' => 'House mouse',
                    ],
                    'score' => 0.95,
                ],
                [
                    'object' => [
                        'id' => 'http://purl.obolibrary.org/obo/NCBITaxon_10116',
                        'label' => 'Rattus norvegicus',
                    ],
                    'score' => 0.72,
                ],
            ],
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($responseData);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'search', $this->callback(static function (array $opts) {
                return isset($opts['query']['query'])
                    && 'C57BL/6J' === $opts['query']['query']
                    && 5 === $opts['query']['limit'];
            }))
            ->willReturn($response);

        $request = new CurateGptSearchRequestDto(query: 'C57BL/6J', limit: 5);
        $candidates = $this->client->search($request);

        $this->assertCount(2, $candidates);
        $this->assertSame('http://purl.obolibrary.org/obo/NCBITaxon_10090', $candidates[0]->id);
        $this->assertSame('Mus musculus', $candidates[0]->label);
        $this->assertSame(0.95, $candidates[0]->score);
        $this->assertSame(['definition' => 'House mouse'], $candidates[0]->metadata);

        $this->assertSame('http://purl.obolibrary.org/obo/NCBITaxon_10116', $candidates[1]->id);
        $this->assertSame('Rattus norvegicus', $candidates[1]->label);
        $this->assertSame(0.72, $candidates[1]->score);
    }

    #[Test]
    public function searchIncludesCollectionParamWhenProvided(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'search', $this->callback(static function (array $opts) {
                return 'mp' === ($opts['query']['collection'] ?? null);
            }))
            ->willReturn($response);

        $request = new CurateGptSearchRequestDto(query: 'pain', limit: 5, collection: 'mp');
        $this->client->search($request);
    }

    #[Test]
    public function searchThrowsRuntimeExceptionOnHttpFailure(): void
    {
        $this->httpClient->expects($this->once())
            ->method('request')
            ->willThrowException(new \Symfony\Component\HttpClient\Exception\TransportException('Connection refused'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CurateGPT search request failed/');

        $request = new CurateGptSearchRequestDto(query: 'anything');
        $this->client->search($request);
    }
}
