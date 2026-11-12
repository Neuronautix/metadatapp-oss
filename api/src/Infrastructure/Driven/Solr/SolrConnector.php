<?php

declare(strict_types=1);

namespace App\Infrastructure\Driven\Solr;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class SolrConnector
{
    // IMPC Solr Base URL
    private const BASE_URL = 'https://www.ebi.ac.uk/mi/impc/solr/genotype-phenotype/';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function query(string $core, array $params): array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL . $core . '/select', [
            'query' => array_merge(['wt' => 'json'], $params),
        ]);

        return $response->toArray();
    }
}
