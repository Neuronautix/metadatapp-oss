<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\ProjectFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class FairReportControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    // ------------------------------------------------------------------
    // Study FAIR report
    // ------------------------------------------------------------------

    #[Test]
    public function studyFairReportRequiresAuthentication(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->request('GET', \sprintf('/studies/%s/fair-report.pdf', $experiment->getId()));

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function studyFairReportIsAccessibleWhenAuthenticated(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', \sprintf('/studies/%s/fair-report.pdf', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
    }

    #[Test]
    public function studyFairReportContentDispositionContainsStudyId(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/fair-report.pdf', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            \sprintf('experiment-report-study-%s.pdf', $experiment->getId()),
            (string) $response->getHeaders(false)['content-disposition'][0],
        );
    }

    #[Test]
    public function studyFairReportPdfContainsStudyName(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne([
            'name' => 'Cortex Mapping Alpha',
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/fair-report.pdf', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();
        $this->assertStringContainsString('Cortex Mapping Alpha', $content);
    }

    #[Test]
    public function studyFairReportPdfContainsAllFairPillars(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/fair-report.pdf', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();

        foreach (['FINDABLE', 'ACCESSIBLE', 'INTEROPERABLE', 'REUSABLE'] as $pillar) {
            $this->assertStringContainsString($pillar, $content, "Missing pillar: {$pillar}");
        }
    }

    #[Test]
    public function studyFairReportPdfContainsAllFairSubItems(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/fair-report.pdf', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();

        $expectedIds = ['F1', 'F2', 'F3', 'F4', 'A1', 'A1.1', 'A1.2', 'A2', 'I1', 'I2', 'I3', 'R1', 'R1.1', 'R1.2', 'R1.3'];
        foreach ($expectedIds as $criterionId) {
            $this->assertStringContainsString($criterionId, $content, "Missing criterion ID: {$criterionId}");
        }
    }

    #[Test]
    public function studyFairReportReturns404ForUnknownId(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/studies/00000000-0000-0000-0000-000000000000/fair-report.pdf');

        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function studyFairReportReturns400ForInvalidId(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/studies/not-a-valid-uuid/fair-report.pdf');

        $this->assertResponseStatusCodeSame(400);
    }

    // ------------------------------------------------------------------
    // Investigation FAIR report
    // ------------------------------------------------------------------

    #[Test]
    public function investigationFairReportRequiresAuthentication(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->request('GET', \sprintf('/investigations/%s/fair-report.pdf', $project->getId()));

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function investigationFairReportIsAccessibleWhenAuthenticated(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', \sprintf('/investigations/%s/fair-report.pdf', $project->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
    }

    #[Test]
    public function investigationFairReportPdfContainsInvestigationName(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne([
            'name' => 'Neuroplasticity Investigation',
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/investigations/%s/fair-report.pdf', $project->getId()));

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();
        $this->assertStringContainsString('Neuroplasticity Investigation', $content);
    }

    #[Test]
    public function investigationFairReportPdfContainsAllFairPillars(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/investigations/%s/fair-report.pdf', $project->getId()));

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();

        foreach (['FINDABLE', 'ACCESSIBLE', 'INTEROPERABLE', 'REUSABLE'] as $pillar) {
            $this->assertStringContainsString($pillar, $content, "Missing pillar: {$pillar}");
        }
    }

    #[Test]
    public function investigationFairReportPdfContainsAllFairSubItems(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/investigations/%s/fair-report.pdf', $project->getId()));

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();

        $expectedIds = ['F1', 'F2', 'F3', 'F4', 'A1', 'A1.1', 'A1.2', 'A2', 'I1', 'I2', 'I3', 'R1', 'R1.1', 'R1.2', 'R1.3'];
        foreach ($expectedIds as $criterionId) {
            $this->assertStringContainsString($criterionId, $content, "Missing criterion ID: {$criterionId}");
        }
    }

    #[Test]
    public function investigationFairReportReturns404ForUnknownId(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/investigations/00000000-0000-0000-0000-000000000000/fair-report.pdf');

        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function investigationFairReportReturns400ForInvalidId(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/investigations/not-a-valid-uuid/fair-report.pdf');

        $this->assertResponseStatusCodeSame(400);
    }

    // ------------------------------------------------------------------
    // Tenant isolation
    // ------------------------------------------------------------------

    #[Test]
    public function studyFairReportForbidsAccessToOtherAccountStudy(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $owner, 'account' => $owner->getAccount()]);

        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', \sprintf('/studies/%s/fair-report.pdf', $experiment->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function investigationFairReportForbidsAccessToOtherAccountInvestigation(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $owner, 'account' => $owner->getAccount()]);

        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', \sprintf('/investigations/%s/fair-report.pdf', $project->getId()));

        $this->assertResponseStatusCodeSame(403);
    }

    // ------------------------------------------------------------------
    // ARRIVE helper reports
    // ------------------------------------------------------------------

    #[Test]
    public function studyArriveReportIsAccessibleWhenAuthenticated(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne([
            'name' => 'DVC Circadian Study',
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/studies/%s/arrive-report.pdf', $experiment->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
        $this->assertResponseHeaderSame(
            'content-disposition',
            \sprintf('attachment; filename="arrive-report-study-%s.pdf"', $experiment->getId()),
        );
        $this->assertStringContainsString('ARRIVE 2.0 Checklist Helper Report', $response->getContent());
        $this->assertStringContainsString('DVC Circadian Study', $response->getContent());
    }

    #[Test]
    public function investigationArriveReportContainsExpectedSections(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne([
            'name' => 'DVC Demo Investigation',
            'user' => $user,
            'account' => $user->getAccount(),
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', \sprintf('/investigations/%s/arrive-report.pdf', $project->getId()));

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();
        foreach ([
            'ARRIVE Essential 10',
            'ARRIVE Recommended Set',
            '[1] Study design',
            '[10] Results',
            '[21] Declaration of interests',
            'Missing Metadata Warnings',
        ] as $section) {
            $this->assertStringContainsString($section, $content, "Missing ARRIVE section: {$section}");
        }
    }

    #[Test]
    public function investigationArriveReportForbidsAccessToOtherAccountInvestigation(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $project = ProjectFactory::createOne(['user' => $owner, 'account' => $owner->getAccount()]);

        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('GET', \sprintf('/investigations/%s/arrive-report.pdf', $project->getId()));

        $this->assertResponseStatusCodeSame(403);
    }
}
