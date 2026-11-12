<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\JaxPhenome\Client;

use App\ConnectedApps\Apps\JaxPhenome\Client\JaxPhenomeClient;
use App\Entity\ConnectedApp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class JaxPhenomeClientTest extends TestCase
{
    private function connectedApp(?string $externalUrl = null): ConnectedApp
    {
        $connectedApp = $this->createStub(ConnectedApp::class);
        $connectedApp->method('getExternalUrl')->willReturn($externalUrl);

        return $connectedApp;
    }

    /**
     * Routes requests to representative MPD payloads by path. The homepage carries
     * the typeahead term list, and the JSON endpoints mirror the real shapes.
     */
    private function mpdClient(?callable $tap = null): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url, array $options) use ($tap): MockResponse {
            if (null !== $tap) {
                $tap($method, $url, $options);
            }

            $path = parse_url($url, \PHP_URL_PATH) ?: '/';

            if ('/' === $path) {
                return new MockResponse(
                    '<script>$("#x").typeahead({ source: [ "body weight ... VT:0001259", "blood ... MA:0000059" ] });</script>',
                    ['http_code' => 200, 'response_headers' => ['content-type' => 'text/html']]
                );
            }

            $body = match (true) {
                str_contains($path, '/api/pheno/measures_by_ontology/') => json_encode([
                    'count' => 2,
                    'measures' => [
                        ['measnum' => 12345, 'descrip' => 'Body weight at 6 weeks', 'units' => 'g', 'projsym' => 'Naggert1'],
                        'not-an-object',
                        ['measnum' => 67890, 'descrip' => 'Plasma glucose', 'units' => 'mg/dL'],
                    ],
                    'ontology_terms' => [['id' => 'VT:0001259', 'descrip' => 'body weight']],
                ], \JSON_THROW_ON_ERROR),
                str_contains($path, '/api/pheno/measureinfo/') => json_encode([
                    'count' => 1,
                    'measures_info' => [['measnum' => 2908, 'descrip' => 'gallbladder volume', 'units' => '&micro;L', 'varname' => 'gb_vol']],
                ], \JSON_THROW_ON_ERROR),
                str_contains($path, '/api/straininfo') => json_encode([
                    'jaxinfo' => [['nomenclature' => 'C57BL/6J', 'stocknum' => '000664', 'avl_status' => 'Readily Available']],
                    'mpdinfo' => [['id' => 7, 'aname' => 'C57BL/6J']],
                ], \JSON_THROW_ON_ERROR),
                default => '{}',
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
    public function getBaseUrlDefaultsToPublicMpdAndStripsTrailingSlash(): void
    {
        $client = new JaxPhenomeClient(new MockHttpClient());

        $this->assertSame('https://phenome.jax.org', $client->getBaseUrl($this->connectedApp()));
        $this->assertSame('https://mpd.example.org', $client->getBaseUrl($this->connectedApp('https://mpd.example.org/')));
    }

    #[Test]
    public function searchMeasuresResolvesFreeTextToAnOntologyTermThenReturnsMeasures(): void
    {
        $result = (new JaxPhenomeClient($this->mpdClient()))
            ->searchMeasures($this->connectedApp(), 'body weight');

        $this->assertSame('VT:0001259', $result['resolvedTerm']['id']);
        $this->assertNotEmpty($result['ontologyTerms']);
        // Non-array measures are dropped during normalization.
        $this->assertCount(2, $result['measures']);
        $this->assertSame(12345, $result['measures'][0]['measnum']);
        $this->assertSame('Body weight at 6 weeks', $result['measures'][0]['name']);
    }

    #[Test]
    public function searchMeasuresHonoursAnExplicitlyTypedOntologyId(): void
    {
        $result = (new JaxPhenomeClient($this->mpdClient()))
            ->searchMeasures($this->connectedApp(), 'VT:0005311');

        $this->assertCount(2, $result['measures']);
    }

    #[Test]
    public function searchMeasuresReturnsEmptyForBlankInput(): void
    {
        $result = (new JaxPhenomeClient($this->mpdClient()))
            ->searchMeasures($this->connectedApp(), '   ');

        $this->assertSame(0, $result['totalItems']);
        $this->assertSame([], $result['measures']);
    }

    #[Test]
    public function getMeasureReadsTheMeasuresInfoWrapperAndDecodesHtmlEntities(): void
    {
        $requestedUrl = null;
        $client = new JaxPhenomeClient($this->mpdClient(static function (string $method, string $url) use (&$requestedUrl): void {
            $requestedUrl = $url;
        }));

        $measure = $client->getMeasure($this->connectedApp(), 'a b');

        $this->assertSame('https://phenome.jax.org/api/pheno/measureinfo/' . rawurlencode('a b'), $requestedUrl);
        $this->assertSame(2908, $measure['measnum']);
        $this->assertSame('gallbladder volume', $measure['name']);
        $this->assertSame('µL', $measure['units']);
    }

    #[Test]
    public function lookupStrainReadsMpdAndJaxInfo(): void
    {
        $strain = (new JaxPhenomeClient($this->mpdClient()))
            ->lookupStrain($this->connectedApp(), 'C57BL/6J');

        $this->assertSame(7, $strain['id']);
        $this->assertSame('C57BL/6J', $strain['symbol']);
        $this->assertSame('000664', $strain['stocknum']);
    }

    #[Test]
    public function listStrainsResolvesTheDefaultStrainSet(): void
    {
        $result = (new JaxPhenomeClient($this->mpdClient()))
            ->listStrains($this->connectedApp(), 3, 0);

        $this->assertSame(12, $result['totalItems']);
        $this->assertCount(3, $result['strains']);
    }

    #[Test]
    public function nonSuccessStatusRaisesARuntimeException(): void
    {
        $client = new JaxPhenomeClient($this->statusClient(429));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP 429');
        $client->getMeasure($this->connectedApp(), '2908');
    }

    #[Test]
    public function cacheServesRepeatedReadsWithoutHittingTheUpstreamAgain(): void
    {
        $calls = 0;
        $client = new JaxPhenomeClient($this->mpdClient(static function () use (&$calls): void {
            ++$calls;
        }), new ArrayAdapter());
        $app = $this->connectedApp();

        $client->getMeasure($app, '2908');
        $client->getMeasure($app, '2908');

        $this->assertSame(1, $calls, 'Second identical read should be served from cache.');
    }

    #[Test]
    public function cacheIsBypassedWhenUseCacheIsFalse(): void
    {
        $calls = 0;
        $client = new JaxPhenomeClient($this->mpdClient(static function () use (&$calls): void {
            ++$calls;
        }), new ArrayAdapter());
        $app = $this->connectedApp();

        $client->listStrains($app, 1, 0, false);
        $client->listStrains($app, 1, 0, false);

        $this->assertSame(2, $calls, 'Uncached reads must always hit the upstream.');
    }
}
