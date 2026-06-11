<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Experiment;
use App\Entity\Project;

/**
 * Evaluates all 15 FAIR sub-principles (F1–F4, A1–A2, I1–I3, R1–R1.3) against
 * measurable signals available in the domain entities.
 *
 * This service is used by:
 *  - FairReportPdfService  (PDF export)
 *  - FairAssessmentProvider (JSON/MCP endpoint)
 */
final class FairAssessmentService
{
    /**
     * Assess a Study (Experiment) against all 15 FAIR criteria.
     *
     * @return array{
     *   criteria: array<string, list<array{id: string, label: string, description: string, pass: bool, note: string}>>,
     *   score: array{total: int, findable: int, accessible: int, interoperable: int, reusable: int},
     * }
     */
    public function assessExperiment(Experiment $experiment): array
    {
        $criteria = $this->buildExperimentCriteria($experiment);
        $score = $this->computeScore($criteria);

        return ['criteria' => $criteria, 'score' => $score];
    }

    /**
     * Assess an Investigation (Project) against all 15 FAIR criteria.
     *
     * @return array{
     *   criteria: array<string, list<array{id: string, label: string, description: string, pass: bool, note: string}>>,
     *   score: array{total: int, findable: int, accessible: int, interoperable: int, reusable: int},
     * }
     */
    public function assessProject(Project $project): array
    {
        $criteria = $this->buildProjectCriteria($project);
        $score = $this->computeScore($criteria);

        return ['criteria' => $criteria, 'score' => $score];
    }

    // ------------------------------------------------------------------
    // Criteria builders
    // ------------------------------------------------------------------

