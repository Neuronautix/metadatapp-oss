<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\ExperimentFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * The guideline QC/review workflow: field-level "mark reviewed" (only on an
 * already-filled field, resets when the value is edited afterward) plus the
 * report-level approve / request-changes sign-off and its audit history.
 *
 * Each sequential HTTP call re-creates the client (mirrors ImportSessionTest):
 * the test client reboots the kernel per request, and re-using one client
 * across calls that persist new entities referencing the authenticated user
 * hits an unrelated Doctrine identity quirk this suite does not exercise.
 */
final class GuidelineReviewControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    private const string TEMPLATE_ID = 'arrive-v2';
    private const string FIELD_ID = 'study_title';

    #[Test]
    public function reviewingAFieldWithoutAValueReturnsNotFound(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', $this->fieldReviewPath($experiment->getId()), [
            'json' => ['status' => 'reviewed'],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function markingAFilledFieldReviewedRecordsTheReviewer(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Alpha']]);
        $this->assertResponseIsSuccessful();

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', $this->fieldReviewPath($experiment->getId()), [
            'json' => ['status' => 'reviewed'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('reviewed', $data['review_status']);
        $this->assertSame($user->getEmail(), $data['reviewed_by']);
        $this->assertNotNull($data['reviewed_at']);
    }

    #[Test]
    public function editingAReviewedFieldResetsItToDraft(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Alpha']]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', $this->fieldReviewPath($experiment->getId()), ['json' => ['status' => 'reviewed']]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Beta']]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('draft', $response->toArray()['review_status']);
    }

    #[Test]
    public function resavingTheIdenticalValueDoesNotResetAnExistingReview(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Alpha']]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', $this->fieldReviewPath($experiment->getId()), ['json' => ['status' => 'reviewed']]);

        // Re-save the exact same value — e.g. clicking Save without editing anything.
        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Alpha']]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('reviewed', $response->toArray()['review_status']);

        $client = static::createClient();
        $client->loginUser($user);
        $history = $client->request('GET', $this->reportReviewPath($experiment->getId()))->toArray()['history'];
        $this->assertSame([], array_filter($history, static fn (array $e): bool => 'field_value_changed' === $e['action']));
    }

    #[Test]
    public function unfilledReportHasNotReviewedStatus(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('GET', $this->reportReviewPath($experiment->getId()));

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('not_reviewed', $data['status']);
        $this->assertSame([], $data['history']);
    }

    #[Test]
    public function approvingTheReportRecordsAnAuditEvent(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', $this->reportReviewPath($experiment->getId()), [
            'json' => ['decision' => 'approved', 'note' => 'Looks complete.'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('approved', $data['status']);
        $this->assertSame($user->getEmail(), $data['reviewed_by']);
        $this->assertCount(1, $data['history']);
        $this->assertSame('report_approved', $data['history'][0]['action']);
        $this->assertSame('Looks complete.', $data['history'][0]['note']);
    }

    #[Test]
    public function requestingChangesSupersedesAPriorApproval(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', $this->reportReviewPath($experiment->getId()), ['json' => ['decision' => 'approved']]);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', $this->reportReviewPath($experiment->getId()), ['json' => ['decision' => 'changes_requested']]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertSame('changes_requested', $data['status']);
        $this->assertCount(2, $data['history']);
    }

    #[Test]
    public function editingAFieldAfterApprovalInvalidatesIt(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Alpha']]);

        $client = static::createClient();
        $client->loginUser($user);
        $approved = $client->request('POST', $this->reportReviewPath($experiment->getId()), ['json' => ['decision' => 'approved']]);
        $this->assertSame('approved', $approved->toArray()['status']);

        // Editing a field after approval must invalidate it — an "Approved" stamp
        // must never describe content that has since changed.
        $client = static::createClient();
        $client->loginUser($user);
        $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Beta']]);

        $client = static::createClient();
        $client->loginUser($user);
        $review = $client->request('GET', $this->reportReviewPath($experiment->getId()));

        $this->assertResponseIsSuccessful();
        $data = $review->toArray();
        $this->assertSame('not_reviewed', $data['status']);
        $this->assertNull($data['reviewed_by']);
        // The audit trail still records the original approval — only the current
        // headline status is invalidated, not the history.
        $this->assertContains('report_approved', array_column($data['history'], 'action'));
    }

    #[Test]
    public function unreviewingAFieldAfterApprovalInvalidatesIt(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Alpha']]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', $this->fieldReviewPath($experiment->getId()), ['json' => ['status' => 'reviewed']]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', $this->reportReviewPath($experiment->getId()), ['json' => ['decision' => 'approved']]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', $this->fieldReviewPath($experiment->getId()), ['json' => ['status' => 'draft']]);

        $client = static::createClient();
        $client->loginUser($user);
        $review = $client->request('GET', $this->reportReviewPath($experiment->getId()));

        $this->assertSame('not_reviewed', $review->toArray()['status']);
    }

    #[Test]
    public function requestedChangesAreNotInvalidatedByFurtherEdits(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Alpha']]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', $this->reportReviewPath($experiment->getId()), ['json' => ['decision' => 'changes_requested']]);

        // Editing in response to a changes-requested decision is expected — it must
        // NOT be treated as invalidating anything (there is nothing to invalidate).
        $client = static::createClient();
        $client->loginUser($user);
        $client->request('PUT', $this->fieldPath($experiment->getId()), ['json' => ['value' => 'Cortex Mapping Beta']]);

        $client = static::createClient();
        $client->loginUser($user);
        $review = $client->request('GET', $this->reportReviewPath($experiment->getId()));

        $this->assertSame('changes_requested', $review->toArray()['status']);
    }

    #[Test]
    public function anInvalidDecisionIsRejected(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $experiment = ExperimentFactory::createOne(['user' => $user, 'account' => $user->getAccount()]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('POST', $this->reportReviewPath($experiment->getId()), ['json' => ['decision' => 'maybe']]);

        $this->assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function reviewEndpointsAreTenantIsolated(): void
    {
        $ownerAccount = AccountFactory::createOne();
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER], 'account' => $ownerAccount]);
        $experiment = ExperimentFactory::createOne(['user' => $owner, 'account' => $ownerAccount]);

        $otherAccount = AccountFactory::createOne();
        $otherAccountUser = UserFactory::createOne(['roles' => [Roles::ROLE_USER], 'account' => $otherAccount]);

        $client = static::createClient();
        $client->loginUser($otherAccountUser);
        $client->request('POST', $this->reportReviewPath($experiment->getId()), ['json' => ['decision' => 'approved']]);

        $this->assertResponseStatusCodeSame(403);
    }

    private function fieldPath(mixed $studyId): string
    {
        return \sprintf('/studies/%s/guidelines/%s/fields/%s', $studyId, self::TEMPLATE_ID, self::FIELD_ID);
    }

    private function fieldReviewPath(mixed $studyId): string
    {
        return $this->fieldPath($studyId) . '/review';
    }

    private function reportReviewPath(mixed $studyId): string
    {
        return \sprintf('/studies/%s/guidelines/%s/review', $studyId, self::TEMPLATE_ID);
    }
}
