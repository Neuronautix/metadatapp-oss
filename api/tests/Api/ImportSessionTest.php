<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ImportSessionTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_creates_an_import_session_and_progresses_through_workflow(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);

        // Upload CSV
        $csvContent = "Subject ID,Sex,Species\n1,Male,Mouse";
        $csvPath = sys_get_temp_dir() . '/test_import.csv';
        file_put_contents($csvPath, $csvContent);

        $uploadedFile = new UploadedFile(
            $csvPath,
            'test_import.csv',
            'text/csv',
            null,
            true
        );

        $response = $client->request('POST', '/curation/sessions', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'files' => [
                    'file' => $uploadedFile,
                ],
            ],
        ]);
        
        if ($response->getStatusCode() !== 201 && $response->getStatusCode() !== 200) {
            dump($response->getContent(false));
        }

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertArrayHasKey('id', $payload);
        $this->assertSame('review_ready', $payload['status']['value']);

        $sessionId = $payload['id'];

        // Get Summary
        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', "/curation/sessions/{$sessionId}/summary", [
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame('review_ready', $payload['status']);

        // Profile workflow
        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', "/curation/sessions/{$sessionId}/profile", [
            'json' => []
        ]);
        $this->assertResponseIsSuccessful();

        @unlink($csvPath);
    }
}
