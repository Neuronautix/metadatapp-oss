<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CorsPreflightTest extends ApiTestCase
{
    #[Test]
    public function preflightRequestsDoNotRequireAuthentication(): void
    {
        static::createClient()->request('OPTIONS', '/projects', [
            'headers' => [
                'Origin' => 'https://osoma.metadatapp.test',
                'Access-Control-Request-Method' => 'GET',
                'Access-Control-Request-Headers' => 'authorization,x-bypass-mock',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('access-control-allow-origin', 'https://osoma.metadatapp.test');
        $this->assertResponseHeaderSame('access-control-allow-credentials', 'true');
    }
}
