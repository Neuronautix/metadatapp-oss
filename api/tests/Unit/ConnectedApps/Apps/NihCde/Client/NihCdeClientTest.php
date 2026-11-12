<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\NihCde\Client;

use App\ConnectedApps\Apps\NihCde\Client\NihCdeClient;
use App\Entity\ConnectedApp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NihCdeClientTest extends TestCase
{
    #[Test]
    public function getElementUsesNihDataElementEndpoint(): void
    {
        $requestedUrl = null;
        $requestedMethod = null;
        $httpClient = new MockHttpClient(
            static function (string $method, string $url) use (&$requestedMethod, &$requestedUrl): MockResponse {
                $requestedMethod = $method;
                $requestedUrl = $url;

                return new MockResponse(json_encode(['tinyId' => 'C1234567'], \JSON_THROW_ON_ERROR), [
                    'http_code' => 200,
                    'response_headers' => ['content-type' => 'application/json'],
                ]);
            }
        );

        $connectedApp = $this->createStub(ConnectedApp::class);
        $connectedApp->method('getExternalUrl')->willReturn(null);
        $connectedApp->method('getToken')->willReturn(null);

        $result = (new NihCdeClient($httpClient))->getElement($connectedApp, 'C1234567');

        $this->assertSame(['tinyId' => 'C1234567'], $result);
        $this->assertSame('GET', $requestedMethod);
        $this->assertSame('https://cde.nlm.nih.gov/api/de/C1234567', $requestedUrl);
    }

    #[Test]
    public function searchUsesPostToServerDeSearch(): void
    {
        $requestedUrl = null;
        $requestedMethod = null;
        $requestedBody = null;
        $httpClient = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$requestedMethod, &$requestedUrl, &$requestedBody): MockResponse {
                $requestedMethod = $method;
                $requestedUrl = $url;
                $requestedBody = $options['body'] ?? null;

                return new MockResponse(
                    json_encode(['totalItems' => 1, 'cdes' => [['tinyId' => 'C1234567']]], \JSON_THROW_ON_ERROR),
                    ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]
                );
            }
        );

        $connectedApp = $this->createStub(ConnectedApp::class);
        $connectedApp->method('getExternalUrl')->willReturn(null);
        $connectedApp->method('getToken')->willReturn(null);

        $result = (new NihCdeClient($httpClient))->search($connectedApp, 'body weight', 10, 0);

        $this->assertSame('POST', $requestedMethod);
        $this->assertSame('https://cde.nlm.nih.gov/server/de/search', $requestedUrl);
        $this->assertSame(1, $result['totalItems']);
        $this->assertCount(1, $result['elements']);
    }

    #[Test]
    public function getBaseUrlStripsLegacyApiSuffix(): void
    {
        $httpClient = new MockHttpClient();
        $connectedApp = $this->createStub(ConnectedApp::class);
        $connectedApp->method('getExternalUrl')->willReturn('https://cde.nlm.nih.gov/api');
        $connectedApp->method('getToken')->willReturn(null);

        $baseUrl = (new NihCdeClient($httpClient))->getBaseUrl($connectedApp);

        $this->assertSame('https://cde.nlm.nih.gov', $baseUrl);
    }
}
