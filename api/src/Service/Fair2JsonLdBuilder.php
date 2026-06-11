<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\Subject;
use App\Entity\User;

/**
 * Builds a Croissant ML + FAIR² JSON-LD document from an Investigation (Project) or Study (Experiment).
 *
 * Spec references:
 *  - schema.org Dataset   https://schema.org/Dataset
 *  - MLCommons Croissant  https://docs.mlcommons.org/croissant/docs/croissant-spec.html
 *  - FAIR² extensions     https://fair2.science/ns/
 *  - CRediT roles         https://credit.niso.org/
 *  - QUDT units           https://qudt.org/
 *  - BioSchemas           https://bioschemas.org/
 */
class Fair2JsonLdBuilder
{
    private const string CROISSANT_CONFORMANCE_URI = 'http://mlcommons.org/croissant/1.0';
    private const string DEFAULT_LICENSE = 'https://creativecommons.org/licenses/by/4.0/';

    /**
     * @return array<string, mixed>
     */
    public function buildForProject(Project $project, string $baseUrl): array
    {
        $projectIri = \sprintf('%s/investigations/%s/fair2.json', $baseUrl, $project->getId()?->toRfc4122());
        $user = $project->getUser();
        $creator = $this->buildPerson($user);

        // --- Study nodes ---
        $studyNodes = [];
        foreach ($project->getExperiments() as $experiment) {
            $studyIri = \sprintf('%s/studies/%s/fair2.json', $baseUrl, $experiment->getId()?->toRfc4122());
            $studyNode = [
                '@type' => ['sc:Dataset', 'bioschemas:Study'],
                '@id' => $studyIri,
                'name' => $experiment->getName(),
                'description' => $experiment->getGoal() ?? $experiment->getDescription(),
                'url' => $experiment->getRepositoryLink(),
                'isPartOf' => ['@id' => $projectIri],
            ];
            if (null !== $experiment->getExternalId()) {
                $studyNode['identifier'] = $experiment->getExternalId();
            }
            $studyNodes[] = array_filter($studyNode, static fn (mixed $v) => null !== $v);
        }

        $rootNode = array_filter([
            '@type' => ['sc:Dataset', 'bioschemas:Project'],
            '@id' => $projectIri,
            'cr:conformsTo' => self::CROISSANT_CONFORMANCE_URI,
            'name' => $project->getName(),
            'description' => $project->getGoal() ?? $project->getDescription(),
            'url' => $project->getRepositoryLink(),
            'license' => self::DEFAULT_LICENSE,
            'creator' => $creator,
            'dateCreated' => $project->getStartAt()?->format('Y-m-d'),
            'dateModified' => $project->getEndAt()?->format('Y-m-d'),
            'hasPart' => [] !== $studyNodes ? array_map(static fn (array $s) => ['@id' => $s['@id']], $studyNodes) : null,
        ], static fn (mixed $v) => null !== $v);

        $graph = array_merge([$rootNode], $studyNodes);

        return [
            '@context' => $this->buildContext(),
            '@graph' => $graph,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForExperiment(Experiment $experiment, string $baseUrl): array
    {
        $experimentIri = \sprintf('%s/studies/%s/fair2.json', $baseUrl, $experiment->getId()?->toRfc4122());
        $user = $experiment->getUser();
        $creator = $this->buildPerson($user);

        $graph = [];

        // --- sameAs links (first-class connected resources) ---
        $sameAs = [];
        foreach ($experiment->getConnectedResourceLinks() as $link) {
            $ref = $link->getUrl() ?? \sprintf('urn:%s:%s', $link->getProvider(), $link->getExternalId());
            $sameAs[] = $ref;
        }

        // --- Root study node ---
        $rootNode = array_filter([
            '@type' => ['sc:Dataset', 'bioschemas:Study'],
            '@id' => $experimentIri,
            'cr:conformsTo' => self::CROISSANT_CONFORMANCE_URI,
            'name' => $experiment->getName(),
            'description' => $experiment->getGoal() ?? $experiment->getDescription(),
            'url' => $experiment->getRepositoryLink(),
            'license' => self::DEFAULT_LICENSE,
            'creator' => $creator,
            'dateCreated' => $experiment->getStartAt()->format('Y-m-d'),
            'dateModified' => $experiment->getEndAt()?->format('Y-m-d'),
            'fa2:method' => $experiment->getProtocol(),
            'identifier' => $experiment->getExternalId(),
            'sameAs' => [] !== $sameAs ? $sameAs : null,
            'isPartOf' => null !== $experiment->getProject() ? [
                '@type' => ['sc:Dataset', 'bioschemas:Project'],
                '@id' => \sprintf('%s/investigations/%s/fair2.json', $baseUrl, $experiment->getProject()->getId()?->toRfc4122()),
                'name' => $experiment->getProject()->getName(),
            ] : null,
        ], static fn (mixed $v) => null !== $v);

        $graph[] = $rootNode;

        // --- ConnectedResourceLink nodes ---
        foreach ($experiment->getConnectedResourceLinks() as $link) {
            $linkId = $link->getUrl() ?? \sprintf('urn:%s:%s', $link->getProvider(), $link->getExternalId());
            $linkNode = array_filter([
                '@type' => ['sc:Thing', 'fa2:ConnectedResource'],
                '@id' => $linkId,
                'name' => $link->getLabel(),
                'identifier' => $link->getExternalId(),
                'url' => $link->getUrl(),
                'sc:additionalType' => $link->getResourceType(),
                'fa2:provider' => $link->getProvider(),
                'dateModified' => $link->getSyncedAt()?->format(\DateTimeInterface::ATOM),
                'isRelatedTo' => ['@id' => $experimentIri],
            ], static fn (mixed $v) => null !== $v);
            $graph[] = $linkNode;
        }

        // --- Protocol nodes ---
        foreach ($experiment->getProcedures() as $procedure) {
            $procIri = \sprintf('%s/procedures/%s', $experimentIri, $procedure->getId()?->toRfc4122());
            $procNode = array_filter([
                '@type' => ['bioschemas:LabProtocol', 'sc:HowTo'],
                '@id' => $procIri,
                'name' => $procedure->getName(),
                'dateStart' => $procedure->getStartAt()?->format(\DateTimeInterface::ATOM),
                'dateEnd' => $procedure->getEndAt()?->format(\DateTimeInterface::ATOM),
                'isPartOf' => ['@id' => $experimentIri],
            ], static fn (mixed $v) => null !== $v);

            $graph[] = $procNode;

            // --- Subject (animal) nodes ---
            foreach ($procedure->getSubjects() as $subject) {
                $subjectNode = $this->buildSubjectNode($subject, $experimentIri, $procIri, $baseUrl);
                $graph[] = $subjectNode;
            }
        }

        // --- Croissant RecordSet descriptors ---
        $recordSets = $this->buildRecordSets($experiment);
        foreach ($recordSets as $rs) {
            $graph[] = $rs;
        }

        return [
            '@context' => $this->buildContext(),
            '@graph' => $graph,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildContext(): array
    {
        return [
            '@language' => 'en',
            '@vocab' => 'https://schema.org/',
            'sc' => 'https://schema.org/',
            'cr' => 'http://mlcommons.org/croissant/',
            'dct' => 'http://purl.org/dc/terms/',
            'fa2' => 'https://fair2.science/ns/',
            'credit' => 'https://credit.niso.org/',
            'qudt' => 'http://qudt.org/schema/qudt/',
            'unit' => 'http://qudt.org/vocab/unit/',
            'bioschemas' => 'https://bioschemas.org/',
            'OBI' => 'http://purl.obolibrary.org/obo/OBI_',
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function buildPerson(User $user): array
    {
        // Email is PII: omit from the public JSON-LD until a future isPublic/consent flag is introduced.
        $person = [
            '@type' => 'sc:Person',
            'name' => $user->getName(),
        ];

        if (null !== $user->getOrcid()) {
            $person['sameAs'] = \sprintf('https://orcid.org/%s', $user->getOrcid());
        }

        return array_filter($person, static fn (mixed $value) => null !== $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSubjectNode(Subject $subject, string $experimentIri, string $procIri, string $baseUrl): array
    {
        $subjectIri = \sprintf('%s/subjects/%s', $experimentIri, $subject->getId()?->toRfc4122());

        $node = [
            '@type' => ['bioschemas:BioSample', 'sc:BioChemEntity'],
            '@id' => $subjectIri,
            'name' => $subject->getName(),
            'sc:additionalType' => ['@id' => 'OBI:0100026'], // organism
            'bioschemas:scientificName' => $subject->getSpecies()->value,
            'sc:biologicalSex' => $subject->getSex()->value,
            'bioschemas:taxonomicRange' => $subject->getStrain()->getName(),
            'isPartOf' => ['@id' => $procIri],
        ];

        if (null !== $subject->getExternalId()) {
            $node['identifier'] = $subject->getExternalId();
            $node['sameAs'] = 'urn:metadatapp:subject:' . $subject->getExternalId();
        }

        if (null !== $subject->getGenotype()) {
            $node['bioschemas:hasAttribute'] = [
                '@type' => 'PropertyValue',
                'name' => 'genotype',
                'value' => $subject->getGenotype(),
            ];
        }

        $cage = $subject->getCage();
        if (null !== $cage) {
            $cageIri = \sprintf('%s/cages/%s', $experimentIri, $cage->getId()?->toRfc4122());
            $node['sc:containedInPlace'] = ['@id' => $cageIri];
        }

        return $node;
    }

    /**
     * Builds Croissant RecordSet descriptors for the study's subject measurements.
     *
     * @return list<array<string, mixed>>
     */
    private function buildRecordSets(Experiment $experiment): array
    {
        $hasProcedures = $experiment->getProcedures()->count() > 0;
        if (!$hasProcedures) {
            return [];
        }

        $expIri = 'study/' . $experiment->getId()?->toRfc4122();

        return [
            [
                '@type' => 'cr:RecordSet',
                '@id' => $expIri . '/subjects',
                'name' => 'Subjects',
                'description' => 'Animal subjects enrolled in this study.',
                'cr:field' => [
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/subjects/subject_id',
                        'name' => 'subject_id',
                        'description' => 'Unique external identifier of the subject.',
                        'dataType' => 'sc:Text',
                    ],
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/subjects/species',
                        'name' => 'species',
                        'description' => 'Taxonomic species name.',
                        'dataType' => 'sc:Text',
                    ],
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/subjects/sex',
                        'name' => 'sex',
                        'description' => 'Biological sex of the subject.',
                        'dataType' => 'sc:Text',
                    ],
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/subjects/weight',
                        'name' => 'weight',
                        'description' => 'Body weight of the subject in kilograms.',
                        'dataType' => 'sc:Number',
                        'qudt:unit' => 'unit:KiloGM',
                    ],
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/subjects/date',
                        'name' => 'date',
                        'description' => 'Date of the measurement.',
                        'dataType' => 'sc:Date',
                    ],
                ],
            ],
            [
                '@type' => 'cr:RecordSet',
                '@id' => $expIri . '/time-series',
                'name' => 'TimeSeries',
                'description' => 'Cage-level time-series activity measurements.',
                'cr:field' => [
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/time-series/cage_id',
                        'name' => 'cage_id',
                        'description' => 'External identifier of the cage.',
                        'dataType' => 'sc:Text',
                    ],
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/time-series/variable',
                        'name' => 'variable',
                        'description' => 'Measured variable (e.g. DVC activity count).',
                        'dataType' => 'sc:Text',
                    ],
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/time-series/recorded_at',
                        'name' => 'recorded_at',
                        'description' => 'UTC timestamp of the observation.',
                        'dataType' => 'sc:DateTime',
                    ],
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/time-series/value',
                        'name' => 'value',
                        'description' => 'Numeric value of the observation.',
                        'dataType' => 'sc:Number',
                    ],
                    [
                        '@type' => 'cr:Field',
                        '@id' => $expIri . '/time-series/unit',
                        'name' => 'unit',
                        'description' => 'Unit of the observation value.',
                        'dataType' => 'sc:Text',
                    ],
                ],
            ],
        ];
    }
}
