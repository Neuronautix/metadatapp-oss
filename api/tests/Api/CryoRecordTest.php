<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\CryoRecordFactory;
use App\DataFixtures\Factory\UserFactory;
use App\DataFixtures\Factory\ZebrafishLineFactory;
use App\Enum\CryoRecordStatus;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CryoRecordTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_persists_and_lists_cryo_records_for_a_line(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'firstName' => 'Nora',
            'lastName' => 'Recorder',
            'roles' => [Roles::ROLE_ADMIN],
        ]);
        $line = ZebrafishLineFactory::createOne([
            'name' => 'Tg(cryo:line)',
            'genotype' => 'Tg(cryo:line)zf900',
            'purpose' => 'CRYO tracking line',
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $createResponse = $authenticatedRequest('POST', '/zefix/lines/' . ($line->getId()?->toRfc4122() ?? '') . '/cryo-records', [
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'storageLocation' => 'Freezer A / Box 4 / Rack 2',
                'protocolUsed' => 'Slow freeze v1',
                'storageDate' => '2026-03-18',
                'notes' => 'Initial aliquot for line backup.',
                'status' => '/cryo_record_statuses/' . CryoRecordStatus::STORED->value,
            ],
        ]);

        self::assertSame(201, $createResponse->getStatusCode(), $createResponse->getContent(false));
        self::assertSame('Freezer A / Box 4 / Rack 2', $createResponse->toArray()['storageLocation']);
        self::assertSame(CryoRecordStatus::STORED->value, $createResponse->toArray()['status']['value']);

        $collectionResponse = $authenticatedRequest('GET', '/cryo_records.jsonld?' . http_build_query([
            'line' => $this->findIriBy(\App\Entity\ZebrafishLine::class, ['id' => $line->getId()?->toRfc4122() ?? '']),
        ]), [
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        self::assertSame(200, $collectionResponse->getStatusCode(), $collectionResponse->getContent(false));
        self::assertCount(1, $collectionResponse->toArray()['hydra:member']);
        self::assertSame('Freezer A / Box 4 / Rack 2', $collectionResponse->toArray()['hydra:member'][0]['storageLocation']);
        self::assertSame('Slow freeze v1', $collectionResponse->toArray()['hydra:member'][0]['protocolUsed']);
        self::assertSame(CryoRecordStatus::STORED->value, $collectionResponse->toArray()['hydra:member'][0]['status']['value']);
    }

    #[Test]
    public function it_exports_cryo_inventory_as_csv(): void
    {
        $account = AccountFactory::createOne(['name' => 'North Facility']);
        $user = UserFactory::createOne([
            'account' => $account,
            'roles' => [Roles::ROLE_ADMIN],
        ]);
        $otherLine = ZebrafishLineFactory::createOne([
            'name' => 'Tg(cryo:second)',
            'genotype' => 'Tg(cryo:second)zf901',
            'purpose' => 'Second CRYO line',
            'account' => $account,
        ]);
        $line = ZebrafishLineFactory::createOne([
            'name' => 'Tg(cryo:line)',
            'genotype' => 'Tg(cryo:line)zf900',
            'purpose' => 'CRYO tracking line',
            'account' => $account,
        ]);

        CryoRecordFactory::createOne([
            'line' => $line,
            'storageLocation' => 'Freezer A / Box 4 / Rack 2',
            'protocolUsed' => 'Slow freeze v1',
            'storageDate' => new \DateTimeImmutable('2026-03-18'),
            'notes' => 'Initial aliquot for line backup.',
            'status' => CryoRecordStatus::STORED,
            'account' => $account,
        ]);
        CryoRecordFactory::createOne([
            'line' => $otherLine,
            'storageLocation' => 'Freezer B / Box 1 / Rack 1',
            'protocolUsed' => 'Vapor phase v2',
            'storageDate' => new \DateTimeImmutable('2026-03-20'),
            'notes' => 'Reserve stock.',
            'status' => CryoRecordStatus::USED,
            'account' => $account,
        ]);

        $authenticatedRequest = function (string $method, string $uri, array $options = []) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri, $options);
        };

        $response = $authenticatedRequest('GET', '/zefix/exports/cryo-inventory.csv', [
            'headers' => ['Accept' => 'text/csv'],
        ]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/csv; charset=UTF-8', $response->getHeaders(false)['content-type'][0] ?? null);
        self::assertStringContainsString('Tg(cryo:line)', $response->getContent());
        self::assertStringContainsString('Tg(cryo:second)', $response->getContent());
        self::assertStringContainsString('Freezer A / Box 4 / Rack 2', $response->getContent());
        self::assertStringContainsString('stored', $response->getContent());
    }
}
