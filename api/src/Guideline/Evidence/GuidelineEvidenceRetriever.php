<?php

declare(strict_types=1);

namespace App\Guideline\Evidence;

use App\Entity\Account;
use App\Entity\Cage;
use App\Entity\CageHcmMeasure;
use App\Entity\ConnectedResourceLink;
use App\Entity\Experiment;
use App\Entity\GuidelineFieldValue;
use App\Entity\Procedure;
use App\Entity\Project;
use App\Entity\Subject;
use App\Repository\GuidelineFieldValueRepository;
use App\Service\FairAssessmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Tenant-scoped, deterministic, read-only evidence retriever (§ enhancement).
 *
 * Gathers grounding evidence for a Study (Experiment) or Investigation (Project)
 * from ONLY locally-persisted Doctrine rows + the elabFTW metadata blob — it
 * NEVER calls a ConnectedApps client/proxy and never invents facts. Every text
 * value is length-bounded and stripped of control/role-marker characters so the
 * output is safe to embed in an LLM prompt.
 *
 * It also exposes {@see self::computeFacts()} so the metadata resolver can map
 * a subset of the same deterministic computation onto guideline field ids as
 * base values without duplicating the computation.
 */
final readonly class GuidelineEvidenceRetriever
{
    private const int MAX_VALUE_LENGTH = 500;

    /** Field ids the elabFTW key heuristics may map onto. */
    private const string FIELD_ETHICS = 'ethics_statement';
    private const string FIELD_LICENCE = 'project_licence';
    private const string FIELD_PROTOCOL_NUMBERS = 'protocol_numbers';
    private const string FIELD_PROCEDURES = 'procedures_description';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GuidelineFieldValueRepository $fieldValues,
        private FairAssessmentService $fairService,
        private ReferenceEvidenceResolver $references,
    ) {
    }

    public function retrieveForStudy(Experiment $experiment): EvidenceBundle
    {
        $items = $this->collect(
            [$experiment],
            $experiment->getAccount(),
            $experiment->getId(),
            $this->fairSummary($this->fairService->assessExperiment($experiment)),
        );

        return new EvidenceBundle($items, $this->references);
    }

    public function retrieveForInvestigation(Project $project): EvidenceBundle
    {
        /** @var list<Experiment> $experiments */
        $experiments = array_values($project->getExperiments()->toArray());

        $items = $this->collect(
            $experiments,
            $project->getAccount(),
            $project->getId(),
            $this->fairSummary($this->fairService->assessProject($project)),
        );

        return new EvidenceBundle($items, $this->references);
    }

    /**
     * Deterministic facts for the given experiments. Shared with the metadata
     * resolver so the computation lives in one place.
     *
     * @param list<Experiment> $experiments
     */
    public function computeFacts(array $experiments): ComputedFacts
    {
        $subjects = $this->subjectsForExperiments($experiments);
        if ([] === $subjects) {
            return new ComputedFacts(studyTimeSpan: $this->studyTimeSpan($experiments));
        }

        $sexCounts = [];
        $species = [];
        $strains = [];
        $genotypes = [];
        $hasWeight = false;
        $ages = [];
        $now = new \DateTimeImmutable();

        /** @var array<string, Cage> $cages */
        $cages = [];
        foreach ($subjects as $subject) {
            $sex = $subject->getSex()->value;
            $sexCounts[$sex] = ($sexCounts[$sex] ?? 0) + 1;
            $species[$subject->getSpecies()->value] = true;
            $strains[$subject->getStrain()->getName()] = true;
            $genotype = $subject->getGenotype();
            if (null !== $genotype && '' !== trim($genotype)) {
                $genotypes[trim($genotype)] = true;
            }
            if (null !== $subject->getWeight()) {
                $hasWeight = true;
            }
            $ages[] = (int) $subject->getBirthAt()->diff($now)->days;

            $cage = $subject->getCage();
            if (null !== $cage) {
                $cageId = $cage->getId()?->toRfc4122() ?? (string) spl_object_id($cage);
                $cages[$cageId] = $cage;
            }
        }

        $cageTypes = [];
        $hasEnrichment = false;
        foreach ($cages as $cage) {
            $cageTypes[$cage->getType()->value] = true;
            if ($cage->hasEnrichments() || null !== $cage->getEnrichmentType()) {
                $hasEnrichment = true;
            }
        }

        $occupiedCages = \count($cages);
        $housingDensity = $occupiedCages > 0 ? round(\count($subjects) / $occupiedCages, 2) : null;

        [$lightsOn, $lightDuration, $hasHcm] = $this->homeCageSignals(array_values($cages));

        // $ages always has an entry here ($subjects is non-empty above).
        $ageRange = \sprintf('%d–%d days', min($ages), max($ages));

        return new ComputedFacts(
            totalSubjects: \count($subjects),
            sexCounts: $sexCounts,
            species: array_keys($species),
            strains: array_keys($strains),
            genotypes: array_keys($genotypes),
            ageRange: $ageRange,
            hasWeight: $hasWeight,
            weightUnit: 'g',
            housingDensity: $housingDensity,
            cageTypes: array_keys($cageTypes),
            hasEnrichment: $hasEnrichment,
            lightsOnUtcHour: $lightsOn,
            lightDurationHours: $lightDuration,
            hasHcmMeasures: $hasHcm,
            studyTimeSpan: $this->studyTimeSpan($experiments),
        );
    }

    /**
     * @param list<Experiment>   $experiments
     * @param list<EvidenceItem> $fairItems
     *
     * @return list<EvidenceItem>
     */
    private function collect(array $experiments, Account $account, ?Uuid $resourceId, array $fairItems): array
    {
        $items = [];

        // 1. Computed facts (general + a few field-mapped).
        $facts = $this->computeFacts($experiments);
        foreach ($this->computedEvidence($facts) as $item) {
            $items[] = $item;
        }

        // 2. elabFTW evidence (from the first experiment carrying a blob).
        foreach ($experiments as $experiment) {
            $elab = $experiment->getElabftwMetadata();
            if (null !== $elab && [] !== $elab) {
                foreach ($this->elabftwEvidence($elab) as $item) {
                    $items[] = $item;
                }
                break;
            }
        }

        // 3. Connected-resource links + procedure protocols + strain/genotype.
        foreach ($experiments as $experiment) {
            foreach ($experiment->getConnectedResourceLinks() as $link) {
                $item = $this->connectedLinkEvidence($link);
                if (null !== $item) {
                    $items[] = $item;
                }
            }
            foreach ($experiment->getProcedures() as $procedure) {
                $item = $this->procedureEvidence($procedure);
                if (null !== $item) {
                    $items[] = $item;
                }
            }
        }
        foreach ($this->strainEvidence($facts) as $item) {
            $items[] = $item;
        }

        // 4. Cross-investigation history (latest fills for relevant fields in this account).
        foreach ($this->historyEvidence($account, $resourceId) as $item) {
            $items[] = $item;
        }

        // 5. FAIR summary (general context).
        foreach ($fairItems as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return list<EvidenceItem>
     */
    private function computedEvidence(ComputedFacts $facts): array
    {
        if (0 === $facts->totalSubjects && null === $facts->studyTimeSpan) {
            return [];
        }

        $items = [];

        if ($facts->totalSubjects > 0) {
            $items[] = $this->computed(null, 'Total subjects (n)', (string) $facts->totalSubjects, \sprintf('computed from %d subjects', $facts->totalSubjects));

            if ([] !== $facts->sexCounts) {
                $parts = [];
                foreach ($facts->sexCounts as $sex => $count) {
                    $parts[] = \sprintf('%s: %d', $sex, $count);
                }
                $items[] = $this->computed('sex', 'Sex distribution', implode(', ', $parts), \sprintf('computed from %d subjects', $facts->totalSubjects));
            }
            if ([] !== $facts->species) {
                $items[] = $this->computed('species', 'Species', implode(', ', $facts->species), \sprintf('computed from %d subjects', $facts->totalSubjects));
            }
            if ([] !== $facts->strains) {
                $items[] = $this->computed('strain', 'Strains', implode(', ', $facts->strains), \sprintf('computed from %d subjects', $facts->totalSubjects));
            }
            $items[] = $this->computed('total_n', 'Sample size (total_n)', (string) $facts->totalSubjects, \sprintf('computed from %d subjects', $facts->totalSubjects));

            $groups = [];
            if ([] !== $facts->species) {
                $groups[] = \count($facts->species) . ' species';
            }
            if ([] !== $facts->strains) {
                $groups[] = \count($facts->strains) . ' strain(s)';
            }
            if ([] !== $facts->sexCounts) {
                $groups[] = \count($facts->sexCounts) . ' sex group(s)';
            }
            if ([] !== $groups) {
                $items[] = $this->computed('experimental_groups', 'Experimental groups', implode(', ', $groups), \sprintf('computed from %d subjects', $facts->totalSubjects));
            }

            if (null !== $facts->ageRange) {
                $items[] = $this->computed(null, 'Age range', $facts->ageRange, \sprintf('computed from %d subjects', $facts->totalSubjects));
            }
            if ($facts->hasWeight) {
                $items[] = $this->computed(null, 'Weight recorded', \sprintf('yes (unit: %s)', $facts->weightUnit), 'computed from subject weight rows');
            }

            $housing = $this->housingSummary($facts);
            if ('' !== $housing) {
                $items[] = $this->computed('housing', 'Housing', $housing, \sprintf('computed from %d subjects', $facts->totalSubjects));
            }

            if (null !== $facts->lightsOnUtcHour || $facts->hasHcmMeasures) {
                $items[] = $this->computed(null, 'Home-cage monitoring', $this->hcmSummary($facts), 'computed from home-cage monitoring rows');
            }
        }

        if (null !== $facts->studyTimeSpan) {
            $items[] = $this->computed(null, 'Study time span', $facts->studyTimeSpan, 'computed from study start/end dates');
        }

        return $items;
    }

    private function housingSummary(ComputedFacts $facts): string
    {
        $parts = [];
        if (null !== $facts->housingDensity) {
            $parts[] = \sprintf('%s subjects/cage', $facts->housingDensity);
        }
        if ([] !== $facts->cageTypes) {
            $parts[] = 'cage type(s): ' . implode(', ', $facts->cageTypes);
        }
        $parts[] = $facts->hasEnrichment ? 'enrichment present' : 'no enrichment recorded';

        return implode('; ', $parts);
    }

    private function hcmSummary(ComputedFacts $facts): string
    {
        $parts = [];
        if (null !== $facts->lightsOnUtcHour) {
            $parts[] = \sprintf('lights on %02d:00 UTC', $facts->lightsOnUtcHour);
        }
        if (null !== $facts->lightDurationHours) {
            $parts[] = \sprintf('%dh light phase', $facts->lightDurationHours);
        }
        $parts[] = $facts->hasHcmMeasures ? 'activity/HCM measures present' : 'no HCM measures';

        return implode('; ', $parts);
    }

    /**
     * @param array<array-key, mixed> $elab
     *
     * @return list<EvidenceItem>
     */
    private function elabftwEvidence(array $elab): array
    {
        $items = [];

        // Flatten top-level keys + extra_fields (elabFTW stores user fields there).
        $pairs = [];
        foreach ($elab as $key => $value) {
            if (\is_string($key) && 'extra_fields' !== $key) {
                $pairs[$key] = $value;
            }
        }
        $extra = $elab['extra_fields'] ?? null;
        if (\is_array($extra)) {
            foreach ($extra as $key => $value) {
                if (\is_string($key)) {
                    // elabFTW extra_fields entries are usually {type, value, ...}.
                    $pairs[$key] = \is_array($value) && \array_key_exists('value', $value) ? $value['value'] : $value;
                }
            }
        }

        foreach ($pairs as $key => $value) {
            $text = $this->scalarToText($value);
            if ('' === $text) {
                continue;
            }
            $fieldId = $this->mapElabftwKey($key);
            $items[] = new EvidenceItem(
                fieldId: $fieldId,
                source: EvidenceItem::SOURCE_ELABFTW,
                label: $this->sanitize($key, 120),
                value: $this->sanitize($text),
                provenance: \sprintf('elabFTW extra field: %s', $this->sanitize($key, 120)),
                confidence: null !== $fieldId ? 0.6 : null,
            );
        }

        return $items;
    }

    private function mapElabftwKey(string $key): ?string
    {
        $needle = strtolower($key);

        return match (true) {
            str_contains($needle, 'ethic') || str_contains($needle, 'approval') => self::FIELD_ETHICS,
            str_contains($needle, 'licence') || str_contains($needle, 'license') => self::FIELD_LICENCE,
            str_contains($needle, 'protocol') => self::FIELD_PROTOCOL_NUMBERS,
            str_contains($needle, 'procedure') => self::FIELD_PROCEDURES,
            default => null,
        };
    }

    private function connectedLinkEvidence(ConnectedResourceLink $link): ?EvidenceItem
    {
        $url = $link->getUrl();
        $label = $link->getLabel();
        $value = trim((string) ($label ?? '') . (null !== $url ? ' ' . $url : ''));
        if ('' === $value) {
            $value = $link->getExternalId();
        }
        if ('' === trim($value)) {
            return null;
        }

        $type = strtolower($link->getResourceType());
        $fieldId = str_contains($type, 'protocol') ? self::FIELD_PROTOCOL_NUMBERS : self::FIELD_PROCEDURES;

        return new EvidenceItem(
            fieldId: $fieldId,
            source: EvidenceItem::SOURCE_CONNECTED_LINK,
            label: $this->sanitize(\sprintf('%s %s', $link->getProvider(), $link->getResourceType()), 120),
            value: $this->sanitize($value),
            provenance: \sprintf('connected resource: %s/%s', $this->sanitize($link->getProvider(), 60), $this->sanitize($link->getExternalId(), 60)),
            confidence: 0.5,
        );
    }

    private function procedureEvidence(Procedure $procedure): ?EvidenceItem
    {
        $protocol = $procedure->getProtocol();
        $protocolUri = $procedure->getProtocolUri();
        $value = trim((string) ($protocol ?? '') . (null !== $protocolUri ? ' ' . $protocolUri : ''));
        if ('' === $value) {
            return null;
        }

        return new EvidenceItem(
            fieldId: self::FIELD_PROCEDURES,
            source: EvidenceItem::SOURCE_ENTITY,
            label: $this->sanitize($procedure->getName(), 120),
            value: $this->sanitize($value),
            provenance: \sprintf('procedure protocol: %s', $this->sanitize($procedure->getName(), 120)),
            confidence: 0.7,
        );
    }

    /**
     * @return list<EvidenceItem>
     */
    private function strainEvidence(ComputedFacts $facts): array
    {
        $items = [];
        if ([] !== $facts->strains) {
            $items[] = new EvidenceItem(
                fieldId: 'strain',
                source: EvidenceItem::SOURCE_ENTITY,
                label: 'Strain',
                value: $this->sanitize(implode(', ', $facts->strains)),
                provenance: 'computed from subject strain rows',
                confidence: 0.9,
            );
        }
        if ([] !== $facts->genotypes) {
            $items[] = new EvidenceItem(
                fieldId: 'strain',
                source: EvidenceItem::SOURCE_ENTITY,
                label: 'Genotypes',
                value: $this->sanitize(implode(', ', $facts->genotypes)),
                provenance: 'computed from subject genotype rows',
                confidence: 0.6,
            );
        }

        return $items;
    }

    /**
     * Cross-investigation reuse: the most-recent non-empty fill for each relevant
     * field id from OTHER resources in the SAME account.
     *
     * @return list<EvidenceItem>
     */
    private function historyEvidence(Account $account, ?Uuid $resourceId): array
    {
        $fieldIds = [
            self::FIELD_ETHICS,
            self::FIELD_LICENCE,
            self::FIELD_PROTOCOL_NUMBERS,
            self::FIELD_PROCEDURES,
            'study_objectives',
            'housing',
            'experimental_groups',
            'humane_endpoints',
            'welfare_assessment',
        ];

        $latest = $this->fieldValues->findLatestValuesForFieldsAcrossAccount($fieldIds, $account, $resourceId);

        $items = [];
        foreach ($latest as $fieldId => $row) {
            $text = $this->scalarToText($row->getValue());
            if ('' === $text) {
                continue;
            }
            $items[] = new EvidenceItem(
                fieldId: $fieldId,
                source: EvidenceItem::SOURCE_HISTORY,
                label: \sprintf('Prior value for %s', $fieldId),
                value: $this->sanitize($text),
                provenance: $this->historyProvenance($row),
                confidence: 0.55,
            );
        }

        return $items;
    }

    private function historyProvenance(GuidelineFieldValue $row): string
    {
        $label = $this->resolveResourceName($row->getResourceType(), $row->getResourceId(), $row->getAccount());
        $kind = GuidelineFieldValue::RESOURCE_INVESTIGATION === $row->getResourceType() ? 'Investigation' : 'Study';

        return \sprintf('%s: %s', $kind, $this->sanitize($label, 120));
    }

    private function resolveResourceName(string $resourceType, Uuid $resourceId, Account $account): string
    {
        if (GuidelineFieldValue::RESOURCE_INVESTIGATION === $resourceType) {
            /** @var Project|null $project */
            $project = $this->entityManager->getRepository(Project::class)->findOneBy(['id' => $resourceId, 'account' => $account]);

            return null !== $project ? $project->getName() : $resourceId->toRfc4122();
        }

        /** @var Experiment|null $experiment */
        $experiment = $this->entityManager->getRepository(Experiment::class)->findOneBy(['id' => $resourceId, 'account' => $account]);

        return null !== $experiment ? $experiment->getName() : $resourceId->toRfc4122();
    }

    /**
     * @param array{score: array{total: int, ...}, criteria: array<string, list<array{id: string, label: string, pass: bool, note: string, description: string}>>} $assessment
     *
     * @return list<EvidenceItem>
     */
    private function fairSummary(array $assessment): array
    {
        $overall = $assessment['score']['total'];
        $weak = [];
        foreach ($assessment['criteria'] as $items) {
            foreach ($items as $criterion) {
                if (false === $criterion['pass']) {
                    $weak[] = $criterion['id'];
                }
            }
        }

        $value = \sprintf(
            'Overall FAIR score: %d%%%s',
            $overall,
            [] !== $weak ? '. Weak criteria: ' . implode(', ', $weak) : '. All criteria pass.',
        );

        return [new EvidenceItem(
            fieldId: null,
            source: EvidenceItem::SOURCE_FAIR,
            label: 'FAIR assessment',
            value: $this->sanitize($value),
            provenance: 'computed from FairAssessmentService',
        )];
    }

    /**
     * Light schedule + whether HCM/activity measures exist, derived from the
     * occupied cages' HomeCageMonitoring + CageHcmMeasure rows (account-scoped via
     * the cage entities, which are account-aware).
     *
     * @param list<Cage> $cages
     *
     * @return array{0: ?int, 1: ?int, 2: bool} [lightsOnUtcHour, lightDurationHours, hasHcmMeasures]
     */
    private function homeCageSignals(array $cages): array
    {
        $lightsOn = null;
        $lightDuration = null;
        $hcmIds = [];
        foreach ($cages as $cage) {
            $hcm = $cage->getHomeCageMonitoring();
            if (null === $hcm) {
                continue;
            }
            if (null === $lightsOn && null !== $hcm->getLightsOnUtcHour()) {
                $lightsOn = $hcm->getLightsOnUtcHour();
                $lightDuration = $hcm->getLightDurationHours();
            }
            $id = $hcm->getId();
            if (null !== $id) {
                $hcmIds[$id->toRfc4122()] = true;
            }
        }

        $hasMeasures = false;
        if ([] !== $hcmIds) {
            $count = (int) $this->entityManager->createQueryBuilder()
                ->select('COUNT(m.id)')
                ->from(CageHcmMeasure::class, 'm')
                ->join('m.homeCageMonitoring', 'h')
                ->where('h.id IN (:ids)')
                ->setParameter('ids', array_keys($hcmIds))
                ->getQuery()
                ->getSingleScalarResult()
            ;
            $hasMeasures = $count > 0;
        }

        return [$lightsOn, $lightDuration, $hasMeasures];
    }

    /**
     * @param list<Experiment> $experiments
     */
    private function studyTimeSpan(array $experiments): ?string
    {
        $start = null;
        $end = null;
        foreach ($experiments as $experiment) {
            $s = $experiment->getStartAt();
            if (null === $start || $s < $start) {
                $start = $s;
            }
            $e = $experiment->getEndAt();
            if (null !== $e && (null === $end || $e > $end)) {
                $end = $e;
            }
        }

        if (null === $start) {
            return null;
        }

        return \sprintf(
            '%s → %s',
            $start->format('Y-m-d'),
            null !== $end ? $end->format('Y-m-d') : 'ongoing',
        );
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

    private function computed(?string $fieldId, string $label, string $value, string $provenance): EvidenceItem
    {
        return new EvidenceItem(
            fieldId: $fieldId,
            source: EvidenceItem::SOURCE_COMPUTED,
            label: $label,
            value: $this->sanitize($value),
            provenance: $provenance,
            confidence: null !== $fieldId ? 1.0 : null,
        );
    }

    private function scalarToText(mixed $value): string
    {
        if (\is_array($value)) {
            $flat = array_filter(array_map(
                static fn (mixed $v): string => \is_scalar($v) ? trim((string) $v) : '',
                $value,
            ), static fn (string $v): bool => '' !== $v);

            return implode(', ', $flat);
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    /**
     * Length-bound and strip control/role-marker characters so the text is safe
     * to embed in an LLM prompt (defends against prompt-injection pivots).
     */
    private function sanitize(string $value, int $maxLength = self::MAX_VALUE_LENGTH): string
    {
        // Strip ASCII control chars (except none kept) and collapse whitespace.
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        // Neutralise common chat role markers / instruction framing.
        $clean = preg_replace('/(?i)\b(system|assistant|user)\s*:/', '$1 -', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);

        if (mb_strlen($clean) > $maxLength) {
            $clean = mb_substr($clean, 0, $maxLength - 1) . '…';
        }

        return $clean;
    }
}
