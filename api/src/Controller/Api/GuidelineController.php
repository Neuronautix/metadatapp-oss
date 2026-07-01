<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\GuidelineFieldValue;
use App\Entity\GuidelineReviewEvent;
use App\Entity\User;
use App\Guideline\ConformanceEngine;
use App\Guideline\Evidence\EvidenceBundle;
use App\Guideline\Evidence\EvidenceItem;
use App\Guideline\Evidence\GuidelineEvidenceRetriever;
use App\Guideline\GuidelineAiFiller;
use App\Guideline\GuidelineResourceLocator;
use App\Guideline\Report\ConformanceReport;
use App\Guideline\Report\GuidelineDocumentRenderer;
use App\Guideline\Review\GuidelineReviewSummaryResolver;
use App\Guideline\Schema\GuidelineTemplate;
use App\Guideline\Schema\RequiredMetadata;
use App\Guideline\TemplateLoader;
use App\Repository\GuidelineFieldValueRepository;
use App\Repository\GuidelineReviewEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Write + render surface for the International Guidelines Reporting pipeline.
 *
 *  PUT  /studies|investigations/{id}/guidelines/{templateId}/fields/{fieldId}            → upsert manual value
 *  POST /studies|investigations/{id}/guidelines/{templateId}/fields/{fieldId}/ai-draft   → AI draft candidate (no persist)
 *  POST /studies|investigations/{id}/guidelines/{templateId}/fields/{fieldId}/review      → mark a filled field reviewed/draft (QC)
 *  POST /studies|investigations/{id}/guidelines/{templateId}/review                       → report-level sign-off decision (QC)
 *  GET  /studies|investigations/{id}/guidelines/{templateId}/review                       → current sign-off + audit history
 *  GET  /studies|investigations/{id}/guidelines/{templateId}/report.md                   → human-readable docs (markdown)
 *  GET  /studies|investigations/{id}/guidelines/{templateId}/report.zip                  → zip of the docs
 *
 * All ROLE_USER + tenant-isolated (the locator enforces account match).
 */
