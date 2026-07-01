<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Curation\Application\ApplyApprovedPatches;
use App\Curation\Application\BuildPatchProposals;
use App\Curation\Application\GenerateNormalizationProposals;
use App\Curation\Application\GenerateSchemaMappingProposals;
use App\Curation\Application\GenerateVocabularyProposals;
use App\Entity\ImportSession;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class SessionWorkflowProcessor implements ProcessorInterface
{
    public function __construct(
        private GenerateSchemaMappingProposals $genSchema,
        private GenerateNormalizationProposals $genNorm,
        private GenerateVocabularyProposals $genVocab,
        private \App\Curation\Application\IdentifyResolutionConflicts $identifyConflicts,
        private BuildPatchProposals $buildPatches,
        private ApplyApprovedPatches $applyPatches,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ImportSession) {
            return $data; // default behavior for unsupported entities
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return $data;
        }
        $path = $request->getPathInfo();

        if (str_ends_with($path, '/generate')) {
            $this->genSchema->execute($data);
            $this->genNorm->execute($data);
            $this->genVocab->execute($data);
            $this->identifyConflicts->execute($data);
        } elseif (str_ends_with($path, '/patch-build')) {
            $this->buildPatches->execute($data);
        } elseif (str_ends_with($path, '/apply')) {
            $this->applyPatches->execute($data);
        }

        return $data;
    }
}
