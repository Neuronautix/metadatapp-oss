<?php

declare(strict_types=1);

namespace App\Service\RoCrate;

use App\Entity\Behavior;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\Subject;
use App\Entity\TimeSeries;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class RoCrateExporter
{
    private const string ARTIFACT_DIR = 'artifact';
    private const int JSON_DEPTH = 512;

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function stream(Project $investigation): StreamedResponse
    {
        $tmpRoot = $this->createTempWorkspace();
        $artifact = $tmpRoot . '/' . self::ARTIFACT_DIR;
        $filesystem = new Filesystem();
        $filesystem->mkdir([
            $artifact,
            $artifact . '/metadata/subjects',
            $artifact . '/data',
            $artifact . '/media',
            $artifact . '/code',
        ]);

        $experiments = $this->experimentsForInvestigation($investigation);
        foreach ($experiments as $experiment) {
            $this->writeJson($artifact . "/metadata/experiment-{$experiment->getId()}.jsonld", $this->experimentPayload($experiment));

            foreach ($this->subjectsForExperiment($experiment) as $subject) {
                $this->writeJson(
                    $artifact . "/metadata/subjects/subject-{$subject->getId()}.jsonld",
                    $this->subjectPayload($subject)
                );
            }

            foreach ($this->timeSeriesForExperiment($experiment) as $timeSeries) {
                $tsId = (string) $timeSeries->getId();
                $this->writeJson(
                    $artifact . "/data/timeseries-{$tsId}.jsonld",
                    $this->timeSeriesPayload($timeSeries)
                );
            }

            foreach ($this->behaviorsForExperiment($experiment) as $behavior) {
                $behaviorId = (string) $behavior->getId();
                $this->writeJson(
                    $artifact . "/media/behavior-{$behaviorId}.jsonld",
                    $this->behaviorPayload($behavior)
                );
            }

            if ($experiment->getRepositoryLink()) {
                $this->writeJson(
                    $artifact . "/code/analysis-{$experiment->getId()}.jsonld",
                    $this->codePayload($experiment)
                );
            }
        }

        $this->writeJson($artifact . '/ro-crate-metadata.json', $this->assembleMetadata($artifact, $investigation));

        $zipPath = $tmpRoot . '/ro-crate.zip';
        $this->zipDirectory($artifact, $zipPath);

        return new StreamedResponse(static function () use ($zipPath, $filesystem, $tmpRoot): void {
            try {
                $stream = fopen($zipPath, 'r');
                if (!\is_resource($stream)) {
                    throw new \RuntimeException('Unable to open RO-Crate archive.');
                }

                $output = fopen('php://output', 'w');
                if (!\is_resource($output)) {
                    fclose($stream);
                    throw new \RuntimeException('Failed to initialize RO-Crate download stream.');
                }

                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
            } finally {
                $filesystem->remove($tmpRoot);
            }
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="ro-crate.zip"',
        ]);
    }

    private function createTempWorkspace(): string
    {
        $base = $this->projectDir . '/var';
        $filesystem = new Filesystem();
        if (!$filesystem->exists($base)) {
            $filesystem->mkdir($base, 0755);
        }

        $path = $base . '/ro-crate-' . bin2hex(random_bytes(8));
        if (!$filesystem->exists($path)) {
            $filesystem->mkdir($path, 0755);
        }

        return $path;
    }

    /**
     * @return list<Experiment>
     */
    private function experimentsForInvestigation(Project $investigation): array
    {
        return $this->entityManager->getRepository(Experiment::class)
            ->createQueryBuilder('e')
            ->andWhere('e.project = :project')
            ->andWhere('e.account = :account')
            ->setParameter('project', $investigation)
            ->setParameter('account', $investigation->getAccount())
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<Subject>
     */
    private function subjectsForExperiment(Experiment $experiment): array
    {
        return $this->entityManager->getRepository(Subject::class)
            ->createQueryBuilder('s')
            ->distinct()
            ->join('s.procedures', 'p')
            ->andWhere('p.experiment = :experiment')
            ->andWhere('s.account = :account')
            ->setParameter('experiment', $experiment)
            ->setParameter('account', $experiment->getAccount())
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<TimeSeries>
     */
    private function timeSeriesForExperiment(Experiment $experiment): array
    {
        return $this->entityManager->getRepository(TimeSeries::class)
            ->createQueryBuilder('t')
            ->andWhere('t.experiment = :experiment')
            ->andWhere('t.account = :account')
            ->setParameter('experiment', $experiment)
            ->setParameter('account', $experiment->getAccount())
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<Behavior>
     */
    private function behaviorsForExperiment(Experiment $experiment): array
    {
        return $this->entityManager->getRepository(Behavior::class)
            ->createQueryBuilder('b')
            ->andWhere('b.experiment = :experiment')
            ->andWhere('b.account = :account')
            ->setParameter('experiment', $experiment)
            ->setParameter('account', $experiment->getAccount())
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if (false === $json) {
            throw new \RuntimeException('Failed to encode JSON payload for ' . $path);
        }

        if (false === file_put_contents($path, $json)) {
            throw new \RuntimeException('Failed to write JSON payload to ' . $path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function experimentPayload(Experiment $experiment): array
    {
        $payload = [
            '@id' => (string) $experiment->getId(),
            'name' => $experiment->getName(),
            'goal' => $experiment->getGoal(),
            'description' => $experiment->getDescription(),
            'startAt' => $experiment->getStartAt()->format(\DATE_ATOM),
            'repositoryLink' => $experiment->getRepositoryLink(),
            'project' => (string) $experiment->getProject()?->getId(),
            'user' => [
                'id' => (string) $experiment->getUser()->getId(),
                'name' => $experiment->getUser()->getName(),
                'email' => $experiment->getUser()->getEmail(),
            ],
        ];

        if (null !== $experiment->getEndAt()) {
            $payload['endAt'] = $experiment->getEndAt()->format(\DATE_ATOM);
        }

        $links = [];
        foreach ($experiment->getConnectedResourceLinks() as $link) {
            $links[] = array_filter([
                '@id' => $link->getUrl() ?? \sprintf('urn:%s:%s', $link->getProvider(), $link->getExternalId()),
                'provider' => $link->getProvider(),
                'externalId' => $link->getExternalId(),
                'resourceType' => $link->getResourceType(),
                'label' => $link->getLabel(),
                'url' => $link->getUrl(),
                'subjectExternalId' => $link->getSubjectExternalId(),
                'syncedAt' => $link->getSyncedAt()?->format(\DATE_ATOM),
            ], static fn (mixed $v) => null !== $v);
        }
        if ([] !== $links) {
            $payload['connectedResourceLinks'] = $links;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectPayload(Subject $subject): array
    {
        return [
            '@id' => (string) $subject->getId(),
            'name' => $subject->getName(),
            'species' => $subject->getSpecies()->name,
            'sex' => $subject->getSex()->name,
            'birthAt' => $subject->getBirthAt()->format(\DATE_ATOM),
            'weight' => $subject->getWeight(),
            'healthStatus' => $subject->getHealthStatus()->name,
            'externalId' => $subject->getExternalId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timeSeriesPayload(TimeSeries $timeSeries): array
    {
        $variable = $timeSeries->getVariable();
        $subject = $timeSeries->getSubject();

        return [
            '@id' => "data/timeseries-{$timeSeries->getId()}.jsonld",
            '@type' => 'File',
            'name' => "timeseries-{$timeSeries->getId()}.jsonld",
            'encodingFormat' => 'application/ld+json',
            'subject' => [
                'id' => (string) $subject->getId(),
                'name' => $subject->getName(),
            ],
            'recordedAt' => $timeSeries->getRecordedAt()->format(\DATE_ATOM),
            'value' => $timeSeries->getValue(),
            'unit' => $timeSeries->getUnit(),
            'variable' => [
                'id' => (string) $variable->getId(),
                'name' => $variable->getName(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function behaviorPayload(Behavior $behavior): array
    {
        $payload = [
            '@id' => "media/behavior-{$behavior->getId()}.jsonld",
            '@type' => 'File',
            'name' => "behavior-{$behavior->getId()}.jsonld",
            'encodingFormat' => 'application/ld+json',
            'apparatus' => $behavior->getApparatus(),
            'context' => $behavior->getContext(),
            'lighting' => $behavior->getLighting(),
            'procedureType' => $behavior->getProcedureType()->name,
        ];

        if ($behavior->getProtocolUri()) {
            $payload['url'] = $behavior->getProtocolUri();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function codePayload(Experiment $experiment): array
    {
        return [
            '@id' => "code/analysis-{$experiment->getId()}",
            '@type' => 'SoftwareSourceCode',
            'name' => "analysis-{$experiment->getId()}",
            'codeRepository' => $experiment->getRepositoryLink(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Builds ro-crate-metadata.json from the repository template located at templates/ro-crate-metadata.jsonld.tpl.
     */
    private function assembleMetadata(string $artifactRoot, Project $investigation): array
    {
        $templatePath = $this->projectDir . '/templates/ro-crate-metadata.jsonld.tpl';
        if (!is_file($templatePath)) {
            throw new \RuntimeException(\sprintf('RO-Crate template missing at %s', $templatePath));
        }

        $templateContents = file_get_contents($templatePath);
        if (false === $templateContents || '' === $templateContents) {
            throw new \RuntimeException(\sprintf('Failed to read RO-Crate metadata template from %s. Ensure the template file is present and readable.', $templatePath));
        }

        $template = json_decode($templateContents, true, self::JSON_DEPTH, \JSON_THROW_ON_ERROR);
        $graph = $template['@graph'] ?? [];

        $root = null;
        foreach ($graph as &$entry) {
            if (isset($entry['@id']) && './' === $entry['@id']) {
                $root = &$entry;
                break;
            }
        }
        unset($entry);

        if (null === $root) {
            $graph[] = ['@id' => './', '@type' => 'Dataset', 'hasPart' => []];
            $root = &$graph[array_key_last($graph)];
        }

        $root['name'] = $investigation->getName();
        $root['description'] = $investigation->getDescription() ?? $investigation->getGoal() ?? '';
        $root['identifier'] = (string) $investigation->getId();
        $root['dateCreated'] = $investigation->getStartAt()?->format(\DATE_ATOM) ?? (new \DateTimeImmutable())->format(\DATE_ATOM);
        $root['dateModified'] = $investigation->getEndAt()?->format(\DATE_ATOM) ?? (new \DateTimeImmutable())->format(\DATE_ATOM);

        $files = $this->collectFiles($artifactRoot);
        $hasPart = [];
        $existingIds = [];
        foreach ($graph as $entry) {
            if (isset($entry['@id'])) {
                $existingIds[] = $entry['@id'];
            }
        }

        foreach ($files as $file) {
            $rel = ltrim(str_replace($artifactRoot . '/', '', $file), '/');
            $hasPart[] = ['@id' => $rel];

            if (!\in_array($rel, $existingIds, true)) {
                $graph[] = [
                    '@id' => $rel,
                    '@type' => 'File',
                    'name' => basename($file),
                    'encodingFormat' => $this->mimeType($file),
                    'contentSize' => (string) filesize($file),
                    'sha256' => hash_file('sha256', $file),
                ];
                $existingIds[] = $rel;
            }
        }

        $root['hasPart'] = $hasPart;

        $metadata = $template;
        $metadata['@graph'] = $graph;

        return $metadata;
    }

    /**
     * @return list<string>
     */
    private function collectFiles(string $artifactRoot): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($artifactRoot, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function mimeType(string $path): string
    {
        $mime = mime_content_type($path);

        return false !== $mime ? $mime : 'application/octet-stream';
    }

    private function zipDirectory(string $source, string $destination): void
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new \RuntimeException('Cannot create RO-Crate archive.');
        }

        $files = $this->collectFiles($source);
        foreach ($files as $file) {
            $relativePath = ltrim(str_replace($source . '/', '', $file), '/');
            $zip->addFile($file, $relativePath);
        }

        $zip->close();
    }
}
