<?php

declare(strict_types=1);

namespace App\Command;

use App\Curation\Application\ApplyApprovedPatches;
use App\Curation\Application\BuildPatchProposals;
use App\Curation\Application\CreateImportSession;
use App\Curation\Application\GenerateNormalizationProposals;
use App\Curation\Application\GenerateSchemaMappingProposals;
use App\Curation\Application\GenerateVocabularyProposals;
use App\Curation\Application\GetPatchBuildSummary;
use App\Curation\Application\IdentifyResolutionConflicts;
use App\Curation\Application\ProfileImportSession;
use App\Entity\PatchProposal;
use App\Entity\SchemaFieldMappingProposal;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verify-milestone-b',
    description: 'Verifies the full Milestone B curation pipeline.',
)]
class VerifyMilestoneBCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CreateImportSession $createSession,
        private ProfileImportSession $profileSession,
        private GenerateSchemaMappingProposals $generateSchema,
        private GenerateNormalizationProposals $generateNormalizations,
        private GenerateVocabularyProposals $generateVocabulary,
        private IdentifyResolutionConflicts $identifyConflicts,
        private BuildPatchProposals $buildPatches,
        private ApplyApprovedPatches $applyPatches,
        private GetPatchBuildSummary $patchSummary,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Verifying Milestone B Pipeline');

        $user = $this->entityManager->getRepository(User::class)->findOneBy([]);
        $account = $user?->getAccount();

        if (!$account) {
            $io->error('No account found.');

            return Command::FAILURE;
        }

        // 1. Create
        $session = $this->createSession->execute('milestone_b_fpr_' . time(), $account, $user);
        $this->entityManager->flush();
        $io->success('1. Session created: ' . $session->getId());

        // 2. Profile
        $columns = [
            ['header' => 'Subject ID'],
            ['header' => 'Species'],
        ];
        $rows = [
            ['Subject ID' => 'S1', 'Species' => 'Mouse'],
            ['Subject ID' => 'S1', 'Species' => 'Mouse'], // Duplicate to test resolution consistency
        ];
        $this->profileSession->execute($session, $columns, $rows);
        $this->entityManager->flush();
        $io->success('2. Profiling complete.');

        // 3. Schema Mapping
        $this->generateSchema->execute($session);
        $mappings = $this->entityManager->getRepository(SchemaFieldMappingProposal::class)->findBy(['session' => $session]);
        foreach ($mappings as $m) {
            $m->setDecisionStatus('approved');
        }
        $this->entityManager->flush();
        $io->success('3. Schema mapped & approved.');

        // 4. Normalization & Vocabulary
        $this->generateNormalizations->execute($session);
        $this->generateVocabulary->execute($session);
        $io->success('4. Normalization/Vocabulary generated.');

        // 5. Identify Resolution Conflicts
        $this->identifyConflicts->execute($session);
        $io->success('5. Resolution conflicts identified.');

        // 6. Build Patches
        $this->buildPatches->execute($session);
        $summary = $this->patchSummary->execute($session);
        $io->success('6. Patches built. Sub-delta count: ' . \count($summary->subject_deltas));

        // Approval: Auto-approve patches
        $patches = $this->entityManager->getRepository(PatchProposal::class)->findBy(['session' => $session]);
        foreach ($patches as $p) {
            $p->setDecisionStatus('approved');
        }
        $this->entityManager->flush();

        // 7. Apply Patches
        $this->applyPatches->execute($session);
        $io->success('7. Patches applied to Knowledge Graph.');

        $io->section('Final Results');
        $io->info('Session Status: ' . $session->getStatus()->value);

        return Command::SUCCESS;
    }
}
