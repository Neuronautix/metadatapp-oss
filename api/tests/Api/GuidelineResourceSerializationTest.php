<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Regression guard: `GuidelineTemplateResource`, `GuidelineConformanceResource`,
 * and `GuidelineCompletionResource` each set a `normalizationContext` group but
 * previously had NO `#[Groups(...)]` attributes on their properties — the
 * Symfony serializer silently stripped every data field, leaving only the
 * JSON-LD `@id`/`@type` stub. This never surfaced in Provider-level unit tests
 * (which bypass the serializer) or MSW-backed frontend tests (which bypass the
 * backend entirely) — only a real HTTP round-trip catches it, which is exactly
 * what these tests exercise.
 */
final class GuidelineResourceSerializationTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function templateCollectionMembersCarryRealData(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/guidelines/templates');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $arrive = current(array_filter($data['hydra:member'], static fn (array $t): bool => 'arrive-v2' === $t['templateId']));
        $this->assertNotFalse($arrive, 'arrive-v2 template missing from the collection');
        $this->assertSame('ARRIVE 2.0', $arrive['name']);
        $this->assertNotSame('', $arrive['version']);
        $this->assertGreaterThan(0, $arrive['requiredMetadataCount']);
    }

    #[Test]
    public function resolvedTemplateItemCarriesRequiredMetadata(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', '/guidelines/templates/arrive-v2');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('ARRIVE 2.0', $data['name']);
        $this->assertNotEmpty($data['requiredMetadata']);
        $this->assertArrayHasKey('arrive_section', $data['requiredMetadata'][0]);
    }

    #[Test]
    public function conformanceReportCarriesScoreAndEntries(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);
        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/guidelines/arrive-v2/conformance', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('ARRIVE 2.0', $data['standard']);
        $this->assertArrayHasKey('points', $data['score']);
        $this->assertNotEmpty($data['entries']);
        $this->assertArrayHasKey('field_id', $data['entries'][0]);
    }

    #[Test]
    public function completionReportCarriesFieldsAndReportReview(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);
        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/guidelines/arrive-v2/completion', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('ARRIVE 2.0', $data['standard']);
        $this->assertNotEmpty($data['fields']);
        $this->assertArrayHasKey('review_status', $data['fields'][0]);
        $this->assertSame('not_reviewed', $data['reportReview']['status']);
    }
}
