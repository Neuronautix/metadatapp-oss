<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Curation\CurationReviewService;
use App\Curation\LLMCurationService;
use App\Entity\CurationSuggestion;
use App\Entity\Subject;
use App\Entity\User;
use App\Repository\CurationSuggestionRepository;
use App\Repository\SubjectRepository;
use App\Security\Roles;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/curation', name: 'api_curation_')]
#[IsGranted(Roles::ROLE_USER)]
final class CurationController extends AbstractController
{
    #[Route('/suggest/{recordId}', name: 'suggest_subject', methods: ['POST'])]
    public function suggest(
        string $recordId,
        SubjectRepository $subjectRepository,
        LLMCurationService $llmCurationService,
    ): JsonResponse {
        $subject = $this->findSubject($recordId, $subjectRepository);
        $suggestions = $llmCurationService->requestSubjectSuggestions($subject);

        return $this->json([
            'recordId' => (string) $subject->getId(),
            'suggestions' => array_map($this->serializeSuggestion(...), $suggestions),
        ]);
    }

    #[Route('/suggestions/{recordId}', name: 'list_subject_suggestions', methods: ['GET'])]
    public function list(
        string $recordId,
        SubjectRepository $subjectRepository,
        CurationSuggestionRepository $curationSuggestionRepository,
    ): JsonResponse {
        $subject = $this->findSubject($recordId, $subjectRepository);
        $suggestions = $curationSuggestionRepository->findForSubject($subject);

        return $this->json([
            'recordId' => (string) $subject->getId(),
            'suggestions' => array_map($this->serializeSuggestion(...), $suggestions),
        ]);
    }

    #[Route('/suggestions/{suggestionId}/accept', name: 'accept_suggestion', methods: ['POST'])]
    public function accept(
        string $suggestionId,
        CurationSuggestionRepository $curationSuggestionRepository,
        CurationReviewService $curationReviewService,
    ): JsonResponse {
        $suggestion = $this->findSuggestion($suggestionId, $curationSuggestionRepository);
        $suggestion = $curationReviewService->accept($suggestion, $this->getCurrentUser());

        return $this->json([
            'suggestion' => $this->serializeSuggestion($suggestion),
        ]);
    }

    #[Route('/suggestions/{suggestionId}/reject', name: 'reject_suggestion', methods: ['POST'])]
    public function reject(
        string $suggestionId,
        CurationSuggestionRepository $curationSuggestionRepository,
        CurationReviewService $curationReviewService,
    ): JsonResponse {
        $suggestion = $this->findSuggestion($suggestionId, $curationSuggestionRepository);
        $suggestion = $curationReviewService->reject($suggestion, $this->getCurrentUser());

        return $this->json([
            'suggestion' => $this->serializeSuggestion($suggestion),
        ]);
    }

    private function findSubject(string $recordId, SubjectRepository $subjectRepository): Subject
    {
        if (!Uuid::isValid($recordId)) {
            throw $this->createNotFoundException('Subject not found.');
        }

        $subject = $subjectRepository->find($recordId);
        if (!$subject instanceof Subject || !$this->belongsToCurrentAccount($subject->getAccount()->getId())) {
            throw $this->createNotFoundException('Subject not found.');
        }

        return $subject;
    }

    private function findSuggestion(string $suggestionId, CurationSuggestionRepository $curationSuggestionRepository): CurationSuggestion
    {
        if (!Uuid::isValid($suggestionId)) {
            throw $this->createNotFoundException('Curation suggestion not found.');
        }

        $suggestion = $curationSuggestionRepository->find($suggestionId);
        if (
            !$suggestion instanceof CurationSuggestion
            || !$this->belongsToCurrentAccount($suggestion->getSubject()->getAccount()->getId())
        ) {
            throw $this->createNotFoundException('Curation suggestion not found.');
        }

        return $suggestion;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSuggestion(CurationSuggestion $suggestion): array
    {
        return [
            'id' => (string) $suggestion->getId(),
            'recordId' => $suggestion->getResourceId(),
            'resourceType' => $suggestion->getResourceType(),
            'resourceId' => $suggestion->getResourceId(),
            'taskType' => $suggestion->getTaskType()->value,
            'status' => $suggestion->getStatus()->value,
            'fieldName' => $suggestion->getFieldName(),
            'currentValue' => $suggestion->getCurrentValue(),
            'proposedValue' => $suggestion->getProposedValue(),
            'ontologyId' => $suggestion->getOntologyId(),
            'confidence' => $suggestion->getConfidence(),
            'reason' => $suggestion->getReason(),
            'warningCode' => $suggestion->getWarningCode(),
            'providerName' => $suggestion->getProviderName(),
            'providerMetadata' => $suggestion->getProviderMetadata(),
            'failureDetails' => $suggestion->getFailureDetails(),
            'rawLlmResponse' => $suggestion->getRawLlmResponse(),
            'createdAt' => $suggestion->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $suggestion->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'reviewedAt' => $suggestion->getReviewedAt()?->format(\DateTimeInterface::ATOM),
            'reviewedBy' => $suggestion->getReviewedBy()?->getName() ?? $suggestion->getReviewedBy()?->getEmail(),
        ];
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function belongsToCurrentAccount(?Uuid $accountId): bool
    {
        $currentAccountId = $this->getCurrentUser()->getAccount()->getId();

        return null !== $accountId && null !== $currentAccountId && $accountId->equals($currentAccountId);
    }
}
