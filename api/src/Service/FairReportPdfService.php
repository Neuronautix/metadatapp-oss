<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Cage;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\Subject;
use App\Service\Pdf\TextPdfRenderer;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Generates FAIR assessment PDF reports for Study and Investigation resources.
 *
 * For experiments: renders a structured template (experiment-report.txt.twig) that
 * includes study overview, animal population, housing conditions, procedures/provenance,
 * FAIR score, and completeness warnings.
 *
 * For projects (investigations): renders a legacy flat-text FAIR assessment report.
 */
final class FairReportPdfService
{
    private const string DEFAULT_LICENSE = 'https://creativecommons.org/licenses/by/4.0/';

    public function __construct(
        private readonly FairAssessmentService $assessmentService,
        private readonly TextPdfRenderer $pdfRenderer,
        private readonly Environment $twig,
    ) {
    }

    public function generateForExperiment(Experiment $experiment): Response
    {
        $assessment = $this->assessmentService->assessExperiment($experiment);
        $subjects = $this->collectSubjects($experiment);
        $cages = $this->collectCages($experiment);

        $report = [
            'study' => [
                'id' => $experiment->getId()?->toRfc4122() ?? 'n/a',
                'name' => $experiment->getName(),
                'investigationName' => $experiment->getProject()?->getName() ?? 'n/a',
                'investigationId' => $experiment->getProject()?->getId()?->toRfc4122() ?? 'n/a',
                'protocol' => $experiment->getProtocol() ?? 'n/a',
                'startAt' => $experiment->getStartAt()->format('Y-m-d'),
                'endAt' => $experiment->getEndAt()?->format('Y-m-d') ?? 'In progress',
                'goal' => $experiment->getGoal() ?? 'n/a',
                'repositoryLink' => $experiment->getRepositoryLink() ?? 'n/a',
                'externalId' => $experiment->getExternalId(),
                'elabftwId' => $experiment->getElaftwExternalId(),
                'fair3rId' => $experiment->getFair3rExternalId(),
                'license' => self::DEFAULT_LICENSE,
            ],
            'subjects' => $subjects,
            'cages' => $cages,
            'procedures' => $this->collectProcedures($experiment),
            'fairScore' => $assessment['score'],
            'fairCriteria' => $assessment['criteria'],
            'fairWarnings' => $this->buildFairWarnings($assessment['criteria']),
        ];

        $title = \sprintf('Experiment Report - Study: %s', $experiment->getName());
        $filename = \sprintf('experiment-report-study-%s.pdf', $experiment->getId()?->toRfc4122() ?? 'unknown');

        $rendered = $this->twig->render('reports/experiment-report.txt.twig', [
            'report' => $report,
            'generatedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim($rendered));

        return new Response(
            $this->pdfRenderer->render(
                title: $title,
                lines: false === $lines ? [] : $lines,
                producer: 'Metadatapp Experiment Reporter',
            ),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => \sprintf('attachment; filename="%s"', $filename),
                'Cache-Control' => 'no-store, private',
            ],
        );
    }

    public function generateForProject(Project $project): Response
    {
        $assessment = $this->assessmentService->assessProject($project);

        $title = \sprintf('FAIR Assessment Report - Investigation: %s', $project->getName());
        $meta = [
            ['Investigation ID', $project->getId()?->toRfc4122() ?? 'n/a'],
            ['Investigation name', $project->getName()],
            ['Goal', $project->getGoal() ?? 'n/a'],
            ['Start date', $project->getStartAt()?->format('Y-m-d') ?? 'n/a'],
            ['License', self::DEFAULT_LICENSE],
        ];

        return $this->buildLegacyPdfResponse(
            \sprintf('fair-report-investigation-%s.pdf', $project->getId()?->toRfc4122() ?? 'unknown'),
            $title,
            $meta,
            $assessment['criteria'],
            $assessment['score'],
        );
    }

    /**
     * @param list<array{0: string, 1: string}>                                                                    $meta
     * @param array<string, list<array{id: string, label: string, description: string, pass: bool, note: string}>> $criteria
     * @param array{total: int, findable: int, accessible: int, interoperable: int, reusable: int}                 $score
     */
    private function buildLegacyPdfResponse(
        string $filename,
        string $title,
        array $meta,
        array $criteria,
        array $score,
    ): Response {
        $lines = $this->buildReportLines($title, $meta, $criteria, $score);

        return new Response(
            $this->pdfRenderer->render($title, $lines, 'Metadatapp FAIR Reporter'),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => \sprintf('attachment; filename="%s"', $filename),
                'Cache-Control' => 'no-store, private',
            ],
        );
    }