#[IsGranted('ROLE_USER')]
final class GuidelineController extends AbstractController
{
    public function __construct(
        private readonly TemplateLoader $loader,
        private readonly ConformanceEngine $engine,
        private readonly GuidelineResourceLocator $locator,
        private readonly GuidelineFieldValueRepository $fieldValues,
        private readonly GuidelineReviewEventRepository $reviewEvents,
        private readonly GuidelineReviewSummaryResolver $reviewSummaryResolver,
        private readonly GuidelineAiFiller $aiFiller,
        private readonly GuidelineDocumentRenderer $renderer,
        private readonly GuidelineEvidenceRetriever $evidenceRetriever,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Max fields drafted in one bulk auto-fill call (review queue). */
    private const int BULK_DRAFT_CAP = 25;

    #[Route(
        path: '/studies/{id}/guidelines/{templateId}/fields/{fieldId}',
        name: 'api_guideline_study_field_put',
        methods: ['PUT'],
    )]
    public function putStudyField(string $id, string $templateId, string $fieldId, Request $request): JsonResponse
    {
        $this->locator->findStudy($id);

        return $this->upsert(GuidelineFieldValue::RESOURCE_STUDY, $id, $templateId, $fieldId, $request);
    }

    #[Route(
        path: '/investigations/{id}/guidelines/{templateId}/fields/{fieldId}',
        name: 'api_guideline_investigation_field_put',
        methods: ['PUT'],
    )]
    public function putInvestigationField(string $id, string $templateId, string $fieldId, Request $request): JsonResponse
    {
        $this->locator->findInvestigation($id);

        return $this->upsert(GuidelineFieldValue::RESOURCE_INVESTIGATION, $id, $templateId, $fieldId, $request);
    }

    #[Route(
        path: '/studies/{id}/guidelines/{templateId}/fields/{fieldId}/review',
        name: 'api_guideline_study_field_review',
        methods: ['POST'],
    )]
    public function reviewStudyField(string $id, string $templateId, string $fieldId, Request $request): JsonResponse
    {
        $this->locator->findStudy($id);

        return $this->reviewField(GuidelineFieldValue::RESOURCE_STUDY, $id, $templateId, $fieldId, $request);
    }

    #[Route(
        path: '/investigations/{id}/guidelines/{templateId}/fields/{fieldId}/review',
        name: 'api_guideline_investigation_field_review',
        methods: ['POST'],
    )]
    public function reviewInvestigationField(string $id, string $templateId, string $fieldId, Request $request): JsonResponse
    {
        $this->locator->findInvestigation($id);

        return $this->reviewField(GuidelineFieldValue::RESOURCE_INVESTIGATION, $id, $templateId, $fieldId, $request);
    }

    #[Route(
        path: '/studies/{id}/guidelines/{templateId}/review',
        name: 'api_guideline_study_report_review_post',
        methods: ['POST'],
    )]
    public function submitStudyReportReview(string $id, string $templateId, Request $request): JsonResponse
    {
        $this->locator->findStudy($id);

        return $this->submitReportReview(GuidelineFieldValue::RESOURCE_STUDY, $id, $templateId, $request);
    }

    #[Route(
        path: '/investigations/{id}/guidelines/{templateId}/review',
        name: 'api_guideline_investigation_report_review_post',
        methods: ['POST'],
    )]
    public function submitInvestigationReportReview(string $id, string $templateId, Request $request): JsonResponse
    {
        $this->locator->findInvestigation($id);

        return $this->submitReportReview(GuidelineFieldValue::RESOURCE_INVESTIGATION, $id, $templateId, $request);
    }

    #[Route(
        path: '/studies/{id}/guidelines/{templateId}/review',
        name: 'api_guideline_study_report_review_get',
        methods: ['GET'],
    )]
    public function studyReportReview(string $id, string $templateId): JsonResponse
    {
        $this->locator->findStudy($id);

        return $this->reportReviewState(GuidelineFieldValue::RESOURCE_STUDY, $id, $templateId);
    }

    #[Route(
        path: '/investigations/{id}/guidelines/{templateId}/review',
        name: 'api_guideline_investigation_report_review_get',
        methods: ['GET'],
    )]
    public function investigationReportReview(string $id, string $templateId): JsonResponse
    {
        $this->locator->findInvestigation($id);

        return $this->reportReviewState(GuidelineFieldValue::RESOURCE_INVESTIGATION, $id, $templateId);
    }

    #[Route(
        path: '/studies/{id}/guidelines/{templateId}/fields/{fieldId}/ai-draft',
        name: 'api_guideline_study_field_ai_draft',
        methods: ['POST'],
    )]
    public function aiDraftStudyField(string $id, string $templateId, string $fieldId): JsonResponse
    {
        $experiment = $this->locator->findStudy($id);
        $ctx = $this->locator->locateStudy($id);

        return $this->aiDraft($templateId, $fieldId, $ctx['metadata'], $this->evidenceRetriever->retrieveForStudy($experiment));
    }

    #[Route(
        path: '/investigations/{id}/guidelines/{templateId}/fields/{fieldId}/ai-draft',
        name: 'api_guideline_investigation_field_ai_draft',
        methods: ['POST'],
    )]
    public function aiDraftInvestigationField(string $id, string $templateId, string $fieldId): JsonResponse
    {
        $project = $this->locator->findInvestigation($id);
        $ctx = $this->locator->locateInvestigation($id);

        return $this->aiDraft($templateId, $fieldId, $ctx['metadata'], $this->evidenceRetriever->retrieveForInvestigation($project));
    }

    #[Route(
        path: '/studies/{id}/guidelines/{templateId}/fields/{fieldId}/evidence',
        name: 'api_guideline_study_field_evidence',
        methods: ['GET'],
    )]
    public function studyFieldEvidence(string $id, string $templateId, string $fieldId): JsonResponse
    {
        $experiment = $this->locator->findStudy($id);

        return $this->fieldEvidence($templateId, $fieldId, $this->evidenceRetriever->retrieveForStudy($experiment));
    }

    #[Route(
        path: '/investigations/{id}/guidelines/{templateId}/fields/{fieldId}/evidence',
        name: 'api_guideline_investigation_field_evidence',
        methods: ['GET'],
    )]
    public function investigationFieldEvidence(string $id, string $templateId, string $fieldId): JsonResponse
    {
        $project = $this->locator->findInvestigation($id);

        return $this->fieldEvidence($templateId, $fieldId, $this->evidenceRetriever->retrieveForInvestigation($project));
    }

    #[Route(
        path: '/studies/{id}/guidelines/{templateId}/ai-draft-all',
        name: 'api_guideline_study_ai_draft_all',
        methods: ['POST'],
    )]
    public function aiDraftAllStudy(string $id, string $templateId): JsonResponse
    {
        $experiment = $this->locator->findStudy($id);
        $ctx = $this->locator->locateStudy($id);

        return $this->aiDraftAll($templateId, $ctx['metadata'], $this->evidenceRetriever->retrieveForStudy($experiment));
    }

    #[Route(
        path: '/investigations/{id}/guidelines/{templateId}/ai-draft-all',
        name: 'api_guideline_investigation_ai_draft_all',
        methods: ['POST'],
    )]
    public function aiDraftAllInvestigation(string $id, string $templateId): JsonResponse
    {
        $project = $this->locator->findInvestigation($id);
        $ctx = $this->locator->locateInvestigation($id);

        return $this->aiDraftAll($templateId, $ctx['metadata'], $this->evidenceRetriever->retrieveForInvestigation($project));
    }

    #[Route(
        path: '/studies/{id}/guidelines/{templateId}/report.md',
        name: 'api_guideline_study_report_md',
        methods: ['GET'],
    )]
    public function studyReportMarkdown(string $id, string $templateId): Response
    {
        $ctx = $this->locator->locateStudy($id);

        return $this->markdownResponse($templateId, $ctx['columns'], $ctx['metadata'], $ctx['resourceType'], $ctx['resourceId']);
    }

    #[Route(
        path: '/investigations/{id}/guidelines/{templateId}/report.md',
        name: 'api_guideline_investigation_report_md',
        methods: ['GET'],
    )]
    public function investigationReportMarkdown(string $id, string $templateId): Response
    {
        $ctx = $this->locator->locateInvestigation($id);

        return $this->markdownResponse($templateId, $ctx['columns'], $ctx['metadata'], $ctx['resourceType'], $ctx['resourceId']);
    }

    #[Route(
        path: '/studies/{id}/guidelines/{templateId}/report.zip',
        name: 'api_guideline_study_report_zip',
        methods: ['GET'],
    )]
    public function studyReportZip(string $id, string $templateId): Response
    {
        $ctx = $this->locator->locateStudy($id);

        return $this->zipResponse($templateId, $ctx['columns'], $ctx['metadata'], $ctx['resourceType'], $ctx['resourceId']);
    }

    #[Route(
        path: '/investigations/{id}/guidelines/{templateId}/report.zip',
        name: 'api_guideline_investigation_report_zip',
        methods: ['GET'],
    )]
    public function investigationReportZip(string $id, string $templateId): Response
    {
        $ctx = $this->locator->locateInvestigation($id);

        return $this->zipResponse($templateId, $ctx['columns'], $ctx['metadata'], $ctx['resourceType'], $ctx['resourceId']);
    }

    private function upsert(string $resourceType, string $id, string $templateId, string $fieldId, Request $request): JsonResponse
    {
        $template = $this->requireTemplate($templateId);
        $this->requireMetadataField($template, $fieldId);

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload) || !\array_key_exists('value', $payload)) {
            return new JsonResponse(['message' => 'Body must be a JSON object with a "value" key.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->locator->currentUser();
        $resourceUuid = Uuid::fromString($id);

        $existing = $this->fieldValues->findOneForField($resourceType, $resourceUuid, $templateId, $fieldId, $user->getAccount());
        $entity = $existing;
        if (null === $entity) {
            $entity = new GuidelineFieldValue();
            $entity->setResourceType($resourceType)
                ->setResourceId($resourceUuid)
                ->setTemplateId($templateId)
                ->setFieldId($fieldId)
                ->setAccount($user->getAccount())
                ->setUser($this->managedUser($user))
            ;
        }

        $wasReviewed = null !== $existing && GuidelineFieldValue::REVIEW_REVIEWED === $existing->getReviewStatus();
        // A first-time fill is not a "change" (nothing to compare against), and a
        // re-save of the exact same value (e.g. clicking Save without editing) is
        // not a content change either — neither should invalidate an existing
        // review or log a field_value_changed event.
        $valueChanged = null !== $existing && $existing->getValue() !== $payload['value'];

        $entity->setValue($payload['value'])
            ->setSource(GuidelineFieldValue::SOURCE_MANUAL)
            ->touch()
        ;

        if ($wasReviewed && $valueChanged) {
            // An edited value needs a fresh QC pass — invalidate the prior review.
            $entity->resetReview();
        }

        $this->entityManager->persist($entity);

        if ($valueChanged) {
            // Logged regardless of this field's own review state (not just when
            // $wasReviewed) — a report-level approval can be invalidated by a change
            // to ANY field, not only ones that were individually marked reviewed.
            $this->logReviewEvent($resourceType, $resourceUuid, $templateId, $fieldId, GuidelineReviewEvent::ACTION_FIELD_VALUE_CHANGED);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'resource_type' => $resourceType,
            'resource_id' => $resourceUuid->toRfc4122(),
            'template_id' => $templateId,
            'field_id' => $fieldId,
            'value' => $entity->getValue(),
            'source' => $entity->getSource(),
            'updated_at' => $entity->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'review_status' => $entity->getReviewStatus(),
        ], Response::HTTP_OK);
    }

    /**
     * Toggle QC review state on an already-filled field. 404s when the field has
     * no value yet — there is nothing to review.
     */
    private function reviewField(string $resourceType, string $id, string $templateId, string $fieldId, Request $request): JsonResponse
    {
        $template = $this->requireTemplate($templateId);
        $this->requireMetadataField($template, $fieldId);

        $payload = json_decode($request->getContent(), true);
        $status = \is_array($payload) ? ($payload['status'] ?? null) : null;
        if (!\in_array($status, [GuidelineFieldValue::REVIEW_DRAFT, GuidelineFieldValue::REVIEW_REVIEWED], true)) {
            return new JsonResponse(
                ['message' => 'Body must be a JSON object with a "status" key of "reviewed" or "draft".'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $user = $this->locator->currentUser();
        $resourceUuid = Uuid::fromString($id);

        $entity = $this->fieldValues->findOneForField($resourceType, $resourceUuid, $templateId, $fieldId, $user->getAccount());
        if (null === $entity) {
            throw new NotFoundHttpException(\sprintf('Field "%s" has no value yet — nothing to review.', $fieldId));
        }

        $note = \is_array($payload) && \is_string($payload['note'] ?? null) ? $payload['note'] : null;

        if (GuidelineFieldValue::REVIEW_REVIEWED === $status) {
            $entity->markReviewed($this->managedUser($user));
            $action = GuidelineReviewEvent::ACTION_FIELD_REVIEWED;
        } else {
            $entity->resetReview();
            $action = GuidelineReviewEvent::ACTION_FIELD_UNREVIEWED;
        }

        $this->entityManager->persist($entity);
        $this->logReviewEvent($resourceType, $resourceUuid, $templateId, $fieldId, $action, $note);
        $this->entityManager->flush();

        return new JsonResponse([
            'resource_type' => $resourceType,
            'resource_id' => $resourceUuid->toRfc4122(),
            'template_id' => $templateId,
            'field_id' => $fieldId,
            'review_status' => $entity->getReviewStatus(),
            'reviewed_by' => $entity->getReviewedBy()?->getEmail(),
            'reviewed_at' => $entity->getReviewedAt()?->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_OK);
    }

    /**
     * Record a report-level QC decision (approve the whole compliance report for
     * this resource + template, or send it back for changes). Purely additive to
     * the audit log — the "current" status is always derived by reading the
     * latest report-level event, so there is nothing else to update.
     */
    private function submitReportReview(string $resourceType, string $id, string $templateId, Request $request): JsonResponse
    {
        $this->requireTemplate($templateId);

        $payload = json_decode($request->getContent(), true);
        $decision = \is_array($payload) ? ($payload['decision'] ?? null) : null;
        $action = match ($decision) {
            'approved' => GuidelineReviewEvent::ACTION_REPORT_APPROVED,
            'changes_requested' => GuidelineReviewEvent::ACTION_REPORT_CHANGES_REQUESTED,
            default => null,
        };
        if (null === $action) {
            return new JsonResponse(
                ['message' => 'Body must be a JSON object with a "decision" key of "approved" or "changes_requested".'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $note = \is_array($payload) && \is_string($payload['note'] ?? null) ? $payload['note'] : null;
        $resourceUuid = Uuid::fromString($id);
        $this->logReviewEvent($resourceType, $resourceUuid, $templateId, null, $action, $note);
        $this->entityManager->flush();

        return $this->reportReviewState($resourceType, $id, $templateId);
    }

    private function reportReviewState(string $resourceType, string $id, string $templateId): JsonResponse
    {
        $this->requireTemplate($templateId);

        $resourceUuid = Uuid::fromString($id);
        $account = $this->locator->currentUser()->getAccount();

        $history = $this->reviewEvents->findHistory($resourceType, $resourceUuid, $templateId, $account);

        return new JsonResponse([
            'resource_type' => $resourceType,
            'resource_id' => $resourceUuid->toRfc4122(),
            'template_id' => $templateId,
            ...$this->reviewSummaryResolver->summarize($resourceType, $resourceUuid, $templateId, $account),
            'history' => array_map(fn (GuidelineReviewEvent $event): array => $this->eventToArray($event), $history),
        ], Response::HTTP_OK);
    }

    /**
     * @return array{action: string, field_id: string|null, actor: string, occurred_at: string, note: string|null}
     */
    private function eventToArray(GuidelineReviewEvent $event): array
    {
        return [
            'action' => $event->getAction(),
            'field_id' => $event->getFieldId(),
            'actor' => $event->getActor()->getEmail(),
            'occurred_at' => $event->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'note' => $event->getNote(),
        ];
    }

    private function logReviewEvent(
        string $resourceType,
        Uuid $resourceUuid,
        string $templateId,
        ?string $fieldId,
        string $action,
        ?string $note = null,
    ): void {
        $user = $this->locator->currentUser();

        $event = new GuidelineReviewEvent();
        $event->setResourceType($resourceType)
            ->setResourceId($resourceUuid)
            ->setTemplateId($templateId)
            ->setFieldId($fieldId)
            ->setAction($action)
            ->setActor($this->managedUser($user))
            ->setAccount($user->getAccount())
            ->setNote($note)
        ;

        $this->entityManager->persist($event);
    }

    /**
     * The security token's User is not guaranteed to be the instance tracked by
     * this request's EntityManager (see App\Curation\Application\CreateImportSession
     * for the same defensive pattern) — resolve a proper managed reference before
     * attaching it to a new/modified entity relation.
     */
    private function managedUser(User $user): User
    {
        $reference = $this->entityManager->getReference(User::class, $user->getId());
        \assert($reference instanceof User);

        return $reference;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function aiDraft(string $templateId, string $fieldId, array $metadata, EvidenceBundle $bundle): JsonResponse
    {
        $template = $this->requireTemplate($templateId);
        $field = $this->requireMetadataField($template, $fieldId);

        $draft = $this->aiFiller->draft($template, $field, $metadata, $bundle);

        return new JsonResponse($draft->toArray(), Response::HTTP_OK);
    }

    /**
     * Deterministic evidence + suggestions for one field (no AI needed).
     */
    private function fieldEvidence(string $templateId, string $fieldId, EvidenceBundle $bundle): JsonResponse
    {
        $template = $this->requireTemplate($templateId);
        $field = $this->requireMetadataField($template, $fieldId);

        return new JsonResponse([
            'field_id' => $field->id,
            'computed' => array_map(static fn (EvidenceItem $i): array => $i->toArray(), $bundle->computedFacts()),
            'suggestions' => array_map(static fn (EvidenceItem $i): array => $i->toSuggestionArray(), $bundle->suggestionsForField($field)),
            'evidence' => array_map(static fn (EvidenceItem $i): array => $i->toArray(), $bundle->forField($field)),
        ], Response::HTTP_OK);
    }

    /**
     * Bulk auto-fill (review queue): draft every currently-missing required-metadata
     * field via the evidence-grounded filler. Persists NOTHING — the user reviews
     * then saves via the existing PUT. Degrades per-field: even with AI unavailable
     * the deterministic suggestions are returned so the queue is useful offline.
     *
     * @param array<string, mixed> $metadata
     */
    private function aiDraftAll(string $templateId, array $metadata, EvidenceBundle $bundle): JsonResponse
    {
        $template = $this->requireTemplate($templateId);

        $missing = [];
        foreach ($template->requiredMetadata as $field) {
            if (!$this->isFilled($metadata, $field->id)) {
                $missing[] = $field;
            }
        }

        $capped = \count($missing) > self::BULK_DRAFT_CAP;
        if ($capped) {
            $this->logger->info('Guideline bulk draft capped.', [
                'template_id' => $templateId,
                'missing' => \count($missing),
                'cap' => self::BULK_DRAFT_CAP,
            ]);
            $missing = \array_slice($missing, 0, self::BULK_DRAFT_CAP);
        }

        $drafted = [];
        $skipped = [];
        foreach ($missing as $field) {
            $draft = $this->aiFiller->draft($template, $field, $metadata, $bundle);
            $row = $draft->toArray();
            // The bulk queue exposes the review-relevant subset of the draft shape.
            $drafted[] = [
                'field_id' => $row['field_id'],
                'value' => $row['value'],
                'rationale' => $row['rationale'],
                'available' => $row['available'],
                'used_evidence' => $row['used_evidence'],
                'suggestions' => $row['suggestions'],
            ];
        }

        // Already-satisfied fields are reported as skipped (not re-drafted).
        foreach ($template->requiredMetadata as $field) {
            if ($this->isFilled($metadata, $field->id)) {
                $skipped[] = $field->id;
            }
        }

        return new JsonResponse([
            'template_id' => $templateId,
            'drafted' => $drafted,
            'skipped' => $skipped,
            'capped' => $capped,
        ], Response::HTTP_OK);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function isFilled(array $metadata, string $fieldId): bool
    {
        if (!\array_key_exists($fieldId, $metadata)) {
            return false;
        }
        $value = $metadata[$fieldId];
        if (null === $value) {
            return false;
        }
        if (\is_string($value)) {
            return '' !== trim($value);
        }
        if (\is_array($value)) {
            return [] !== $value;
        }

        return true;
    }

    /**
     * @param list<\App\Guideline\CsvColumn> $columns
     * @param array<string, mixed>           $metadata
     */
    private function markdownResponse(string $templateId, array $columns, array $metadata, string $resourceType, Uuid $resourceUuid): Response
    {
        $template = $this->requireTemplate($templateId);
        $docs = $this->renderDocuments($template, $columns, $metadata, $resourceType, $resourceUuid);

        $body = $docs['plan'] . "\n\n---\n\n" . $docs['checklist'];

        return new Response($body, Response::HTTP_OK, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => \sprintf('attachment; filename="%s-report.md"', $templateId),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param list<\App\Guideline\CsvColumn> $columns
     * @param array<string, mixed>           $metadata
     */
    private function zipResponse(string $templateId, array $columns, array $metadata, string $resourceType, Uuid $resourceUuid): Response
    {
        $template = $this->requireTemplate($templateId);
        $docs = $this->renderDocuments($template, $columns, $metadata, $resourceType, $resourceUuid);

        $tmpFile = tempnam(sys_get_temp_dir(), 'guideline_zip_');
        if (false === $tmpFile) {
            throw new \RuntimeException('Unable to allocate a temporary file for the guideline export.');
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new \RuntimeException('Unable to create the guideline export archive.');
        }
        $zip->addFromString(\sprintf('%s_study_plan.md', $templateId), $docs['plan']);
        $zip->addFromString(\sprintf('%s_checklist.md', $templateId), $docs['checklist']);
        $zip->close();

        return new StreamedResponse(static function () use ($tmpFile): void {
            try {
                $in = fopen($tmpFile, 'r');
                $out = fopen('php://output', 'w');
                if (\is_resource($in) && \is_resource($out)) {
                    stream_copy_to_stream($in, $out);
                    fclose($in);
                    fclose($out);
                }
            } finally {
                @unlink($tmpFile);
            }
        }, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => \sprintf('attachment; filename="%s-report.zip"', $templateId),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @param list<\App\Guideline\CsvColumn> $columns
     * @param array<string, mixed>           $metadata
     *
     * @return array{plan: string, checklist: string}
     */
    private function renderDocuments(GuidelineTemplate $template, array $columns, array $metadata, string $resourceType, Uuid $resourceUuid): array
    {
        $entries = $this->engine->evaluate($template, $columns, $metadata);
        $report = new ConformanceReport($template, $entries);
        $account = $this->locator->currentUser()->getAccount();
        $reviewSummary = $this->reviewSummaryResolver->summarize($resourceType, $resourceUuid, $template->id, $account);

        return $this->renderer->render($template, $report, $metadata, $reviewSummary);
    }

    private function requireTemplate(string $templateId): GuidelineTemplate
    {
        if (!$this->loader->has($templateId)) {
            throw new NotFoundHttpException(\sprintf('Guideline template "%s" not found.', $templateId));
        }

        return $this->loader->load($templateId);
    }

    private function requireMetadataField(GuidelineTemplate $template, string $fieldId): RequiredMetadata
    {
        foreach ($template->requiredMetadata as $field) {
            if ($field->id === $fieldId) {
                return $field;
            }
        }

        throw new NotFoundHttpException(\sprintf('Field "%s" is not a metadata field of template "%s".', $fieldId, $template->id));
    }
}
