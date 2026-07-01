<?php

declare(strict_types=1);

namespace App\Curation;

use App\Entity\CurationSuggestion;
use App\Entity\Strain;
use App\Entity\User;
use App\Enum\CurationSuggestionStatus;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Enum\Species;
use App\Repository\StrainRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CurationReviewService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StrainRepository $strainRepository,
    ) {
    }

    public function accept(CurationSuggestion $suggestion, User $reviewer): CurationSuggestion
    {
        if (CurationSuggestionStatus::PendingReview !== $suggestion->getStatus()) {
            return $suggestion;
        }

        $reviewer = $this->resolveManagedReviewer($reviewer);
        $this->applyToCanonicalRecord($suggestion);
        $suggestion
            ->setStatus(CurationSuggestionStatus::Accepted)
            ->setReviewedAt(new \DateTimeImmutable())
            ->setReviewedBy($reviewer)
        ;

        $this->entityManager->flush();

        return $suggestion;
    }

    public function reject(CurationSuggestion $suggestion, User $reviewer): CurationSuggestion
    {
        if (CurationSuggestionStatus::PendingReview !== $suggestion->getStatus()) {
            return $suggestion;
        }

        $reviewer = $this->resolveManagedReviewer($reviewer);
        $suggestion
            ->setStatus(CurationSuggestionStatus::Rejected)
            ->setReviewedAt(new \DateTimeImmutable())
            ->setReviewedBy($reviewer)
        ;

        $this->entityManager->flush();

        return $suggestion;
    }

    private function applyToCanonicalRecord(CurationSuggestion $suggestion): void
    {
        $this->applyFieldToSubject($suggestion->getSubject(), $suggestion->getFieldName(), $suggestion->getProposedValue());
    }

    public function applyToCanonicalRecordFromPatch(\App\Entity\PatchProposal $patch): void
    {
        $subject = $this->entityManager->getRepository(\App\Entity\Subject::class)->find($patch->getSubjectId());
        if ($subject) {
            $this->applyFieldToSubject($subject, $patch->getTargetField(), $patch->getProposedValue());
        }
    }

    private function applyFieldToSubject(\App\Entity\Subject $subject, string $fieldName, mixed $value): void
    {
        match ($fieldName) {
            'name', 'label' => \is_string($value) ? $subject->setName($value) : null,
            'externalId' => \is_string($value) ? $subject->setExternalId($value) : null,
            'species' => \is_string($value) ? $subject->setSpecies(Species::from(strtolower($value))) : null,
            'sex' => \is_string($value) ? $subject->setSex(Sex::from(strtolower($value))) : null,
            'birthAt' => \is_string($value) ? $subject->setBirthAt(new \DateTime($value)) : null,
            'strain' => \is_string($value) && '' !== trim($value) ? $subject->setStrain($this->resolveStrain($value)) : null,
            'genotype' => null === $value || \is_string($value) ? $subject->setGenotype($value) : null,
            'weight' => null === $value || is_numeric($value) ? $subject->setWeight(null === $value ? null : (float) $value) : null,
            'healthStatus' => \is_string($value) ? $subject->setHealthStatus($this->resolveHealthStatus($value)) : null,
            'isPregnant' => \is_bool($value) ? $subject->setIsPregnant($value) : null,
            default => null,
        };
    }

    public function resolveStrain(string $value): Strain
    {
        $name = trim($value);
        $strain = $this->strainRepository->findOneByNormalizedName($name);
        if ($strain instanceof Strain) {
            return $strain;
        }

        $strain = (new Strain())->setName($name);
        $this->entityManager->persist($strain);

        return $strain;
    }

    private function resolveHealthStatus(string $value): HealthStatus
    {
        $normalized = $this->normalizeTextValue($value);

        foreach (HealthStatus::cases() as $status) {
            $candidate = $this->normalizeTextValue($status->value);
            if ($candidate === $normalized) {
                return $status;
            }
        }

        return HealthStatus::from($value);
    }

    private function normalizeTextValue(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }

    private function resolveManagedReviewer(User $reviewer): User
    {
        if ($this->entityManager->contains($reviewer)) {
            return $reviewer;
        }

        $reviewerId = $reviewer->getId();
        if (null === $reviewerId) {
            return $reviewer;
        }

        $managedReviewer = $this->entityManager->getReference(User::class, $reviewerId);
        if (!$managedReviewer instanceof User) {
            return $reviewer;
        }

        return $managedReviewer;
    }
}
