<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Experiment;
use App\Entity\Project;
use App\Enum\Species;

/**
 * Maps Metadatapp entities to/from the FAIR3R DataCite 4.x JSON exchange format
 * and the DataCite Metadata Schema 4.x XML format.
 *
 * The JSON output is aligned with the Open Science Dataset Registration Wizard
 * (https://github.com/precliniverse/Dynamic_Metadata_form, v3.0.0) which produces
 * DataCite 4.x JSON with ORCID/ROR identifiers and ontology subjects
 * (NCBITaxon, EFO, GeneID, ChEBI, DOID, UBERON).
 *
 * Exchange schema: api/schemas/fair3r-fdf-dataset.schema.json
 * FAIR3R wizard:  api/schemas/fair3r-wizard.schema.json
 * DataCite XML:   api/schemas/fair3r-datacite-doi-example.xml
 */
final class Fair3rFdfMapper
{
    private const string DATACITE_XMLNS = 'http://datacite.org/schema/kernel-4';
    private const string DATACITE_SCHEMA_LOCATION = 'http://datacite.org/schema/kernel-4 https://schema.datacite.org/meta/kernel-4/metadata.xsd';
    private const string WIZARD_SCHEMA_URI = 'https://raw.githubusercontent.com/precliniverse/Dynamic_Metadata_form/refs/heads/main/schema.json';

    /** Maps Metadatapp Species enum values to NCBITaxon identifiers. */
    private const array SPECIES_NCBI_MAP = [
        'mouse' => ['label' => 'Mus musculus', 'id' => 'http://purl.obolibrary.org/obo/NCBITaxon_10090', 'taxon_id' => '10090'],
        'rat' => ['label' => 'Rattus norvegicus', 'id' => 'http://purl.obolibrary.org/obo/NCBITaxon_10116', 'taxon_id' => '10116'],
        'zebrafish' => ['label' => 'Danio rerio', 'id' => 'http://purl.obolibrary.org/obo/NCBITaxon_7955', 'taxon_id' => '7955'],
    ];

    public function __construct(
        private readonly FairAssessmentService $assessmentService,
    ) {
    }

    // ------------------------------------------------------------------
    // Experiment (Study) → DataCite JSON
    // ------------------------------------------------------------------

    /**
     * Build a DataCite 4.x JSON document for an Experiment (Study), aligned with
     * the FAIR3R Open Science Dataset Registration Wizard output format.
     *
     * @return array<string, mixed>
     */
    public function experimentToFdf(Experiment $experiment, string $baseUrl): array
    {
        $assessment = $this->assessmentService->assessExperiment($experiment);
        $user = $experiment->getUser();
        $year = (int) $experiment->getStartAt()->format('Y');
        $doi = $experiment->getFair3rExternalId() ?? \sprintf('10.0000/%s', $experiment->getId()?->toRfc4122());
        $landingUrl = $experiment->getRepositoryLink() ?? \sprintf('%s/studies/%s', rtrim($baseUrl, '/'), $experiment->getId()?->toRfc4122());

        return array_filter([
            '$schema' => self::WIZARD_SCHEMA_URI,
            'identifier' => $doi,
            'titles' => [['title' => $experiment->getName()]],
            'publicationYear' => $year,
            'publisher' => 'FAIR3R / Metadatapp',
            'types' => [['resourceType' => 'Dataset', 'resourceTypeGeneral' => 'Dataset']],
            'creators' => [$this->buildCreator($user)],
            'descriptions' => $this->buildDescriptions($experiment->getDescription() ?? $experiment->getGoal()),
            'subjects' => array_merge(
                $this->buildSpeciesSubjectsFromExperiment($experiment),
                $this->buildFairScoreSubject($assessment['score']['total']),
            ),
            'rightsList' => [$this->defaultRights()],
            'alternateIdentifiers' => [['alternateIdentifier' => $landingUrl, 'alternateIdentifierType' => 'URL']],
            // Metadatapp extensions
            'metadatapp_id' => $experiment->getId()?->toRfc4122(),
            'metadatapp_type' => 'study',
            'fair_score' => $assessment['score'],
            'fair_criteria' => $this->buildFairExtras($assessment['criteria']),
        ], static fn (mixed $v): bool => null !== $v && [] !== $v);
    }

    // ------------------------------------------------------------------
    // Project (Investigation) → DataCite JSON
    // ------------------------------------------------------------------

