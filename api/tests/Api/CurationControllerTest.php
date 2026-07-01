<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\StrainFactory;
use App\DataFixtures\Factory\SubjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\CurationSuggestion;
use App\Entity\Subject;
use App\Enum\CurationSuggestionStatus;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class CurationControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function it_persists_and_lists_subject_curation_suggestions(): void
    {
        putenv('LLM_CURATION_MOCK_MODE=normal');
        $_ENV['LLM_CURATION_MOCK_MODE'] = 'normal';
        $_SERVER['LLM_CURATION_MOCK_MODE'] = 'normal';
        self::ensureKernelShutdown();

        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $strain = StrainFactory::createOne(['name' => ' C57BL/6J  ']);
        $subject = SubjectFactory::createOne([
            'account' => $user->getAccount(),
            'strain' => $strain,
            'name' => '  Mouse 001  ',
            'sex' => Sex::Male,
            'isPregnant' => true,
            'genotype' => null,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', '/curation/suggest/' . $subject->getId());

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertSame((string) $subject->getId(), $payload['recordId']);
        $this->assertGreaterThanOrEqual(4, \count($payload['suggestions']));
        $this->assertSame('pending_review', $payload['suggestions'][0]['status']);
        $this->assertSame('subject', $payload['suggestions'][0]['resourceType']);
        $this->assertSame((string) $subject->getId(), $payload['suggestions'][0]['resourceId']);
        $this->assertArrayHasKey('providerName', $payload['suggestions'][0]);

        $client = static::createClient();
        $client->loginUser($user);
        $listResponse = $client->request('GET', '/curation/suggestions/' . $subject->getId());

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(4, \count($listResponse->toArray()['suggestions']));
    }

    #[Test]
    public function it_accepts_and_rejects_suggestions_through_review_flow(): void
    {
        putenv('LLM_CURATION_MOCK_MODE=normal');
        $_ENV['LLM_CURATION_MOCK_MODE'] = 'normal';
        $_SERVER['LLM_CURATION_MOCK_MODE'] = 'normal';
        self::ensureKernelShutdown();

        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $strain = StrainFactory::createOne(['name' => ' Strain Alpha ']);
        $subject = SubjectFactory::createOne([
            'account' => $user->getAccount(),
            'strain' => $strain,
            'name' => '  Mouse 002  ',
            'sex' => Sex::Male,
            'isPregnant' => true,
            'genotype' => ' ab12 ',
            'healthStatus' => HealthStatus::NORMAL,
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', '/curation/suggest/' . $subject->getId());

        /** @var CurationSuggestion $nameSuggestion */
        $nameSuggestion = static::getContainer()->get('doctrine')->getRepository(CurationSuggestion::class)->findOneBy([
            'fieldName' => 'name',
        ]);
        /** @var CurationSuggestion $pregnancySuggestion */
        $pregnancySuggestion = static::getContainer()->get('doctrine')->getRepository(CurationSuggestion::class)->findOneBy([
            'fieldName' => 'isPregnant',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $acceptResponse = $client->request('POST', '/curation/suggestions/' . $nameSuggestion->getId() . '/accept');
        $this->assertResponseIsSuccessful();

        $client = static::createClient();
        $client->loginUser($user);
        $rejectResponse = $client->request('POST', '/curation/suggestions/' . $pregnancySuggestion->getId() . '/reject');
        $this->assertResponseIsSuccessful();

        $this->assertSame(CurationSuggestionStatus::Rejected->value, $rejectResponse->toArray()['suggestion']['status']);
        $this->assertSame(CurationSuggestionStatus::Accepted->value, $acceptResponse->toArray()['suggestion']['status']);

        /** @var Subject $updatedSubject */
        $updatedSubject = static::getContainer()->get('doctrine')->getRepository(Subject::class)->find($subject->getId());
        $this->assertSame('Mouse 002', $updatedSubject->getName());
        $this->assertTrue($updatedSubject->getIsPregnant());
    }

    #[Test]
    public function it_reuses_normalized_strains_and_accepts_normalized_health_status_values(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $existingStrain = StrainFactory::createOne(['name' => ' Existing Strain ']);
        $subject = SubjectFactory::createOne([
            'account' => $user->getAccount(),
            'strain' => $existingStrain,
            'healthStatus' => HealthStatus::WARNING_LEVEL_1,
        ]);

        $entityManager = static::getContainer()->get(\App\Repository\SubjectRepository::class)->getEntityManager();

        $strainSuggestion = (new CurationSuggestion())
            ->setSubject($subject)
            ->setTaskType(\App\Enum\CurationTaskType::ValueNormalization)
            ->setStatus(CurationSuggestionStatus::PendingReview)
            ->setFieldName('strain')
            ->setCurrentValue(' Existing Strain ')
            ->setProposedValue('Existing Strain')
            ->setReason('Normalize strain whitespace.');

        $healthStatusSuggestion = (new CurationSuggestion())
            ->setSubject($subject)
            ->setTaskType(\App\Enum\CurationTaskType::ValueNormalization)
            ->setStatus(CurationSuggestionStatus::PendingReview)
            ->setFieldName('healthStatus')
            ->setCurrentValue(HealthStatus::WARNING_LEVEL_1->value)
            ->setProposedValue('warning level1')
            ->setReason('Normalize health status casing.');

        $entityManager->persist($strainSuggestion);
        $entityManager->persist($healthStatusSuggestion);
        $entityManager->flush();

        $client = static::createClient();
        $client->loginUser($user);

        $client->loginUser($user);
        $client->request('POST', '/curation/suggestions/' . $strainSuggestion->getId() . '/accept');
        $client->loginUser($user);
        $client->request('POST', '/curation/suggestions/' . $healthStatusSuggestion->getId() . '/accept');

        /** @var Subject $updatedSubject */
        $updatedSubject = static::getContainer()->get('doctrine')->getRepository(Subject::class)->find($subject->getId());
        $this->assertSame((string) $existingStrain->getId(), (string) $updatedSubject->getStrain()->getId());
        $this->assertSame(HealthStatus::WARNING_LEVEL_1, $updatedSubject->getHealthStatus());
    }

    #[Test]
    public function it_records_failed_suggestions_when_llm_output_is_invalid(): void
    {
        putenv('LLM_CURATION_MOCK_MODE=invalid_json');
        $_ENV['LLM_CURATION_MOCK_MODE'] = 'invalid_json';
        $_SERVER['LLM_CURATION_MOCK_MODE'] = 'invalid_json';
        self::ensureKernelShutdown();

        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $subject = SubjectFactory::createOne([
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/curation/suggest/' . $subject->getId());

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        $this->assertCount(1, $payload['suggestions']);
        $this->assertSame(CurationSuggestionStatus::Failed->value, $payload['suggestions'][0]['status']);
        $this->assertSame('invalid_llm_output', $payload['suggestions'][0]['warningCode']);
        $this->assertSame('mock', $payload['suggestions'][0]['providerName']);
        $this->assertSame('LLM response must be valid JSON.', $payload['suggestions'][0]['failureDetails']['error']);

        putenv('LLM_CURATION_MOCK_MODE=normal');
        $_ENV['LLM_CURATION_MOCK_MODE'] = 'normal';
        $_SERVER['LLM_CURATION_MOCK_MODE'] = 'normal';
        self::ensureKernelShutdown();
    }
}
