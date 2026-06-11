<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\Subject;
use App\Service\Arrive\ArriveChecklistItem;
use App\Service\Arrive\ArriveReportMetadata;
use App\Service\Pdf\TextPdfRenderer;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Generates ARRIVE 2.0 helper PDF reports based on the Essential 10 and Recommended Set checklists.
 */
final class ArriveReportPdfService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TextPdfRenderer $pdfRenderer,
    ) {
    }

    public function generateForExperiment(Experiment $experiment): Response
    {
        $metadata = $this->buildReportMetadata([$experiment]);

        return $this->buildPdfResponse(
            \sprintf('arrive-report-study-%s.pdf', $experiment->getId()?->toRfc4122() ?? 'unknown'),
            $metadata,
        );
    }

    public function generateForProject(Project $project): Response
    {
        /** @var list<Experiment> $experiments */
        $experiments = array_values($project->getExperiments()->toArray());
        $metadata = $this->buildReportMetadata($experiments, $project);

        return $this->buildPdfResponse(
            \sprintf('arrive-report-investigation-%s.pdf', $project->getId()?->toRfc4122() ?? 'unknown'),
            $metadata,
        );
    }

    /**
     * @param list<Experiment> $experiments
     */
    private function buildReportMetadata(array $experiments, ?Project $project = null): ArriveReportMetadata
    {
        $firstExperiment = $experiments[0] ?? null;
        $metadata = $firstExperiment?->getElabftwMetadata() ?? [];
        $subjects = $this->subjectsForExperiments($experiments);
        $investigationName = $project?->getName() ?? $firstExperiment?->getProject()?->getName() ?? 'n/a';

        $title = \sprintf(
            'ARRIVE 2.0 Checklist Helper Report - %s: %s',
            null === $project ? 'Study' : 'Investigation',
            null === $project ? ($firstExperiment?->getName() ?? 'n/a') : $project->getName(),
        );

        $essentialItems = [
            new ArriveChecklistItem(1, 'Study design', 'Groups, controls, and experimental unit.', $this->studyDesignValue($experiments, $subjects, $metadata), true),
            new ArriveChecklistItem(2, 'Sample size', 'Exact n, total animals, and rationale.', $this->sampleSizeValue($subjects, $metadata), true),
            new ArriveChecklistItem(3, 'Inclusion and exclusion criteria', 'Criteria and exclusions per group.', $this->inclusionExclusionValue($metadata), true),
            new ArriveChecklistItem(4, 'Randomisation', 'Allocation method and confounder control strategy.', $this->randomisationValue($metadata), true),
            new ArriveChecklistItem(5, 'Blinding', 'Who was blinded at each study stage.', $this->metadataValue($metadata, 'blinding'), true),
            new ArriveChecklistItem(6, 'Outcome measures', 'Defined outcomes and primary outcome.', $this->outcomeMeasuresValue($metadata), true),
            new ArriveChecklistItem(7, 'Statistical methods', 'Methods, software, assumptions checks.', $this->statisticalMethodsValue($metadata), true),
            new ArriveChecklistItem(8, 'Experimental animals', 'Species, strain, sex, age/weight, provenance.', $this->experimentalAnimalsValue($subjects), true),
            new ArriveChecklistItem(9, 'Experimental procedures', 'What/when/where/why for each group.', $this->experimentalProceduresValue($experiments), true),
            new ArriveChecklistItem(10, 'Results', 'Descriptive stats, variability, effect size/CI.', $this->resultsValue($metadata), true),
        ];

        $recommendedItems = [
            new ArriveChecklistItem(11, 'Abstract', 'Objectives, species/strain/sex, methods, findings.', $this->abstractValue($project, $firstExperiment, $metadata), false),
            new ArriveChecklistItem(12, 'Background', 'Scientific rationale and context.', $this->backgroundValue($project, $firstExperiment, $metadata), false),
            new ArriveChecklistItem(13, 'Objectives', 'Research questions and hypotheses.', $this->objectivesValue($project, $firstExperiment, $metadata), false),
            new ArriveChecklistItem(14, 'Ethical statement', 'Ethics committee and protocol/licence details.', $this->metadataValue($metadata, 'ethical_statement'), false),
            new ArriveChecklistItem(15, 'Housing and husbandry', 'Housing conditions and enrichment.', $this->housingAndHusbandryValue($subjects), false),
            new ArriveChecklistItem(16, 'Animal care and monitoring', 'Pain/distress mitigation, adverse events, humane endpoints.', $this->animalCareMonitoringValue($metadata), false),
            new ArriveChecklistItem(17, 'Interpretation / scientific implications', 'Interpretation with limitations and bias discussion.', $this->metadataValue($metadata, 'interpretation'), false),
            new ArriveChecklistItem(18, 'Generalisability / translation', 'Relevance beyond the model and to human biology.', $this->metadataValue($metadata, 'generalisability'), false),
            new ArriveChecklistItem(19, 'Protocol registration', 'Whether protocol was preregistered and where.', $this->protocolRegistrationValue($firstExperiment, $metadata), false),
            new ArriveChecklistItem(20, 'Data access', 'Where study data are available.', $this->dataAccessValue($project, $firstExperiment, $metadata), false),
            new ArriveChecklistItem(21, 'Declaration of interests', 'Conflicts of interest and funder role.', $this->declarationOfInterestsValue($metadata), false),
        ];

        $warnings = [];
        foreach ([...$essentialItems, ...$recommendedItems] as $item) {
            if ($item->isMissing()) {
                $warnings[] = \sprintf('Item %d (%s) is missing.', $item->id, $item->title);
            }
        }

        return new ArriveReportMetadata(
            title: $title,
            investigationName: $investigationName,
            studyCount: \count($experiments),
            animalCount: \count($subjects),
            essentialItems: $essentialItems,
            recommendedItems: $recommendedItems,
            warnings: array_values(array_unique($warnings)),
        );
    }

    private function buildPdfResponse(string $filename, ArriveReportMetadata $metadata): Response
    {
        $rendered = $this->twig->render('reports/arrive-report.txt.twig', [
            'report' => $metadata,
            'generatedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim($rendered));

        return new Response(
            $this->pdfRenderer->render(
                title: $metadata->title,
                lines: false === $lines ? [] : $lines,
                producer: 'Metadatapp ARRIVE Reporter',
            ),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => \sprintf('attachment; filename="%s"', $filename),
                'Cache-Control' => 'no-store, private',
            ],
        );
    }

    /**
     * @param list<Experiment>     $experiments
     * @param list<Subject>        $subjects
     * @param array<string, mixed> $metadata
     */
    private function studyDesignValue(array $experiments, array $subjects, array $metadata): string
    {
        if ([] === $experiments) {
            return 'Missing';
        }

        $groups = [];
        foreach ($subjects as $subject) {
            $groups[$subject->getGenotype() ?? 'unspecified genotype'] = true;
        }

        $groupList = [] !== $groups ? implode(', ', array_keys($groups)) : 'Missing';
        $control = $this->metadataValue($metadata, 'control_group');

        return \sprintf('Groups=%s | Control=%s | Experimental unit=%s', $groupList, $control, $this->metadataValue($metadata, 'experimental_unit', 'animal'));
    }

    /**
     * @param list<Subject>        $subjects
     * @param array<string, mixed> $metadata
     */
    private function sampleSizeValue(array $subjects, array $metadata): string
    {
        $rationale = $this->metadataValue($metadata, 'sample_size_rationale');
        if ([] === $subjects && 'Missing' === $rationale) {
            return 'Missing';
        }

        return \sprintf('n=%d animals | rationale=%s', \count($subjects), $rationale);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function inclusionExclusionValue(array $metadata): string
    {
        $inclusion = $this->metadataValue($metadata, 'inclusion_criteria');
        $exclusion = $this->metadataValue($metadata, 'exclusion_criteria');
        $nPerGroup = $this->metadataValue($metadata, 'n_per_group');
        if ('Missing' === $inclusion && 'Missing' === $exclusion && 'Missing' === $nPerGroup) {
            return 'Missing';
        }

        return \sprintf('Inclusion=%s | Exclusion=%s | n per group=%s', $inclusion, $exclusion, $nPerGroup);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function randomisationValue(array $metadata): string
    {
        $randomization = $this->metadataValue($metadata, 'randomization');
        $confounders = $this->metadataValue($metadata, 'confounder_control');
        if ('Missing' === $randomization && 'Missing' === $confounders) {
            return 'Missing';
        }

        return \sprintf('Allocation=%s | Confounder control=%s', $randomization, $confounders);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function outcomeMeasuresValue(array $metadata): string
    {
        $allOutcomes = $this->metadataValue($metadata, 'outcome_measures');
        $primaryOutcome = $this->metadataValue($metadata, 'primary_outcome');
        if ('Missing' === $allOutcomes && 'Missing' === $primaryOutcome) {
            return 'Missing';
        }

        return \sprintf('Outcomes=%s | Primary=%s', $allOutcomes, $primaryOutcome);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function statisticalMethodsValue(array $metadata): string
    {
        $methods = $this->metadataValue($metadata, 'statistical_methods');
        $assumptions = $this->metadataValue($metadata, 'statistical_assumption_checks');
        if ('Missing' === $methods && 'Missing' === $assumptions) {
            return 'Missing';
        }

        return \sprintf('Methods=%s | Assumption checks=%s', $methods, $assumptions);
    }

    /**
     * @param list<Subject> $subjects
     */
    private function experimentalAnimalsValue(array $subjects): string
    {
        if ([] === $subjects) {
            return 'Missing';
        }

        return implode(' || ', array_map(static function (Subject $subject): string {
            $age = $subject->getBirthAt()->format('Y-m-d');

            return \sprintf(
                '%s: species=%s, strain=%s, sex=%s, birth=%s, weight=%s g, genotype=%s, externalId=%s',
                $subject->getName(),
                $subject->getSpecies()->value,
                $subject->getStrain()->getName(),
                $subject->getSex()->value,
                $age,
                null !== $subject->getWeight() ? (string) $subject->getWeight() : 'n/a',
                $subject->getGenotype() ?? 'n/a',
                $subject->getExternalId() ?? 'n/a',
            );
        }, $subjects));
    }

    /**
     * @param list<Experiment> $experiments
     */
    private function experimentalProceduresValue(array $experiments): string
    {
        $rows = [];
        foreach ($experiments as $experiment) {
            foreach ($experiment->getProcedures() as $procedure) {
                $rows[] = \sprintf(
                    '%s: what=%s, when=%s to %s, where=%s, why=%s',
                    $procedure->getName(),
                    $procedure->getProtocol() ?? $procedure->getApparatus() ?? 'Missing',
                    $procedure->getStartAt()?->format('Y-m-d H:i') ?? 'n/a',
                    $procedure->getEndAt()?->format('Y-m-d H:i') ?? 'n/a',
                    $procedure->getContext() ?? 'Missing',
                    $experiment->getGoal() ?? 'Missing',
                );
            }
        }

        return [] === $rows ? 'Missing' : implode(' || ', $rows);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resultsValue(array $metadata): string
    {
        $summary = $this->metadataValue($metadata, 'results_summary');
        $variability = $this->metadataValue($metadata, 'results_variability');
        $effectSize = $this->metadataValue($metadata, 'effect_size');
        if ('Missing' === $summary && 'Missing' === $variability && 'Missing' === $effectSize) {
            return 'Missing';
        }

        return \sprintf('Summary=%s | Variability=%s | Effect size=%s', $summary, $variability, $effectSize);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function abstractValue(?Project $project, ?Experiment $firstExperiment, array $metadata): string
    {
        $value = $this->metadataValue($metadata, 'abstract');
        if ('Missing' !== $value) {
            return $value;
        }

        $derived = trim((string) ($project?->getDescription() ?? $firstExperiment?->getDescription() ?? ''));

        return '' !== $derived ? $derived : 'Missing';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function backgroundValue(?Project $project, ?Experiment $firstExperiment, array $metadata): string
    {
        $value = $this->metadataValue($metadata, 'background');
        if ('Missing' !== $value) {
            return $value;
        }

        return trim((string) ($project?->getGoal() ?? $firstExperiment?->getGoal() ?? '')) ?: 'Missing';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function objectivesValue(?Project $project, ?Experiment $firstExperiment, array $metadata): string
    {
        $value = $this->metadataValue($metadata, 'objectives');
        if ('Missing' !== $value) {
            return $value;
        }

        return trim((string) ($firstExperiment?->getGoal() ?? $project?->getGoal() ?? '')) ?: 'Missing';
    }

    /**
     * @param list<Subject> $subjects
     */
    private function housingAndHusbandryValue(array $subjects): string
    {
        $rows = [];
        foreach ($subjects as $subject) {
            $cage = $subject->getCage();
            if (null === $cage) {
                continue;
            }

            $environment = $cage->getEnvironmentHousing();
            $rows[] = \sprintf(
                'cage=%s, type=%s, format=%s, bedding=%s, enrichment=%s, room=%s, temp=%s C, humidity=%s%%',
                $cage->getExternalId() ?? $cage->getId()?->toRfc4122() ?? 'n/a',
                $cage->getType()->value,
                $cage->getFormat()->value,
                $cage->getBeddingType()->value,
                null !== $cage->getEnrichmentType() ? $cage->getEnrichmentType()->value : 'n/a',
                $environment?->getRoom() ?? 'n/a',
                null !== $environment?->getTemperature() ? (string) $environment->getTemperature() : 'n/a',
                null !== $environment?->getHumidity() ? (string) $environment->getHumidity() : 'n/a',
            );
        }

        return [] === $rows ? 'Missing' : implode(' || ', array_unique($rows));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function animalCareMonitoringValue(array $metadata): string
    {
        $painMitigation = $this->metadataValue($metadata, 'pain_distress_mitigation');
        $adverseEvents = $this->metadataValue($metadata, 'adverse_events');
        $humaneEndpoints = $this->metadataValue($metadata, 'humane_endpoints');

        if ('Missing' === $painMitigation && 'Missing' === $adverseEvents && 'Missing' === $humaneEndpoints) {
            return 'Missing';
        }

        return \sprintf('Mitigation=%s | Adverse events=%s | Humane endpoints=%s', $painMitigation, $adverseEvents, $humaneEndpoints);
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function protocolRegistrationValue(?Experiment $firstExperiment, ?array $metadata): string
    {
        if (null === $firstExperiment) {
            return 'Missing';
        }

        $registration = $this->metadataValue($metadata ?? [], 'protocol_registration');
        $fallback = $firstExperiment->getElaftwExternalId();

        if ('Missing' === $registration && null === $fallback) {
            return 'Missing';
        }

        return 'Missing' !== $registration ? $registration : (string) $fallback;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function dataAccessValue(?Project $project, ?Experiment $firstExperiment, ?array $metadata): string
    {
        $metadataDataAccess = $this->metadataValue($metadata ?? [], 'data_access');
        if ('Missing' !== $metadataDataAccess) {
            return $metadataDataAccess;
        }

        return $firstExperiment?->getRepositoryLink() ?? $project?->getRepositoryLink() ?? 'Missing';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function declarationOfInterestsValue(array $metadata): string
    {
        $conflicts = $this->metadataValue($metadata, 'conflicts_of_interest');
        $funding = $this->metadataValue($metadata, 'funding_sources');

        if ('Missing' === $conflicts && 'Missing' === $funding) {
            return 'Missing';
        }

        return \sprintf('Conflicts=%s | Funding=%s', $conflicts, $funding);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function metadataValue(array $metadata, string $key, string $default = 'Missing'): string
    {
        $value = $metadata[$key] ?? null;
        if (\is_string($value) && '' !== trim($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * @param list<Experiment> $experiments
     *
     * @return list<Subject>
     */
    private function subjectsForExperiments(array $experiments): array
    {
        $subjects = [];
        foreach ($experiments as $experiment) {
            foreach ($experiment->getProcedures() as $procedure) {
                foreach ($procedure->getSubjects() as $subject) {
                    $id = $subject->getId()?->toRfc4122() ?? spl_object_id($subject);
                    $subjects[(string) $id] = $subject;
                }
            }
        }

        return array_values($subjects);
    }
}