    /**
     * @return array<string, list<array{id: string, label: string, description: string, pass: bool, note: string}>>
     */
    public function buildExperimentCriteria(Experiment $experiment): array
    {
        $hasId = null !== $experiment->getId();
        $hasName = '' !== trim($experiment->getName());
        $protocol = $experiment->getProtocol();
        $hasProtocol = null !== $protocol && '' !== trim($protocol);
        $hasProject = null !== $experiment->getProject();
        $hasProcedures = $experiment->getProcedures()->count() > 0;
        $hasRepositoryLink = null !== $experiment->getRepositoryLink();
        $isCompleted = null !== $experiment->getEndAt();

        return [
            'Findable' => [
                [
                    'id' => 'F1',
                    'label' => 'Globally unique and persistent identifier',
                    'description' => '(Meta)data are assigned a globally unique and persistent identifier.',
                    'pass' => $hasId,
                    'note' => $hasId ? 'UUID assigned.' : 'No identifier found.',
                ],
                [
                    'id' => 'F2',
                    'label' => 'Rich metadata',
                    'description' => 'Data are described with rich metadata (defined by R1 below).',
                    'pass' => $hasName && $hasProtocol,
                    'note' => ($hasName && $hasProtocol) ? 'Name and protocol present.' : 'Missing name or protocol.',
                ],
                [
                    'id' => 'F3',
                    'label' => 'Identifier referenced in metadata',
                    'description' => 'Metadata clearly and explicitly include the identifier of the data they describe.',
                    'pass' => $hasId && $hasProject,
                    'note' => ($hasId && $hasProject) ? 'Study ID linked to investigation.' : 'Study ID or investigation link missing.',
                ],
                [
                    'id' => 'F4',
                    'label' => 'Registered in a searchable resource',
                    'description' => '(Meta)data are registered or indexed in a searchable resource.',
                    'pass' => $hasProject,
                    'note' => $hasProject ? 'Registered under an investigation.' : 'Not associated with any investigation.',
                ],
            ],
            'Accessible' => [
                [
                    'id' => 'A1',
                    'label' => 'Data retrievable by identifier',
                    'description' => '(Meta)data are retrievable by their identifier using a standardised communications protocol.',
                    'pass' => $hasId && $hasRepositoryLink,
                    'note' => ($hasId && $hasRepositoryLink) ? 'Repository link present.' : 'No repository link available.',
                ],
                [
                    'id' => 'A1.1',
                    'label' => 'Open, free, and universally implementable protocol',
                    'description' => 'The protocol is open, free, and universally implementable.',
                    'pass' => true,
                    'note' => 'Data is served over HTTP, an open and royalty-free protocol.',
                ],
                [
                    'id' => 'A1.2',
                    'label' => 'Protocol supports authentication',
                    'description' => 'The protocol allows for an authentication and authorisation procedure, where necessary.',
                    'pass' => true,
                    'note' => 'API enforces Bearer-token authentication on every endpoint.',
                ],
                [
                    'id' => 'A2',
                    'label' => 'Metadata accessible even when data is unavailable',
                    'description' => 'Metadata are accessible, even when the data are no longer available.',
                    'pass' => $isCompleted,
                    'note' => $isCompleted ? 'Study is completed; metadata persists.' : 'Study still in progress.',
                ],
            ],
            'Interoperable' => [
                [
                    'id' => 'I1',
                    'label' => 'Formal knowledge representation language',
                    'description' => '(Meta)data use a formal, accessible, shared, and broadly applicable language for knowledge representation.',
                    'pass' => true,
                    'note' => 'API serves JSON-LD via API Platform — a W3C-standardised format.',
                ],
                [
                    'id' => 'I2',
                    'label' => 'FAIR-compliant vocabularies',
                    'description' => '(Meta)data use vocabularies that follow FAIR principles.',
                    'pass' => $hasProtocol,
                    'note' => $hasProtocol ? 'Protocol field references domain vocabulary.' : 'No protocol/vocabulary specified.',
                ],
                [
                    'id' => 'I3',
                    'label' => 'Qualified references to other metadata',
                    'description' => '(Meta)data include qualified references to other (meta)data.',
                    'pass' => $hasProject && $hasProcedures,
                    'note' => ($hasProject && $hasProcedures) ? 'Linked to investigation and procedures.' : 'Missing investigation or procedure links.',
                ],
            ],
            'Reusable' => [
                [
                    'id' => 'R1',
                    'label' => 'Richly described with accurate attributes',
                    'description' => '(Meta)data are richly described with a plurality of accurate and relevant attributes.',
                    'pass' => $hasName && $hasProtocol && $hasProcedures,
                    'note' => ($hasName && $hasProtocol && $hasProcedures) ? 'Name, protocol, and procedures present.' : 'Incomplete attributes.',
                ],
                [
                    'id' => 'R1.1',
                    'label' => 'Clear data usage license',
                    'description' => '(Meta)data are released with a clear and accessible data usage license.',
                    'pass' => $isCompleted,
                    'note' => $isCompleted ? 'Completed study carries CC BY 4.0 license.' : 'License not assigned until study is completed.',
                ],
                [
                    'id' => 'R1.2',
                    'label' => 'Detailed provenance',
                    'description' => '(Meta)data are associated with detailed provenance.',
                    'pass' => $hasProcedures,
                    'note' => $hasProcedures ? 'Procedures record provenance chain.' : 'No procedures found.',
                ],
                [
                    'id' => 'R1.3',
                    'label' => 'Domain-relevant community standards',
                    'description' => '(Meta)data meet domain-relevant community standards.',
                    'pass' => $hasProtocol,
                    'note' => $hasProtocol ? 'Protocol field specifies community standard.' : 'No protocol defined.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, list<array{id: string, label: string, description: string, pass: bool, note: string}>>
     */
    public function buildProjectCriteria(Project $project): array
    {
        $hasId = null !== $project->getId();
        $hasName = '' !== trim($project->getName());
        $goal = $project->getGoal();
        $hasGoal = null !== $goal && '' !== trim($goal);
        $hasStudies = $project->getExperiments()->count() > 0;
        $hasRepositoryLink = null !== $project->getRepositoryLink();
        $hasEndDate = null !== $project->getEndAt();

        return [
            'Findable' => [
                [
                    'id' => 'F1',
                    'label' => 'Globally unique and persistent identifier',
                    'description' => '(Meta)data are assigned a globally unique and persistent identifier.',
                    'pass' => $hasId,
                    'note' => $hasId ? 'UUID assigned.' : 'No identifier found.',
                ],
                [
                    'id' => 'F2',
                    'label' => 'Rich metadata',
                    'description' => 'Data are described with rich metadata (defined by R1 below).',
                    'pass' => $hasName && $hasGoal,
                    'note' => ($hasName && $hasGoal) ? 'Name and goal present.' : 'Missing name or goal description.',
                ],
                [
                    'id' => 'F3',
                    'label' => 'Identifier referenced in metadata',
                    'description' => 'Metadata clearly and explicitly include the identifier of the data they describe.',
                    'pass' => $hasId,
                    'note' => $hasId ? 'Investigation ID present in metadata.' : 'No identifier found.',
                ],
                [
                    'id' => 'F4',
                    'label' => 'Registered in a searchable resource',
                    'description' => '(Meta)data are registered or indexed in a searchable resource.',
                    'pass' => $hasStudies,
                    'note' => $hasStudies ? 'Investigation contains indexed studies.' : 'No studies registered yet.',
                ],
            ],
            'Accessible' => [
                [
                    'id' => 'A1',
                    'label' => 'Data retrievable by identifier',
                    'description' => '(Meta)data are retrievable by their identifier using a standardised communications protocol.',
                    'pass' => $hasId && $hasRepositoryLink,
                    'note' => ($hasId && $hasRepositoryLink) ? 'Repository link present.' : 'No repository link available.',
                ],
                [
                    'id' => 'A1.1',
                    'label' => 'Open, free, and universally implementable protocol',
                    'description' => 'The protocol is open, free, and universally implementable.',
                    'pass' => true,
                    'note' => 'Data is served over HTTP, an open and royalty-free protocol.',
                ],
                [
                    'id' => 'A1.2',
                    'label' => 'Protocol supports authentication',
                    'description' => 'The protocol allows for an authentication and authorisation procedure, where necessary.',
                    'pass' => true,
                    'note' => 'API enforces Bearer-token authentication on every endpoint.',
                ],
                [
                    'id' => 'A2',
                    'label' => 'Metadata accessible even when data is unavailable',
                    'description' => 'Metadata are accessible, even when the data are no longer available.',
                    'pass' => $hasEndDate,
                    'note' => $hasEndDate ? 'Investigation has completed; metadata persists.' : 'Investigation still active.',
                ],
            ],
            'Interoperable' => [
                [
                    'id' => 'I1',
                    'label' => 'Formal knowledge representation language',
                    'description' => '(Meta)data use a formal, accessible, shared, and broadly applicable language for knowledge representation.',
                    'pass' => true,
                    'note' => 'API serves JSON-LD via API Platform — a W3C-standardised format.',
                ],
                [
                    'id' => 'I2',
                    'label' => 'FAIR-compliant vocabularies',
                    'description' => '(Meta)data use vocabularies that follow FAIR principles.',
                    'pass' => $hasGoal,
                    'note' => $hasGoal ? 'Goal field provides domain vocabulary context.' : 'No vocabulary context specified.',
                ],
                [
                    'id' => 'I3',
                    'label' => 'Qualified references to other metadata',
                    'description' => '(Meta)data include qualified references to other (meta)data.',
                    'pass' => $hasStudies,
                    'note' => $hasStudies ? 'Investigation links to studies.' : 'No studies linked.',
                ],
            ],
            'Reusable' => [
                [
                    'id' => 'R1',
                    'label' => 'Richly described with accurate attributes',
                    'description' => '(Meta)data are richly described with a plurality of accurate and relevant attributes.',
                    'pass' => $hasName && $hasGoal && $hasStudies,
                    'note' => ($hasName && $hasGoal && $hasStudies) ? 'Name, goal, and studies present.' : 'Incomplete attributes.',
                ],
                [
                    'id' => 'R1.1',
                    'label' => 'Clear data usage license',
                    'description' => '(Meta)data are released with a clear and accessible data usage license.',
                    'pass' => $hasEndDate,
                    'note' => $hasEndDate ? 'Completed investigation carries CC BY 4.0 license.' : 'License not assigned until investigation is completed.',
                ],
                [
                    'id' => 'R1.2',
                    'label' => 'Detailed provenance',
                    'description' => '(Meta)data are associated with detailed provenance.',
                    'pass' => $hasStudies,
                    'note' => $hasStudies ? 'Studies provide provenance chain.' : 'No studies to provide provenance.',
                ],
                [
                    'id' => 'R1.3',
                    'label' => 'Domain-relevant community standards',
                    'description' => '(Meta)data meet domain-relevant community standards.',
                    'pass' => $hasGoal,
                    'note' => $hasGoal ? 'Goal describes domain-relevant context.' : 'No domain context specified.',
                ],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Score computation
    // ------------------------------------------------------------------

    /**
     * @param array<string, list<array{id: string, label: string, description: string, pass: bool, note: string}>> $criteria
     *
     * @return array{total: int, findable: int, accessible: int, interoperable: int, reusable: int}
     */
    public function computeScore(array $criteria): array
    {
        $scores = [];
        $pillarCount = \count($criteria);

        foreach ($criteria as $pillar => $items) {
            $passCount = \count(array_filter($items, static fn (array $c) => $c['pass']));
            $total = \count($items);
            $scores[strtolower($pillar)] = $total > 0 ? (int) round(($passCount / $total) * 100) : 0;
        }

        $overall = $pillarCount > 0
            ? (int) round(array_sum($scores) / $pillarCount)
            : 0;

        return [
            'total' => $overall,
            'findable' => $scores['findable'] ?? 0,
            'accessible' => $scores['accessible'] ?? 0,
            'interoperable' => $scores['interoperable'] ?? 0,
            'reusable' => $scores['reusable'] ?? 0,
        ];
    }
}
