<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Authentication
{
    public function __construct(
        private ?string $softMouseUsername = null,
        private ?string $softMousePassword = null,
    ) {
        if (null === $softMouseUsername || null === $softMousePassword) {
            throw new \RuntimeException('SoftMouse credentials are not configured.');
        }
    }

    /**
     * Authenticate and return a fresh token.
     * Does not store token internally; caller is responsible for persisting it (e.g. on User entity).
     *
     * Uses SoftMouse's documented public `external/v1` REST API; credentials are
     * always supplied by the user configuring their own connected app, never
     * bundled with this codebase.
     *
     * @throws TransportExceptionInterface
     */
    public function authenticate(HttpClientInterface $client): string
    {
        $response = $client->request('POST', 'external/v1/user/login', [
            'json' => [
                'username' => $this->softMouseUsername,
                'password' => $this->softMousePassword,
            ],
        ]);

        $content = json_decode($response->getContent(), false, 512, \JSON_THROW_ON_ERROR);
        $token = $content->respObj;
        if (!isset($token)) {
            throw new \RuntimeException('Authentication failed.');
        }

        return $token;
    }
}