    /**
     * Build a DataCite 4.x JSON document for a Project (Investigation).
     *
     * @return array<string, mixed>
     */
    public function projectToFdf(Project $project, string $baseUrl): array
    {
        $assessment = $this->assessmentService->assessProject($project);
        $user = $project->getUser();
        $year = (int) ($project->getStartAt() ?? new \DateTimeImmutable())->format('Y');
        $doi = $project->getFair3rExternalId() ?? \sprintf('10.0000/%s', $project->getId()?->toRfc4122());
        $landingUrl = $project->getRepositoryLink() ?? \sprintf('%s/investigations/%s', rtrim($baseUrl, '/'), $project->getId()?->toRfc4122());

        // Collect unique species from all child experiments
        $speciesSubjects = [];
        foreach ($project->getExperiments() as $experiment) {
            foreach ($this->buildSpeciesSubjectsFromExperiment($experiment) as $subject) {
                $key = $subject['valueURI'];
                $speciesSubjects[$key] = $subject;
            }
        }

        // Related identifiers for child studies
        $relatedIdentifiers = array_values(array_map(
            static fn (Experiment $e): array => [
                'relatedIdentifier' => \sprintf('%s/studies/%s', rtrim($baseUrl, '/'), $e->getId()?->toRfc4122()),
                'relatedIdentifierType' => 'URL',
                'relationType' => 'HasPart',
            ],
            $project->getExperiments()->toArray(),
        ));

        return array_filter([
            '$schema' => self::WIZARD_SCHEMA_URI,
            'identifier' => $doi,
            'titles' => [['title' => $project->getName()]],
            'publicationYear' => $year,
            'publisher' => 'FAIR3R / Metadatapp',
            'types' => [['resourceType' => 'Collection', 'resourceTypeGeneral' => 'Collection']],
            'creators' => [$this->buildCreator($user)],
            'descriptions' => $this->buildDescriptions($project->getGoal() ?? $project->getDescription()),
            'subjects' => array_merge(
                array_values($speciesSubjects),
                $this->buildFairScoreSubject($assessment['score']['total']),
            ),
            'rightsList' => [$this->defaultRights()],
            'alternateIdentifiers' => [['alternateIdentifier' => $landingUrl, 'alternateIdentifierType' => 'URL']],
            'relatedIdentifiers' => $relatedIdentifiers ?: null,
            // Metadatapp extensions
            'metadatapp_id' => $project->getId()?->toRfc4122(),
            'metadatapp_type' => 'investigation',
            'fair_score' => $assessment['score'],
            'fair_criteria' => $this->buildFairExtras($assessment['criteria']),
        ], static fn (mixed $v): bool => null !== $v && [] !== $v);
    }

    // ------------------------------------------------------------------
    // Experiment → DataCite XML
    // ------------------------------------------------------------------

