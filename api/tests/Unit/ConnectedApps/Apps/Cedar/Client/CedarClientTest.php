<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Cedar\Client;

use App\ConnectedApps\Apps\Cedar\Client\CedarClient;
use App\ConnectedApps\Apps\Cedar\Enum\CedarArtifactType;
use App\Entity\ConnectedApp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CedarClientTest extends TestCase
{
    private function connectedApp(?string $externalUrl = null, ?string $token = 'cedar-key'): ConnectedApp
    {
        $connectedApp = $this->createStub(ConnectedApp::class);
        $connectedApp->method('getExternalUrl')->willReturn($externalUrl);
        $connectedApp->method('getToken')->willReturn($token);

        return $connectedApp;
    }

    #[Test]
    public function getBaseUrlDefaultsToPublicCedarAndStripsTrailingSlash(): void
    {
        $client = new CedarClient(new MockHttpClient());

        $this->assertSame('https://resource.metadatacenter.org', $client->getBaseUrl($this->connectedApp()));
        $this->assertSame('https://cedar.example.org', $client->getBaseUrl($this->connectedApp('https://cedar.example.org/')));
    }

    #[Test]
    public function getArtifactUrlEncodesTheIriIntoTheTypePathSegment(): void
    {
        $requestedUrl = null;
        $requestedMethod = null;
        $authHeader = null;
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$requestedMethod, &$requestedUrl, &$authHeader): MockResponse {
                $requestedMethod = $method;
                $requestedUrl = $url;
                foreach ($options['headers'] ?? [] as $header) {
                    if (str_starts_with(strtolower((string) $header), 'authorization:')) {
                        $authHeader = $header;
                    }
                }

                return new MockResponse(json_encode(['@id' => 'x'], \JSON_THROW_ON_ERROR), [
                    'http_code' => 200,
                    'response_headers' => ['content-type' => 'application/json'],
                ]);
            }
        );

        $iri = 'https://repo.metadatacenter.org/templates/abc-123';
        (new CedarClient($httpClient))->getArtifact($this->connectedApp(), CedarArtifactType::Template, $iri);

        $this->assertSame('GET', $requestedMethod);
        $this->assertSame('https://resource.metadatacenter.org/templates/' . rawurlencode($iri), $requestedUrl);
        $this->assertSame('Authorization: apiKey cedar-key', $authHeader);
    }

    #[Test]
    public function elementAndFieldUseTemplatePrefixedPathSegments(): void
    {
        $urls = [];
        $httpClient = new MockHttpClient(
            static function (string $method, string $url) use (&$urls): MockResponse {
                $urls[] = $url;

                return new MockResponse('{}', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
            }
        );

        $client = new CedarClient($httpClient);
        $client->getArtifact($this->connectedApp(), CedarArtifactType::Element, 'iri-1');
        $client->getArtifact($this->connectedApp(), CedarArtifactType::Field, 'iri-2');
        $client->getArtifact($this->connectedApp(), CedarArtifactType::Instance, 'iri-3');

        $this->assertSame('https://resource.metadatacenter.org/template-elements/iri-1', $urls[0]);
        $this->assertSame('https://resource.metadatacenter.org/template-fields/iri-2', $urls[1]);
        $this->assertSame('https://resource.metadatacenter.org/template-instances/iri-3', $urls[2]);
    }

    #[Test]
    public function createPostsToTheCollectionAndNullsTopLevelId(): void
    {
        $requestedUrl = null;
        $requestedMethod = null;
        $sentBody = null;
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$requestedMethod, &$requestedUrl, &$sentBody): MockResponse {
                $requestedMethod = $method;
                $requestedUrl = $url;
                $sentBody = $options['body'] ?? null;

                return new MockResponse(json_encode(['@id' => 'https://repo.metadatacenter.org/templates/new'], \JSON_THROW_ON_ERROR), [
                    'http_code' => 201,
                    'response_headers' => ['content-type' => 'application/json'],
                ]);
            }
        );

        $created = (new CedarClient($httpClient))->createArtifact(
            $this->connectedApp(),
            CedarArtifactType::Template,
            ['@id' => 'https://repo.metadatacenter.org/templates/should-be-nulled', 'schema:name' => 'T'],
        );

        $this->assertSame('POST', $requestedMethod);
        $this->assertSame('https://resource.metadatacenter.org/templates', $requestedUrl);
        $this->assertIsString($sentBody);
        $decoded = json_decode((string) $sentBody, true, 512, \JSON_THROW_ON_ERROR);
        $this->assertNull($decoded['@id']);
        $this->assertSame('https://repo.metadatacenter.org/templates/new', $created['@id']);
    }

    #[Test]
    public function validateTargetsCommandValidateWithDetectedResourceType(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(
            static function (string $method, string $url) use (&$requestedUrl): MockResponse {
                $requestedUrl = $url;

                return new MockResponse(json_encode(['validates' => true, 'warnings' => [], 'errors' => []], \JSON_THROW_ON_ERROR), [
                    'http_code' => 200,
                    'response_headers' => ['content-type' => 'application/json'],
                ]);
            }
        );

        $report = (new CedarClient($httpClient))->validateArtifact($this->connectedApp(), [
            '@type' => 'https://schema.metadatacenter.org/core/TemplateElement',
            'schema:name' => 'E',
        ]);

        $this->assertSame('https://resource.metadatacenter.org/command/validate?resource_type=element', $requestedUrl);
        $this->assertTrue($report['validates']);
    }

    #[Test]
    public function validateThrowsWhenTypeCannotBeDetected(): void
    {
        $client = new CedarClient(new MockHttpClient());

        $this->expectException(\InvalidArgumentException::class);
        $client->validateArtifact($this->connectedApp(), ['schema:name' => 'no type here']);
    }

    #[Test]
    public function nonSuccessStatusRaisesARuntimeException(): void
    {
        $httpClient = new MockHttpClient(
            static fn (): MockResponse => new MockResponse('Not found', ['http_code' => 404])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/HTTP 404/');
        (new CedarClient($httpClient))->getArtifact($this->connectedApp(), CedarArtifactType::Template, 'missing');
    }

    #[Test]
    public function preExistingApiKeyPrefixIsNotDoubled(): void
    {
        $authHeader = null;
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$authHeader): MockResponse {
                foreach ($options['headers'] ?? [] as $header) {
                    if (str_starts_with(strtolower((string) $header), 'authorization:')) {
                        $authHeader = $header;
                    }
                }

                return new MockResponse('{}', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
            }
        );

        (new CedarClient($httpClient))->getUsers($this->connectedApp(token: 'apiKey already-prefixed'));

        $this->assertSame('Authorization: apiKey already-prefixed', $authHeader);
    }

    #[Test]
    public function getArtifactCachesRepeatedFetchesOfTheSameIri(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(
            static function () use (&$calls): MockResponse {
                ++$calls;

                return new MockResponse('{"@id":"x"}', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
            }
        );

        $client = new CedarClient($httpClient, new ArrayAdapter());
        $app = $this->connectedApp();
        $client->getArtifact($app, CedarArtifactType::Template, 'iri-1');
        $client->getArtifact($app, CedarArtifactType::Template, 'iri-1');

        $this->assertSame(1, $calls, 'Repeated fetch of the same artifact should be served from cache.');
    }

    #[Test]
    public function updateInvalidatesTheCachedArtifact(): void
    {
        $calls = 0;
        $httpClient = new MockHttpClient(
            static function () use (&$calls): MockResponse {
                ++$calls;

                return new MockResponse('{"@id":"x"}', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
            }
        );

        $client = new CedarClient($httpClient, new ArrayAdapter());
        $app = $this->connectedApp();
        $client->getArtifact($app, CedarArtifactType::Template, 'iri-1'); // call 1 (miss → cached)
        $client->updateArtifact($app, CedarArtifactType::Template, 'iri-1', ['schema:name' => 'T']); // call 2 (+ invalidate)
        $client->getArtifact($app, CedarArtifactType::Template, 'iri-1'); // call 3 (miss again)

        $this->assertSame(3, $calls, 'Update must evict the cached artifact so the next fetch is fresh.');
    }
}