    /**
     * @param list<array{0: string, 1: string}>                                                                    $meta
     * @param array<string, list<array{id: string, label: string, description: string, pass: bool, note: string}>> $criteria
     * @param array{total: int, findable: int, accessible: int, interoperable: int, reusable: int}                 $score
     *
     * @return list<string>
     */
    private function buildReportLines(string $title, array $meta, array $criteria, array $score): array
    {
        $lines = [];

        $lines[] = '=== FAIR Assessment Report ===';
        $lines[] = $title;
        $lines[] = 'Generated: ' . (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s') . ' UTC';
        $lines[] = '';

        $lines[] = '--- Metadata ---';
        foreach ($meta as [$key, $value]) {
            $lines[] = \sprintf('%s: %s', $key, $value);
        }
        $lines[] = '';

        $lines[] = '--- FAIR Score Summary ---';
        $lines[] = \sprintf('Overall score : %d / 100', $score['total']);
        $lines[] = \sprintf('  Findable       : %d / 100', $score['findable']);
        $lines[] = \sprintf('  Accessible     : %d / 100', $score['accessible']);
        $lines[] = \sprintf('  Interoperable  : %d / 100', $score['interoperable']);
        $lines[] = \sprintf('  Reusable       : %d / 100', $score['reusable']);
        $lines[] = '';

        $lines[] = '--- Detailed Criteria ---';
        foreach ($criteria as $pillar => $items) {
            $lines[] = '';
            $lines[] = '[ ' . strtoupper($pillar) . ' ]';
            foreach ($items as $criterion) {
                $status = $criterion['pass'] ? 'PASS' : 'FAIL';
                $lines[] = \sprintf('  [%s] %s - %s', $status, $criterion['id'], $criterion['label']);
                $lines[] = '       ' . $criterion['description'];
                $lines[] = '       Note: ' . $criterion['note'];
            }
        }

        $lines[] = '';
        $lines[] = '--- End of Report ---';

        return $lines;
    }

    /**
     * Collect unique subjects for the experiment across all its procedures.
     *
     * @return list<array{name: string, species: string, strain: string, sex: string, genotype: string, cage: string, weight: float|null}>
     */
    private function collectSubjects(Experiment $experiment): array
    {
        /** @var array<string, Subject> $seen */
        $seen = [];
        foreach ($experiment->getProcedures() as $procedure) {
            foreach ($procedure->getSubjects() as $subject) {
                $key = $subject->getId()?->toRfc4122() ?? spl_object_id($subject);
                $seen[(string) $key] = $subject;
            }
        }

        $result = [];
        foreach ($seen as $subject) {
            $result[] = [
                'name' => $subject->getName(),
                'species' => $subject->getSpecies()->value,
                'strain' => $subject->getStrain()->getName(),
                'sex' => $subject->getSex()->value,
                'genotype' => $subject->getGenotype() ?? '',
                'cage' => $subject->getCage()?->getName() ?? '',
                'weight' => $subject->getWeight(),
            ];
        }

        return $result;
    }

    /**
     * Collect unique cages for the experiment (via subjects linked through procedures).
     *
     * @return list<array{name: string, type: string, format: string, bedding: string, monitoring: string, facility: string}>
     */
    private function collectCages(Experiment $experiment): array
    {
        /** @var array<string, Cage> $seen */
        $seen = [];
        foreach ($experiment->getProcedures() as $procedure) {
            foreach ($procedure->getSubjects() as $subject) {
                $cage = $subject->getCage();
                if (null === $cage) {
                    continue;
                }
                $key = $cage->getId()?->toRfc4122() ?? spl_object_id($cage);
                $seen[(string) $key] = $cage;
            }
        }

        $result = [];
        foreach ($seen as $cage) {
            $result[] = [
                'name' => $cage->getName(),
                'type' => $cage->getType()->value,
                'format' => $cage->getFormat()->value,
                'bedding' => $cage->getBeddingType()->value,
                'monitoring' => $cage->getHomeCageMonitoring()?->getName() ?? '',
                'facility' => $cage->getAnimalFacility()?->getName() ?? '',
            ];
        }

        return $result;
    }

    /**
     * Collect procedures as simple scalar arrays for the report.
     *
     * @return list<array{name: string, startAt: string, endAt: string, state: string, subjectCount: int}>
     */
    private function collectProcedures(Experiment $experiment): array
    {
        $result = [];
        foreach ($experiment->getProcedures() as $procedure) {
            $result[] = [
                'name' => $procedure->getName(),
                'startAt' => $procedure->getStartAt()?->format('Y-m-d') ?? 'n/a',
                'endAt' => $procedure->getEndAt()?->format('Y-m-d') ?? 'n/a',
                'state' => $procedure->getState(),
                'subjectCount' => $procedure->getSubjects()->count(),
            ];
        }

        return $result;
    }

    /**
     * Extract FAIL criteria as human-readable warning strings.
     *
     * @param array<string, list<array{id: string, label: string, description: string, pass: bool, note: string}>> $criteria
     *
     * @return list<string>
     */
    private function buildFairWarnings(array $criteria): array
    {
        $warnings = [];
        foreach ($criteria as $pillar => $items) {
            foreach ($items as $item) {
                if (!$item['pass']) {
                    $warnings[] = \sprintf('[%s] %s (%s): %s', $pillar, $item['id'], $item['label'], $item['note']);
                }
            }
        }

        return $warnings;
    }
}
