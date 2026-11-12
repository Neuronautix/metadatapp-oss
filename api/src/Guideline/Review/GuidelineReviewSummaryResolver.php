<?php

declare(strict_types=1);

namespace App\Guideline\Review;

use App\Entity\Account;
use App\Entity\GuidelineReviewEvent;
use App\Repository\GuidelineReviewEventRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Derives the current report-level QC status from the `GuidelineReviewEvent`
 * audit log (§ review workflow) — shared between the completion read path and
 * the review write/read endpoints so both agree on the same status.
 */
final readonly class GuidelineReviewSummaryResolver
{
    public function __construct(private GuidelineReviewEventRepository $reviewEvents)
    {
    }

    /**
     * @return array{status: string, reviewed_by: string|null, reviewed_at: string|null, note: string|null}
     */
    public function summarize(string $resourceType, Uuid $resourceId, string $templateId, Account $account): array
    {
        $latest = $this->reviewEvents->findLatestReportDecision($resourceType, $resourceId, $templateId, $account);
        if (null === $latest) {
            return ['status' => 'not_reviewed', 'reviewed_by' => null, 'reviewed_at' => null, 'note' => null];
        }

        $isApproved = GuidelineReviewEvent::ACTION_REPORT_APPROVED === $latest->getAction();

        // An approval only certifies the content as it stood at that moment. If a
        // field's value changed (or a field was explicitly un-reviewed) afterward,
        // the approval no longer reflects the current content — treat it as if the
        // report were never reviewed rather than keep reporting a stale "approved".
        // A "changes requested" status is not similarly invalidated: further edits
        // are the expected response to that request, not a violation of it.
        if ($isApproved) {
            \assert(null !== $latest->getId());
            $stale = $this->reviewEvents->hasFieldChangeSince($resourceType, $resourceId, $templateId, $account, $latest->getId());
            if ($stale) {
                return ['status' => 'not_reviewed', 'reviewed_by' => null, 'reviewed_at' => null, 'note' => null];
            }
        }

        return [
            'status' => $isApproved ? 'approved' : 'changes_requested',
            'reviewed_by' => $latest->getActor()->getEmail(),
            'reviewed_at' => $latest->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'note' => $latest->getNote(),
        ];
    }
}
