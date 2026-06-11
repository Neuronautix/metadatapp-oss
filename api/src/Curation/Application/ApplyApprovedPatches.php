<?php

declare(strict_types=1);

namespace App\Curation\Application;

use App\Curation\CurationReviewService;
use App\Entity\ImportSession;
use App\Entity\PatchProposal;
use App\Entity\Subject;
use App\Enum\ImportSessionStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ApplyApprovedPatches
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CurationReviewService $curationReviewService,
    ) {
    }

    public function execute(ImportSession $session): void
    {
        /** @var \Doctrine\ORM\EntityRepository<PatchProposal> $patchRepository */
        $patchRepository = $this->entityManager->getRepository(PatchProposal::class);
        $patches = $patchRepository->findBy(['session' => $session]);

        /** @var array<string, array<PatchProposal>> $patchesBySubject */
        $patchesBySubject = [];
        foreach ($patches as $patch) {
            if ('rejected' === $patch->getDecisionStatus()) {
                continue;
            }
            $patchesBySubject[(string) $patch->getSubjectId()][] = $patch;
        }

        foreach ($patchesBySubject as $subjectId => $subjectPatches) {
            $this->applySubjectPatches($session, (string) $subjectId, $subjectPatches);
        }

        $session->setStatus(ImportSessionStatus::Applied);
        $session->setAppliedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * @param array<PatchProposal> $patches
     */
    private function applySubjectPatches(ImportSession $session, string $subjectId, array $patches): void
    {
        /** @var \Doctrine\ORM\EntityRepository<Subject> $subjectRepo */
        $subjectRepo = $this->entityManager->getRepository(Subject::class);
        $subject = $subjectRepo->find(\Symfony\Component\Uid\Uuid::fromString($subjectId));

        $isNew = false;
        if (!$subject) {
            $isNew = true;
            $subject = new Subject();
            $subject->setId(\Symfony\Component\Uid\Uuid::fromString($subjectId));
            $subject->setAccount($session->getAccount());

            // Temporary defaults to satisfy NOT NULL constraints before patches are applied
            $subject->setName('temp-' . substr($subjectId, 0, 8));
            $subject->setSpecies(\App\Enum\Species::Mouse);
            $subject->setSex(\App\Enum\Sex::Female);
            $subject->setBirthAt(new \DateTime());
            $subject->setHealthStatus(\App\Enum\HealthStatus::NORMAL);
            $subject->setStrain($this->curationReviewService->resolveStrain('Unknown'));

            $this->entityManager->persist($subject);
        }

        // Apply patches (this will override the defaults above)
        foreach ($patches as $patch) {
            if ('approved' === $patch->getDecisionStatus() || 'undecided' === $patch->getDecisionStatus()) {
                $this->curationReviewService->applyToCanonicalRecordFromPatch($patch);
                $patch->setDecisionStatus('applied');
            }
        }
    }
}
