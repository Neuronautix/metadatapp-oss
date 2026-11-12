<?php

declare(strict_types=1);

namespace App\Guideline;

use App\Entity\Account;
use App\Entity\Experiment;
use App\Entity\GuidelineFieldValue;
use App\Entity\Project;
use App\Entity\Subject;
use App\Guideline\Evidence\ComputedFacts;
use App\Guideline\Evidence\GuidelineEvidenceRetriever;
use App\Repository\GuidelineFieldValueRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Builds the ONE shared metadata dict keyed by field id (§5, invariant §9), plus
 * the available CSV column list, for a given Study (Experiment) or Investigation
 * (Project).
 *
 * Base values are derived conservatively from entity fields + getElabftwMetadata()
 * (only mapped when confident — never invent). Stored fill values
 * ({@see GuidelineFieldValue}) are overlaid on top so a manual/ai/paper fill wins.
 */
final readonly class ResourceMetadataResolver
{
    public function __construct(
        private GuidelineFieldValueRepository $fieldValues,
        private GuidelineEvidenceRetriever $evidenceRetriever,
    ) {
    }

    /**
     * @return array{metadata: array<string, mixed>, columns: list<CsvColumn>}
     */
    public function resolveForStudy(Experiment $experiment): array
    {
        $base = $this->baseFromExperiment($experiment);
        $columns = $this->columnsFromSubjects($this->subjectsForExperiments([$experiment]));
        $resourceId = $experiment->getId();
        $metadata = null !== $resourceId
            ? $this->overlayStored($base, GuidelineFieldValue::RESOURCE_STUDY, $resourceId, $experiment->getAccount())
            : $base;

        return ['metadata' => $metadata, 'columns' => $columns];
    }

    /**
     * @return array{metadata: array<string, mixed>, columns: list<CsvColumn>}
     */
    public function resolveForInvestigation(Project $project): array
    {
        /** @var list<Experiment> $experiments */
        $experiments = array_values($project->getExperiments()->toArray());
        $base = $this->baseFromProject($project, $experiments);
        $columns = $this->columnsFromSubjects($this->subjectsForExperiments($experiments));
        $resourceId = $project->getId();
        $metadata = null !== $resourceId
            ? $this->overlayStored($base, GuidelineFieldValue::RESOURCE_INVESTIGATION, $resourceId, $project->getAccount())
            : $base;

        return ['metadata' => $metadata, 'columns' => $columns];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseFromExperiment(Experiment $experiment): array
    {
        $elab = $experiment->getElabftwMetadata() ?? [];

        $base = [];
        $this->set($base, 'study_title', $experiment->getName());
        $this->set($base, 'study_objectives', $experiment->getGoal());
        $this->set($base, 'procedures_description', $experiment->getProtocol());

        $this->mergeComputed($base, $this->evidenceRetriever->computeFacts([$experiment]));
        $this->mergeElabftw($base, $elab);

        return $base;
    }

    /**
     * @param list<Experiment> $experiments
     *
     * @return array<string, mixed>
     */
    private function baseFromProject(Project $project, array $experiments): array
    {
        $first = $experiments[0] ?? null;
        $elab = $first?->getElabftwMetadata() ?? [];

        $base = [];
        $this->set($base, 'study_title', $project->getName());
        $this->set($base, 'study_objectives', $project->getGoal() ?? $first?->getGoal());
        $this->set($base, 'procedures_description', $first?->getProtocol());

        $this->mergeComputed($base, $this->evidenceRetriever->computeFacts($experiments));
        $this->mergeElabftw($base, $elab);

        return $base;
    }

    /**
     * Map the deterministic computed facts 1:1 onto ARRIVE/MNMS field ids as base
     * values, so those fields show satisfied WITHOUT AI. The computation is shared
     * with the evidence retriever (single source of truth). Stored fills are
     * overlaid afterwards, so a user fill always wins.
     *
     * @param array<string, mixed> $base
     */
    private function mergeComputed(array &$base, ComputedFacts $facts): void
    {
        if (0 === $facts->totalSubjects) {
            return;
        }

        if ([] !== $facts->species) {
            $this->set($base, 'species', implode(', ', $facts->species));
        }
        if ([] !== $facts->strains) {
            $this->set($base, 'strain', implode(', ', $facts->strains));
        }
        if ([] !== $facts->sexCounts) {
            $this->set($base, 'sex', implode(', ', array_keys($facts->sexCounts)));
        }
        $this->set($base, 'total_n', (string) $facts->totalSubjects);
        $this->set($base, 'n', (string) $facts->totalSubjects);

        $groups = [];
        foreach ($facts->sexCounts as $sex => $count) {
            $groups[] = \sprintf('%s (n=%d)', $sex, $count);
        }
        if ([] !== $groups) {
            $this->set($base, 'experimental_groups', implode('; ', $groups));
        }

        $housing = [];
        if (null !== $facts->housingDensity) {
            $housing[] = \sprintf('%s subjects/cage', $facts->housingDensity);
        }
        if ([] !== $facts->cageTypes) {
            $housing[] = 'cage type(s): ' . implode(', ', $facts->cageTypes);
        }
        if ([] !== $housing) {
            $this->set($base, 'housing', implode('; ', $housing));
        }
    }

    /**
     * Overlay any value present in the elabftw metadata blob, keyed by the same
     * field id. Only keys we recognise as guideline field ids are mapped.
     *
     * @param array<string, mixed>    $base
     * @param array<array-key, mixed> $elab
     */
    private function mergeElabftw(array &$base, array $elab): void
    {
        foreach ($elab as $key => $value) {
            if (\is_string($key) && $this->isNonEmpty($value)) {
                $base[$key] = $value;
            }
        }
    }

    /**
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    private function overlayStored(array $base, string $resourceType, Uuid $resourceId, Account $account): array
    {
        // The shared metadata dict is template-agnostic: fill values for ANY
        // template assigned to this resource feed the one dict (§9). Overlay them
        // on top of the entity-derived base so a manual/ai/paper fill wins.
        foreach ($this->fieldValues->findBy([
            'resourceType' => $resourceType,
            'resourceId' => $resourceId,
            'account' => $account,
        ]) as $row) {
            if ($this->isNonEmpty($row->getValue())) {
                $base[$row->getFieldId()] = $row->getValue();
            }
        }

        return $base;
    }

    /**
     * @param list<Subject> $subjects
     *
     * @return list<CsvColumn>
     */
    private function columnsFromSubjects(array $subjects): array
    {
        if ([] === $subjects) {
            return [];
        }

        // Derive the column list from the data we actually have for subjects.
        $columns = [
            new CsvColumn('subject_id'),
            new CsvColumn('species'),
            new CsvColumn('strain'),
            new CsvColumn('sex'),
            new CsvColumn('age'),
        ];

        $hasWeight = false;
        $hasCage = false;
        foreach ($subjects as $subject) {
            if (null !== $subject->getWeight()) {
                $hasWeight = true;
            }
            if (null !== $subject->getCage()) {
                $hasCage = true;
            }
        }
        if ($hasWeight) {
            $columns[] = new CsvColumn('weight', userUnit: 'g');
        }
        if ($hasCage) {
            $columns[] = new CsvColumn('cage');
        }

        return $columns;
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
                    $id = $subject->getId()?->toRfc4122() ?? (string) spl_object_id($subject);
                    $subjects[$id] = $subject;
                }
            }
        }

        return array_values($subjects);
    }

    /**
     * @param array<string, mixed> $base
     */
    private function set(array &$base, string $key, mixed $value): void
    {
        if ($this->isNonEmpty($value)) {
            $base[$key] = $value;
        }
    }

    private function isNonEmpty(mixed $value): bool
    {
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
}
