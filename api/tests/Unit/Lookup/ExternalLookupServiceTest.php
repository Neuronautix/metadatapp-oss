<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lookup;

use App\Lookup\ExternalLookupService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExternalLookupServiceTest extends TestCase
{
    #[Test]
    public function itMapsOrcidSuggestions(): void
    {
        $service = new ExternalLookupService(new MockHttpClient([
            new MockResponse(json_encode([
                'expanded-result' => [
                    [
                        'orcid-id' => '0000-0001-2345-6789',
                        'given-names' => 'Ada',
                        'family-names' => 'Lovelace',
                        'institution-name' => ['Analytical Engine Institute'],
                    ],
                ],
            ], \JSON_THROW_ON_ERROR)),
        ]));

        $results = $service->search('orcid', 'Ada');

        $this->assertSame([[
            'label' => 'Ada Lovelace',
            'sublabel' => 'Analytical Engine Institute',
            'value' => '0000-0001-2345-6789',
            'externalId' => 'https://orcid.org/0000-0001-2345-6789',
            'scheme' => 'ORCID',
        ]], $results);
    }

    #[Test]
    public function itMapsRorSuggestionsToCurrentLaboratoryValueShape(): void
    {
        $service = new ExternalLookupService(new MockHttpClient([
            new MockResponse(json_encode([
                'items' => [
                    [
                        'id' => 'https://ror.org/03yrm5c26',
                        'names' => [
                            ['value' => 'Université de Paris', 'types' => ['ror_display']],
                        ],
                        'addresses' => [
                            ['city' => 'Paris'],
                        ],
                        'country' => ['country_name' => 'France'],
                    ],
                ],
            ], \JSON_THROW_ON_ERROR)),
        ]));

        $results = $service->search('ror', 'Paris');

        $this->assertSame([[
            'label' => 'Université de Paris',
            'sublabel' => 'Paris, France',
            'value' => 'Université de Paris',
            'externalId' => 'https://ror.org/03yrm5c26',
            'scheme' => 'ROR',
        ]], $results);
    }

    #[Test]
    public function itFiltersOntologySpeciesToSupportedEnumValues(): void
    {
        $service = new ExternalLookupService(new MockHttpClient([
            new MockResponse(json_encode([
                'response' => [
                    'docs' => [
                        [
                            'label' => 'Mus musculus',
                            'obo_id' => 'NCBITaxon:10090',
                            'iri' => 'http://purl.obolibrary.org/obo/NCBITaxon_10090',
                        ],
                        [
                            'label' => 'Homo sapiens',
                            'obo_id' => 'NCBITaxon:9606',
                            'iri' => 'http://purl.obolibrary.org/obo/NCBITaxon_9606',
                        ],
                    ],
                ],
            ], \JSON_THROW_ON_ERROR)),
        ]));

        $results = $service->search('ols_ncbitaxon', 'mus');

        $this->assertSame([[
            'label' => 'Mus musculus',
            'sublabel' => 'NCBITaxon:10090',
            'value' => 'mouse',
            'externalId' => 'http://purl.obolibrary.org/obo/NCBITaxon_10090',
            'scheme' => 'NCBITaxon',
        ]], $results);
    }

    #[Test]
    public function itMapsEfoSuggestionsForStrainResolution(): void
    {
        $service = new ExternalLookupService(new MockHttpClient([
            new MockResponse(json_encode([
                'response' => [
                    'docs' => [
                        [
                            'label' => 'C57BL/6J',
                            'obo_id' => 'EFO:0004472',
                            'iri' => 'http://www.ebi.ac.uk/efo/EFO_0004472',
                        ],
                    ],
                ],
            ], \JSON_THROW_ON_ERROR)),
        ]));

        $results = $service->search('ols_efo', 'c57');

        $this->assertSame([[
            'label' => 'C57BL/6J',
            'sublabel' => 'EFO:0004472',
            'value' => 'C57BL/6J',
            'externalId' => 'http://www.ebi.ac.uk/efo/EFO_0004472',
            'scheme' => 'EFO',
        ]], $results);
    }
}