    /**
     * Build a DataCite 4.x XML string for an Experiment (Study).
     */
    public function experimentToDataciteXml(Experiment $experiment, string $baseUrl): string
    {
        $user = $experiment->getUser();
        $doi = $experiment->getFair3rExternalId() ?? \sprintf('10.0000/%s', $experiment->getId()?->toRfc4122());
        $year = $experiment->getStartAt()->format('Y');
        $description = htmlspecialchars($experiment->getDescription() ?? $experiment->getGoal() ?? '', \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
        $title = htmlspecialchars($experiment->getName(), \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
        $url = htmlspecialchars(
            $experiment->getRepositoryLink() ?? \sprintf('%s/studies/%s', rtrim($baseUrl, '/'), $experiment->getId()?->toRfc4122()),
            \ENT_XML1 | \ENT_COMPAT,
            'UTF-8',
        );
        $assessment = $this->assessmentService->assessExperiment($experiment);
        $fairTotal = $assessment['score']['total'];
        $speciesSubjects = $this->buildSpeciesSubjectsFromExperiment($experiment);

        return $this->buildDataciteXml([
            'identifier' => htmlspecialchars($doi, \ENT_XML1 | \ENT_COMPAT, 'UTF-8'),
            'title' => $title,
            'creator' => $this->buildCreator($user),
            'year' => $year,
            'resourceType' => 'Dataset',
            'description' => $description,
            'url' => $url,
            'fairTotal' => $fairTotal,
            'speciesSubjects' => $speciesSubjects,
        ]);
    }

    /**
     * Build a DataCite 4.x XML string for a Project (Investigation).
     */
    public function projectToDataciteXml(Project $project, string $baseUrl): string
    {
        $user = $project->getUser();
        $doi = $project->getFair3rExternalId() ?? \sprintf('10.0000/%s', $project->getId()?->toRfc4122());
        $year = ($project->getStartAt() ?? new \DateTimeImmutable())->format('Y');
        $description = htmlspecialchars($project->getGoal() ?? $project->getDescription() ?? '', \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
        $title = htmlspecialchars($project->getName(), \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
        $url = htmlspecialchars(
            $project->getRepositoryLink() ?? \sprintf('%s/investigations/%s', rtrim($baseUrl, '/'), $project->getId()?->toRfc4122()),
            \ENT_XML1 | \ENT_COMPAT,
            'UTF-8',
        );
        $assessment = $this->assessmentService->assessProject($project);
        $fairTotal = $assessment['score']['total'];

        // Collect unique species across all child experiments
        $speciesSubjects = [];
        foreach ($project->getExperiments() as $experiment) {
            foreach ($this->buildSpeciesSubjectsFromExperiment($experiment) as $subject) {
                $key = $subject['valueURI'];
                $speciesSubjects[$key] = $subject;
            }
        }

        return $this->buildDataciteXml([
            'identifier' => htmlspecialchars($doi, \ENT_XML1 | \ENT_COMPAT, 'UTF-8'),
            'title' => $title,
            'creator' => $this->buildCreator($user),
            'year' => $year,
            'resourceType' => 'Collection',
            'description' => $description,
            'url' => $url,
            'fairTotal' => $fairTotal,
            'speciesSubjects' => array_values($speciesSubjects),
        ]);
    }

    // ------------------------------------------------------------------
    // DataCite JSON validation
    // ------------------------------------------------------------------

    /**
     * Validate an incoming DataCite JSON payload against the FAIR3R schema.
     * Performs semantic/web-standards checks beyond basic structural validation.
     * Returns an array of validation error messages (empty = valid).
     *
     * @param array<string, mixed> $payload
     *
     * @return string[]
     */
    public function validateFdf(array $payload): array
    {
        $errors = [];

        // Required top-level fields (DataCite 4.x mandatory)
        if (empty($payload['titles']) || !\is_array($payload['titles'])) {
            $errors[] = 'Required field "titles" is missing or not an array.';
        } elseif (empty($payload['titles'][0]['title'])) {
            $errors[] = 'titles[0].title must be a non-empty string.';
        }

        if (!isset($payload['publicationYear'])) {
            $errors[] = 'Required field "publicationYear" is missing.';
        } else {
            $year = $payload['publicationYear'];
            if ((!\is_int($year) && !\is_float($year)) || $year < 2000 || $year > 2100 || $year !== floor($year)) {
                $errors[] = 'Field "publicationYear" must be an integer between 2000 and 2100.';
            }
        }

        if (empty($payload['creators']) || !\is_array($payload['creators'])) {
            $errors[] = 'Required field "creators" is missing or not an array.';
        } else {
            foreach ($payload['creators'] as $i => $creator) {
                // Must have at least one of: givenName+familyName or name
                $hasPersonal = !empty($creator['givenName']) || !empty($creator['familyName']);
                $hasOrg = !empty($creator['name']);
                if (!$hasPersonal && !$hasOrg) {
                    $errors[] = \sprintf('creators[%d] must have "givenName"/"familyName" (personal) or "name" (organizational).', $i);
                }

                // Validate ORCID URI format if present
                foreach ((array) ($creator['nameIdentifiers'] ?? []) as $j => $ni) {
                    if (!empty($ni['nameIdentifier']) && 'ORCID' === ($ni['nameIdentifierScheme'] ?? '')) {
                        if (!preg_match('#^https?://orcid\.org/\d{4}-\d{4}-\d{4}-\d{3}[\dX]$#', (string) $ni['nameIdentifier'])) {
                            $errors[] = \sprintf('creators[%d].nameIdentifiers[%d]: ORCID must be a full URI (https://orcid.org/XXXX-XXXX-XXXX-XXXX).', $i, $j);
                        }
                    }
                    if (!empty($ni['schemeURI']) && !filter_var($ni['schemeURI'], \FILTER_VALIDATE_URL)) {
                        $errors[] = \sprintf('creators[%d].nameIdentifiers[%d].schemeURI is not a valid URI.', $i, $j);
                    }
                }

                // Validate ROR URI format if present
                foreach ((array) ($creator['affiliation'] ?? []) as $j => $aff) {
                    if (!empty($aff['affiliationIdentifier']) && 'ROR' === ($aff['affiliationIdentifierScheme'] ?? '')) {
                        if (!preg_match('#^https?://ror\.org/#', (string) $aff['affiliationIdentifier'])) {
                            $errors[] = \sprintf('creators[%d].affiliation[%d]: ROR identifier must be a full URI (https://ror.org/…).', $i, $j);
                        }
                    }
                }
            }
        }

        if (empty($payload['types']) || !\is_array($payload['types'])) {
            $errors[] = 'Required field "types" is missing or not an array.';
        }

        // Validate subjects: each must have a non-empty "subject" string
        foreach ((array) ($payload['subjects'] ?? []) as $i => $subject) {
            if (empty($subject['subject'])) {
                $errors[] = \sprintf('subjects[%d] is missing required field "subject".', $i);
            }

            if (!empty($subject['valueURI']) && !filter_var($subject['valueURI'], \FILTER_VALIDATE_URL)) {
                $errors[] = \sprintf('subjects[%d].valueURI is not a valid URI: "%s".', $i, $subject['valueURI']);
            }
        }

        // Validate rightsList URIs
        foreach ((array) ($payload['rightsList'] ?? []) as $i => $rights) {
            if (!empty($rights['rightsURI']) && !filter_var($rights['rightsURI'], \FILTER_VALIDATE_URL)) {
                $errors[] = \sprintf('rightsList[%d].rightsURI is not a valid URI.', $i);
            }
        }

        // Validate alternateIdentifiers (landing page URLs)
        foreach ((array) ($payload['alternateIdentifiers'] ?? []) as $i => $alt) {
            if (!empty($alt['alternateIdentifier']) && !filter_var($alt['alternateIdentifier'], \FILTER_VALIDATE_URL)) {
                $errors[] = \sprintf('alternateIdentifiers[%d].alternateIdentifier is not a valid URL.', $i);
            }
        }

        // Validate $schema URI if present
        if (isset($payload['$schema']) && \is_string($payload['$schema']) && !filter_var($payload['$schema'], \FILTER_VALIDATE_URL)) {
            $errors[] = 'Field "$schema" must be a valid URI when provided as a string.';
        }

        return $errors;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Build a DataCite creator object from a User entity.
     *
     * @return array<string, mixed>
     */
    private function buildCreator(?object $user): array
    {
        if (null === $user) {
            return ['name' => 'Unknown', 'nameType' => 'Organizational'];
        }

        $firstName = method_exists($user, 'getFirstName') ? ($user->getFirstName() ?? '') : '';
        $lastName = method_exists($user, 'getLastName') ? ($user->getLastName() ?? '') : '';
        $orcid = method_exists($user, 'getOrcid') ? $user->getOrcid() : null;

        $creator = [
            'givenName' => $firstName ?: null,
            'familyName' => $lastName ?: null,
            'nameType' => 'Personal',
        ];

        if ($orcid) {
            $orcidUri = str_starts_with($orcid, 'https://') ? $orcid : \sprintf('https://orcid.org/%s', $orcid);
            $creator['nameIdentifiers'] = [[
                'nameIdentifier' => $orcidUri,
                'nameIdentifierScheme' => 'ORCID',
                'schemeURI' => 'https://orcid.org/',
            ]];
        }

        return array_filter($creator, static fn (mixed $v): bool => null !== $v);
    }

    /**
     * Build NCBITaxon subjects from the species found in an Experiment's subjects.
     *
     * @return list<array{subject: string, subjectScheme: string, valueURI: string, schemeURI: string}>
     */
    private function buildSpeciesSubjectsFromExperiment(Experiment $experiment): array
    {
        $seen = [];
        $subjects = [];

        foreach ($experiment->getProcedures() as $procedure) {
            foreach ($procedure->getSubjects() as $animalSubject) {
                $speciesValue = $animalSubject->getSpecies()->value;

                if (isset($seen[$speciesValue])) {
                    continue;
                }
                $seen[$speciesValue] = true;

                $ncbi = self::SPECIES_NCBI_MAP[$speciesValue];
                $subjects[] = [
                    'subject' => $ncbi['label'],
                    'subjectScheme' => 'NCBITaxon',
                    'valueURI' => $ncbi['id'],
                    'schemeURI' => 'http://purl.obolibrary.org/obo/',
                ];
            }
        }

        return $subjects;
    }

    /**
     * Build a FAIR3R FAIR score subject entry.
     *
     * @return list<array{subject: string, subjectScheme: string, valueURI: string, schemeURI: string}>
     */
    private function buildFairScoreSubject(int $fairTotal): array
    {
        return [[
            'subject' => \sprintf('FAIR score: %d/100', $fairTotal),
            'subjectScheme' => 'FAIR3R',
            'valueURI' => 'https://fair3r.fr/score',
            'schemeURI' => 'https://fair3r.fr',
        ]];
    }

    /**
     * Build DataCite descriptions array from a nullable text.
     *
     * @return list<array{description: string, descriptionType: string}>
     */
    private function buildDescriptions(?string $text): array
    {
        if (null === $text || '' === $text) {
            return [];
        }

        return [['description' => $text, 'descriptionType' => 'Abstract']];
    }

    /**
     * @return array{rights: string, rightsURI: string, rightsIdentifier: string, rightsIdentifierScheme: string, schemeURI: string}
     */
    private function defaultRights(): array
    {
        return [
            'rights' => 'Creative Commons Attribution 4.0 International',
            'rightsURI' => 'https://creativecommons.org/licenses/by/4.0/',
            'rightsIdentifier' => 'CC-BY-4.0',
            'rightsIdentifierScheme' => 'SPDX',
            'schemeURI' => 'https://spdx.org/licenses/',
        ];
    }

    /**
     * @param array<string, list<array{id: string, pass: bool, note: string, label: string, description: string}>> $criteria
     *
     * @return list<array{key: string, value: string}>
     */
    private function buildFairExtras(array $criteria): array
    {
        $extras = [];
        foreach ($criteria as $pillar => $items) {
            foreach ($items as $item) {
                $extras[] = [
                    'key' => \sprintf('fair_%s', strtolower($item['id'])),
                    'value' => $item['pass'] ? 'pass' : 'fail',
                ];
            }
        }

        return $extras;
    }

    /**
     * Build a DataCite 4.x XML string.
     *
     * @param array{identifier: string, title: string, creator: array<string, mixed>, year: string, resourceType: string, description: string, url: string, fairTotal: int, speciesSubjects: list<array<string, string>>} $params
     */
    private function buildDataciteXml(array $params): string
    {
        $xmlns = self::DATACITE_XMLNS;
        $schemaLocation = self::DATACITE_SCHEMA_LOCATION;
        $creator = $params['creator'];

        // Build creator name (FamilyName, GivenName or fallback)
        if (!empty($creator['familyName']) && !empty($creator['givenName'])) {
            $creatorNameXml = htmlspecialchars(\sprintf('%s, %s', $creator['familyName'], $creator['givenName']), \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
        } else {
            $creatorNameXml = htmlspecialchars($creator['name'] ?? 'Unknown', \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
        }

        // ORCID block (optional)
        $orcidXml = '';
        foreach ($creator['nameIdentifiers'] ?? [] as $ni) {
            if ('ORCID' === ($ni['nameIdentifierScheme'] ?? '')) {
                $niXml = htmlspecialchars($ni['nameIdentifier'], \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
                $orcidXml = "\n      <nameIdentifier nameIdentifierScheme=\"ORCID\" schemeURI=\"https://orcid.org/\">{$niXml}</nameIdentifier>";
            }
        }

        // Species subjects block
        $speciesXml = '';
        foreach ($params['speciesSubjects'] as $sp) {
            $subj = htmlspecialchars($sp['subject'], \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
            $valueUri = htmlspecialchars($sp['valueURI'] ?? '', \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
            $schemeUri = htmlspecialchars($sp['schemeURI'] ?? '', \ENT_XML1 | \ENT_COMPAT, 'UTF-8');
            $speciesXml .= "\n    <subject subjectScheme=\"NCBITaxon\" valueURI=\"{$valueUri}\" schemeURI=\"{$schemeUri}\">{$subj}</subject>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<resource xmlns="{$xmlns}"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="{$schemaLocation}">

  <identifier identifierType="DOI">{$params['identifier']}</identifier>

  <creators>
    <creator>
      <creatorName nameType="Personal">{$creatorNameXml}</creatorName>{$orcidXml}
    </creator>
  </creators>

  <titles>
    <title>{$params['title']}</title>
  </titles>

  <publisher>FAIR3R / Metadatapp</publisher>
  <publicationYear>{$params['year']}</publicationYear>

  <resourceType resourceTypeGeneral="Dataset">{$params['resourceType']}</resourceType>

  <descriptions>
    <description descriptionType="Abstract">{$params['description']}</description>
  </descriptions>

  <alternateIdentifiers>
    <alternateIdentifier alternateIdentifierType="URL">{$params['url']}</alternateIdentifier>
  </alternateIdentifiers>

  <rightsList>
    <rights rightsURI="https://creativecommons.org/licenses/by/4.0/"
            rightsIdentifier="CC-BY-4.0"
            rightsIdentifierScheme="SPDX"
            schemeURI="https://spdx.org/licenses/">
      Creative Commons Attribution 4.0 International (CC BY 4.0)
    </rights>
  </rightsList>

  <subjects>{$speciesXml}
    <subject subjectScheme="FAIR3R" schemeURI="https://fair3r.fr" valueURI="https://fair3r.fr/score">FAIR score: {$params['fairTotal']}/100</subject>
  </subjects>

</resource>
XML;
    }
}
