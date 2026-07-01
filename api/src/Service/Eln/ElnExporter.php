<?php

declare(strict_types=1);

namespace App\Service\Eln;

use App\Entity\Experiment;
use App\Entity\Project;
use App\Service\Fair3rFdfMapper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds a spec-compliant ".eln" file (The ELN Consortium / ELN File Format)
 * from the FAIR3R DataCite 4.x exchange JSON produced by {@see Fair3rFdfMapper}.
 *
 * The ".eln" format is an RO-Crate 1.1 archive: a single ZIP whose root folder
 * matches the archive name and contains a `ro-crate-metadata.json` describing
 * the bundled content (https://github.com/TheELNConsortium/TheELNFileFormat).
 *
 * Mapping (ISA → RO-Crate):
 *   - Investigation (Project)    → root Dataset "./"
 *   - Study (Experiment)         → entry Dataset "studies/<id>/"
 *   - FAIR3R DataCite JSON       → bundled File payload + schema.org properties
 *       titles                   → name
 *       descriptions[Abstract]   → text / description
 *       creators (+ORCID)        → author → Person nodes (ORCID @id)
 *       subjects (NCBITaxon, …)  → keywords + about → DefinedTerm nodes
 *       rightsList               → license → CreativeWork node
 *       publisher                → publisher → Organization node
 *       publicationYear          → datePublished
 *       identifier (DOI)         → identifier
 *       fair_score               → additionalProperty → PropertyValue nodes
 */
final readonly class ElnExporter
{
    private const string RO_CRATE_CONTEXT = 'https://w3id.org/ro/crate/1.1/context';
    private const string RO_CRATE_CONFORMS_TO = 'https://w3id.org/ro/crate/1.1';
    private const string ELN_CONFORMS_TO = 'https://github.com/TheELNConsortium/TheELNFileFormat';
    private const string PUBLISHER_ID = '#metadatapp-fair3r';
    private const string PUBLISHER_NAME = 'FAIR3R / Metadatapp';

    public function __construct(
        private Fair3rFdfMapper $fdfMapper,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * Stream the investigation as a downloadable ".eln" archive.
     */
    public function stream(Project $investigation, string $baseUrl): StreamedResponse
    {
        $slug = $this->slug($investigation->getId()?->toRfc4122() ?? bin2hex(random_bytes(8)));

        return $this->streamArchive($this->assemble($investigation, $baseUrl), 'metadatapp-investigation-' . $slug);
    }

    /**
     * Stream a single study as a downloadable ".eln" archive.
     */
    public function streamStudy(Experiment $study, string $baseUrl): StreamedResponse
    {
        $slug = $this->slug($study->getId()?->toRfc4122() ?? bin2hex(random_bytes(8)));

        return $this->streamArchive($this->assembleStudy($study, $baseUrl), 'metadatapp-study-' . $slug);
    }

    /**
     * @param array{payloads: array<string, string>, metadata: array<string, mixed>} $assembled
     */
    private function streamArchive(array $assembled, string $rootFolder): StreamedResponse
    {
        $filename = $rootFolder . '.eln';

        $tmpRoot = $this->createTempWorkspace();
        $crateRoot = $tmpRoot . '/' . $rootFolder;
        $filesystem = new Filesystem();
        $filesystem->mkdir($crateRoot);

        // Bundled DataCite JSON payloads referenced as File entities.
        foreach ($assembled['payloads'] as $relPath => $contents) {
            $absolute = $crateRoot . '/' . $relPath;
            $filesystem->mkdir(\dirname($absolute));
            if (false === file_put_contents($absolute, $contents)) {
                throw new \RuntimeException('Failed to write ELN payload to ' . $absolute);
            }
        }

        $metadataJson = json_encode(
            $assembled['metadata'],
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        );
        if (false === $metadataJson) {
            throw new \RuntimeException('Failed to encode ro-crate-metadata.json for ELN export.');
        }
        if (false === file_put_contents($crateRoot . '/ro-crate-metadata.json', $metadataJson)) {
            throw new \RuntimeException('Failed to write ro-crate-metadata.json for ELN export.');
        }

        $zipPath = $tmpRoot . '/' . $filename;
        $this->zipDirectory($crateRoot, $rootFolder, $zipPath);

        return new StreamedResponse(static function () use ($zipPath, $filesystem, $tmpRoot): void {
            try {
                $stream = fopen($zipPath, 'r');
                if (!\is_resource($stream)) {
                    throw new \RuntimeException('Unable to open ELN archive.');
                }

                $output = fopen('php://output', 'w');
                if (!\is_resource($output)) {
                    fclose($stream);
                    throw new \RuntimeException('Failed to initialize ELN download stream.');
                }

                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
            } finally {
                $filesystem->remove($tmpRoot);
            }
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => \sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    /**
     * Build the ro-crate-metadata.json graph without touching the filesystem.
     * Exposed for testing.
     *
     * @return array<string, mixed>
     */
    public function buildRoCrateMetadata(Project $investigation, string $baseUrl): array
    {
        return $this->assemble($investigation, $baseUrl)['metadata'];
    }

    /**
     * Build the study-level ro-crate-metadata.json graph without touching the
     * filesystem. Exposed for testing.
     *
     * @return array<string, mixed>
     */
    public function buildStudyRoCrateMetadata(Experiment $study, string $baseUrl): array
    {
        return $this->assembleStudy($study, $baseUrl)['metadata'];
    }

    /**
     * @return array{payloads: array<string, string>, metadata: array<string, mixed>}
     */
    private function assembleStudy(Experiment $study, string $baseUrl): array
    {
        $studyDoc = $this->fdfMapper->experimentToFdf($study, $baseUrl);

        $payloadPath = 'datacite/study.json';
        $payloads = [$payloadPath => $this->encodePayload($studyDoc)];

        /** @var array<string, array<string, mixed>> $sharedNodes keyed by @id for de-duplication */
        $sharedNodes = [];

        $rootDataset = $this->datasetNode(
            './',
            $studyDoc,
            $study->getStartAt()->format(\DATE_ATOM),
            $study->getEndAt()?->format(\DATE_ATOM),
            [['@id' => $payloadPath]],
            $sharedNodes,
        );

        $fileNodes = [
            $this->fileNode(
                $payloadPath,
                'study.json',
                'FAIR3R DataCite 4.x exchange document for this study.',
                $payloads[$payloadPath],
            ),
        ];

        $metadata = [
            '@context' => self::RO_CRATE_CONTEXT,
            '@graph' => array_merge(
                [
                    $this->metadataDescriptorNode(),
                    $rootDataset,
                    $this->publisherNode(),
                ],
                $fileNodes,
                array_values($sharedNodes),
            ),
        ];

        return ['payloads' => $payloads, 'metadata' => $metadata];
    }

    /**
     * @return array{payloads: array<string, string>, metadata: array<string, mixed>}
     */
    private function assemble(Project $investigation, string $baseUrl): array
    {
        $investigationDoc = $this->fdfMapper->projectToFdf($investigation, $baseUrl);

        $payloads = [];
        /** @var array<string, array<string, mixed>> $sharedNodes keyed by @id for de-duplication */
        $sharedNodes = [];

        // ----- Root Dataset (investigation) -----
        $investigationPayloadPath = 'datacite/investigation.json';
        $payloads[$investigationPayloadPath] = $this->encodePayload($investigationDoc);

        $rootHasPart = [['@id' => $investigationPayloadPath]];
        $studyNodes = [];
        $fileNodes = [
            $this->fileNode(
                $investigationPayloadPath,
                'investigation.json',
                'FAIR3R DataCite 4.x exchange document for this investigation.',
                $payloads[$investigationPayloadPath],
            ),
        ];

        // ----- Entry Datasets (studies) -----
        foreach ($investigation->getExperiments() as $experiment) {
            $studyId = $experiment->getId()?->toRfc4122();
            if (null === $studyId) {
                continue;
            }

            $studyDoc = $this->fdfMapper->experimentToFdf($experiment, $baseUrl);
            $datasetId = 'studies/' . $studyId . '/';
            $payloadPath = 'studies/' . $studyId . '/datacite.json';
            $payloads[$payloadPath] = $this->encodePayload($studyDoc);

            $fileNodes[] = $this->fileNode(
                $payloadPath,
                'datacite.json',
                'FAIR3R DataCite 4.x exchange document for this study.',
                $payloads[$payloadPath],
            );

            $studyNodes[] = $this->datasetNode(
                $datasetId,
                $studyDoc,
                $experiment->getStartAt()->format(\DATE_ATOM),
                $experiment->getEndAt()?->format(\DATE_ATOM),
                [['@id' => $payloadPath]],
                $sharedNodes,
            );

            $rootHasPart[] = ['@id' => $datasetId];
        }

        $rootDataset = $this->datasetNode(
            './',
            $investigationDoc,
            ($investigation->getStartAt() ?? new \DateTimeImmutable())->format(\DATE_ATOM),
            $investigation->getEndAt()?->format(\DATE_ATOM),
            $rootHasPart,
            $sharedNodes,
        );

        $metadata = [
            '@context' => self::RO_CRATE_CONTEXT,
            '@graph' => array_merge(
                [
                    $this->metadataDescriptorNode(),
                    $rootDataset,
                    $this->publisherNode(),
                ],
                $studyNodes,
                $fileNodes,
                array_values($sharedNodes),
            ),
        ];

        return ['payloads' => $payloads, 'metadata' => $metadata];
    }

    /**
     * The RO-Crate metadata file descriptor.
     *
     * @return array<string, mixed>
     */
    private function metadataDescriptorNode(): array
    {
        return [
            '@id' => 'ro-crate-metadata.json',
            '@type' => 'CreativeWork',
            'about' => ['@id' => './'],
            'conformsTo' => [
                ['@id' => self::RO_CRATE_CONFORMS_TO],
                ['@id' => self::ELN_CONFORMS_TO],
            ],
            'sdPublisher' => ['@id' => self::PUBLISHER_ID],
            'dateCreated' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publisherNode(): array
    {
        return [
            '@id' => self::PUBLISHER_ID,
            '@type' => 'Organization',
            'name' => self::PUBLISHER_NAME,
            'url' => 'https://fair3r.fr',
        ];
    }

    /**
     * Build a Dataset node from a FAIR3R DataCite document, registering shared
     * contextual entities (Person, DefinedTerm, license) into $sharedNodes.
     *
     * @param array<string, mixed>                $doc
     * @param list<array{'@id': string}>          $hasPart
     * @param array<string, array<string, mixed>> $sharedNodes
     *
     * @return array<string, mixed>
     */
    private function datasetNode(
        string $id,
        array $doc,
        string $dateCreated,
        ?string $dateModified,
        array $hasPart,
        array &$sharedNodes,
    ): array {
        $node = [
            '@id' => $id,
            '@type' => 'Dataset',
            'name' => $this->title($doc),
            'dateCreated' => $dateCreated,
        ];

        if (null !== $dateModified) {
            $node['dateModified'] = $dateModified;
        }

        $text = $this->description($doc);
        if (null !== $text) {
            $node['text'] = $text;
            $node['description'] = $text;
        }

        if (isset($doc['identifier']) && \is_string($doc['identifier'])) {
            $node['identifier'] = $doc['identifier'];
        }

        if (isset($doc['publicationYear'])) {
            $node['datePublished'] = (string) $doc['publicationYear'];
        }

        // Authors → Person nodes.
        $authorRefs = [];
        foreach ((array) ($doc['creators'] ?? []) as $creator) {
            if (!\is_array($creator)) {
                continue;
            }
            $person = $this->personNode($creator);
            $personId = (string) $person['@id'];
            $sharedNodes[$personId] = $person;
            $authorRefs[] = ['@id' => $personId];
        }
        if ([] !== $authorRefs) {
            $node['author'] = $authorRefs;
        }

        // Subjects → keywords (string) + about → DefinedTerm nodes.
        $keywords = [];
        $aboutRefs = [];
        foreach ((array) ($doc['subjects'] ?? []) as $subject) {
            if (!\is_array($subject) || empty($subject['subject'])) {
                continue;
            }
            $keywords[] = (string) $subject['subject'];

            if (!empty($subject['valueURI'])) {
                $term = $this->definedTermNode($subject);
                $termId = (string) $term['@id'];
                $sharedNodes[$termId] = $term;
                $aboutRefs[] = ['@id' => $termId];
            }
        }
        if ([] !== $keywords) {
            $node['keywords'] = implode(', ', $keywords);
        }
        if ([] !== $aboutRefs) {
            $node['about'] = $aboutRefs;
        }

        // License → CreativeWork node.
        $rights = (array) ($doc['rightsList'] ?? []);
        if (isset($rights[0]) && \is_array($rights[0]) && !empty($rights[0]['rightsURI'])) {
            $licenseId = (string) $rights[0]['rightsURI'];
            $sharedNodes[$licenseId] = [
                '@id' => $licenseId,
                '@type' => 'CreativeWork',
                'name' => (string) ($rights[0]['rights'] ?? $licenseId),
                'identifier' => (string) ($rights[0]['rightsIdentifier'] ?? $licenseId),
            ];
            $node['license'] = ['@id' => $licenseId];
        }

        $node['publisher'] = ['@id' => self::PUBLISHER_ID];

        // FAIR score → additionalProperty PropertyValue list.
        $properties = $this->fairProperties($doc);
        if ([] !== $properties) {
            $node['additionalProperty'] = $properties;
        }

        $node['hasPart'] = $hasPart;

        return $node;
    }

    /**
     * @param array<string, mixed> $creator
     *
     * @return array<string, mixed>
     */
    private function personNode(array $creator): array
    {
        $given = isset($creator['givenName']) ? (string) $creator['givenName'] : '';
        $family = isset($creator['familyName']) ? (string) $creator['familyName'] : '';
        $name = isset($creator['name']) ? (string) $creator['name'] : trim($family . ($family && $given ? ', ' : '') . $given);
        if ('' === $name) {
            $name = 'Unknown';
        }

        $id = null;
        foreach ((array) ($creator['nameIdentifiers'] ?? []) as $ni) {
            if (\is_array($ni) && 'ORCID' === ($ni['nameIdentifierScheme'] ?? '') && !empty($ni['nameIdentifier'])) {
                $id = (string) $ni['nameIdentifier'];
                break;
            }
        }
        $id ??= '#person-' . $this->slug($name);

        $node = [
            '@id' => $id,
            '@type' => 'Person',
            'name' => $name,
        ];
        if ('' !== $given) {
            $node['givenName'] = $given;
        }
        if ('' !== $family) {
            $node['familyName'] = $family;
        }
        if (str_starts_with($id, 'http')) {
            $node['identifier'] = $id;
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $subject
     *
     * @return array<string, mixed>
     */
    private function definedTermNode(array $subject): array
    {
        $node = [
            '@id' => (string) $subject['valueURI'],
            '@type' => 'DefinedTerm',
            'name' => (string) $subject['subject'],
        ];
        if (!empty($subject['subjectScheme'])) {
            $node['inDefinedTermSet'] = (string) $subject['subjectScheme'];
        }
        if (!empty($subject['schemeURI'])) {
            $node['url'] = (string) $subject['schemeURI'];
        }

        return $node;
    }

    /**
     * @param array<string, mixed> $doc
     *
     * @return list<array<string, mixed>>
     */
    private function fairProperties(array $doc): array
    {
        $score = $doc['fair_score'] ?? null;
        if (!\is_array($score)) {
            return [];
        }

        $labels = [
            'total' => 'FAIR score (total)',
            'findable' => 'FAIR score (findable)',
            'accessible' => 'FAIR score (accessible)',
            'interoperable' => 'FAIR score (interoperable)',
            'reusable' => 'FAIR score (reusable)',
        ];

        $properties = [];
        foreach ($labels as $key => $label) {
            if (!isset($score[$key])) {
                continue;
            }
            $properties[] = [
                '@type' => 'PropertyValue',
                'propertyID' => 'fair3r:' . $key,
                'name' => $label,
                'value' => $score[$key],
                'maxValue' => 100,
                'unitText' => 'percent',
            ];
        }

        return $properties;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function title(array $doc): string
    {
        $titles = (array) ($doc['titles'] ?? []);
        if (isset($titles[0]) && \is_array($titles[0]) && !empty($titles[0]['title'])) {
            return (string) $titles[0]['title'];
        }

        return 'Untitled';
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function description(array $doc): ?string
    {
        foreach ((array) ($doc['descriptions'] ?? []) as $description) {
            if (\is_array($description) && !empty($description['description'])) {
                return (string) $description['description'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function encodePayload(array $doc): string
    {
        $json = json_encode($doc, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if (false === $json) {
            throw new \RuntimeException('Failed to encode FAIR3R DataCite payload for ELN export.');
        }

        return $json;
    }

    /**
     * @return array<string, mixed>
     */
    private function fileNode(string $id, string $name, string $description, string $contents): array
    {
        return [
            '@id' => $id,
            '@type' => 'File',
            'name' => $name,
            'description' => $description,
            'encodingFormat' => 'application/json',
            'contentSize' => (string) \strlen($contents),
            'sha256' => hash('sha256', $contents),
        ];
    }

    private function slug(string $value): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value));

        return trim($slug, '-') ?: 'item';
    }

    private function createTempWorkspace(): string
    {
        $base = $this->projectDir . '/var';
        $filesystem = new Filesystem();
        if (!$filesystem->exists($base)) {
            $filesystem->mkdir($base, 0755);
        }

        $path = $base . '/eln-' . bin2hex(random_bytes(8));
        $filesystem->mkdir($path, 0755);

        return $path;
    }

    private function zipDirectory(string $source, string $rootFolder, string $destination): void
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new \RuntimeException('Cannot create ELN archive.');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getPathname();
            }
        }
        sort($files);

        foreach ($files as $file) {
            $relativePath = ltrim(str_replace($source, '', $file), '/');
            // ELN format: every entry lives under a single root folder.
            $zip->addFile($file, $rootFolder . '/' . $relativePath);
        }

        $zip->close();
    }
}
