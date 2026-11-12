<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\PreclinicalTrials;

use App\ConnectedApps\Apps\PreclinicalTrials\Client\PreclinicalTrialsClientInterface;
use App\ConnectedApps\Apps\PreclinicalTrials\Mapper\ProtocolMapper;
use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;
use App\Entity\ConnectedApp;
use App\Entity\ConnectedResourceLink;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Enum\AppCode;
use App\Repository\ConnectedResourceLinkRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class PreclinicalTrialsService implements ConnectedAppServiceInterface, ConnectedAppFromInterface, PreclinicalTrialsServiceInterface
{
    private const array SUPPORTED_ENTITIES = [
        Project::class,
    ];

    public function __construct(
        private readonly PreclinicalTrialsClientInterface $client,
        private readonly ProtocolMapper $mapper,
        private readonly ProjectRepository $projectRepository,
        private readonly ConnectedResourceLinkRepository $connectedResourceLinkRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supportsCode(AppCode $appCode): bool
    {
        return AppCode::PreclinicalTrials === $appCode;
    }

    public static function getSupportedEntitiesSyncFrom(): array
    {
        return self::SUPPORTED_ENTITIES;
    }

    public function supportsEntitySyncTo(string $entityClassname): bool
    {
        return false;
    }

    public function supportsEntitySyncFrom(string $entityClassname): bool
    {
        return \in_array($entityClassname, self::SUPPORTED_ENTITIES, true);
    }

    public function syncAllFromExternal(ConnectedApp $connectedApp, string $entityClassname): ?array
    {
        if (!$this->supportsEntitySyncFrom($entityClassname)) {
            throw new \InvalidArgumentException(\sprintf('Entity class %s is not supported for PreclinicalTrials.eu synchronization.', $entityClassname));
        }

        $synced = [];
        foreach ($this->client->listProtocols($connectedApp) as $protocol) {
            $protocolId = $this->mapper->extractProtocolId($protocol);
            if (null === $protocolId) {
                $this->logger->warning('Skipping PreclinicalTrials.eu protocol without id.', [
                    'connectedAppId' => $connectedApp->getId()?->toRfc4122(),
                ]);
                continue;
            }

            try {
                $details = $this->client->getProtocol($connectedApp, $protocolId);
            } catch (\Throwable) {
                $details = [];
            }
            $source = [] === $details ? $protocol : array_replace($protocol, $details);
            $project = $this->projectRepository->findOneBy([
                'account' => $connectedApp->getAccount(),
                'externalId' => 'preclinicaltrials:' . $protocolId,
            ]) ?? new Project();

            $this->mapper->mapToProject($connectedApp, $source, $project);
            $this->em->persist($project);
            $synced[] = $project;
        }

        $connectedApp->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return [] === $synced ? null : $synced;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProtocolSummaries(ConnectedApp $connectedApp): array
    {
        $summaries = [];
        foreach ($this->client->listProtocols($connectedApp) as $protocol) {
            $summary = $this->mapper->summarize($protocol);
            $protocolId = \is_string($summary['id'] ?? null) ? $summary['id'] : null;
            if (null === $protocolId) {
                continue;
            }

            $externalId = 'preclinicaltrials:' . $protocolId;
            $linkedProject = $this->projectRepository->findOneBy([
                'account' => $connectedApp->getAccount(),
                'externalId' => $externalId,
            ]);
            $linkedStudy = $this->connectedResourceLinkRepository->findOneByProviderAndExternalId(
                $connectedApp->getAccount(),
                'preclinicaltrials',
                $externalId,
            )?->getExperiment();

            $summary['linkedInvestigation'] = null === $linkedProject ? null : [
                'id' => $linkedProject->getId()?->toRfc4122(),
                'name' => $linkedProject->getName(),
            ];
            $summary['linkedStudy'] = null === $linkedStudy ? null : [
                'id' => $linkedStudy->getId()?->toRfc4122(),
                'name' => $linkedStudy->getName(),
            ];
            $summaries[] = $summary;
        }

        usort($summaries, static fn (array $left, array $right): int => strcmp((string) ($right['registrationDate'] ?? ''), (string) ($left['registrationDate'] ?? '')));

        return $summaries;
    }

    /**
     * @return array<string, mixed>
     */
    public function importSelectedProtocol(ConnectedApp $connectedApp, string $protocolId, ?Uuid $targetInvestigationId = null): array
    {
        $protocol = $this->findProtocol($connectedApp, $protocolId);
        $externalId = 'preclinicaltrials:' . $protocolId;
        $project = null === $targetInvestigationId
            ? $this->projectRepository->findOneBy([
                'account' => $connectedApp->getAccount(),
                'externalId' => $externalId,
            ])
            : $this->projectRepository->find($targetInvestigationId);

        if (null !== $project && $project->getAccount()->getId()?->toRfc4122() !== $connectedApp->getAccount()->getId()?->toRfc4122()) {
            throw new \RuntimeException('Target investigation does not belong to the connected app account.');
        }

        $project ??= new Project();
        $this->mapper->mapToProject($connectedApp, $protocol, $project);
        $this->em->persist($project);
        $connectedApp->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return [
            'type' => 'investigation',
            'id' => $project->getId()?->toRfc4122(),
            'name' => $project->getName(),
            'externalId' => $project->getExternalId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function linkSelectedProtocolToStudy(ConnectedApp $connectedApp, string $protocolId, Uuid $targetStudyId): array
    {
        $protocol = $this->findProtocol($connectedApp, $protocolId);
        $summary = $this->mapper->summarize($protocol);
        $externalId = 'preclinicaltrials:' . $protocolId;
        $study = $this->em->getRepository(Experiment::class)->find($targetStudyId);
        if (!$study instanceof Experiment) {
            throw new \RuntimeException('Target study was not found.');
        }
        if ($study->getAccount()->getId()?->toRfc4122() !== $connectedApp->getAccount()->getId()?->toRfc4122()) {
            throw new \RuntimeException('Target study does not belong to the connected app account.');
        }

        $link = $this->connectedResourceLinkRepository->findOneBy([
            'account' => $connectedApp->getAccount(),
            'provider' => 'preclinicaltrials',
            'externalId' => $externalId,
            'experiment' => $study,
        ]) ?? new ConnectedResourceLink();
        $link
            ->setProvider('preclinicaltrials')
            ->setExternalId($externalId)
            ->setResourceType('protocol')
            ->setLabel(\is_string($summary['title'] ?? null) ? $summary['title'] : $protocolId)
            ->setUrl(\is_string($summary['repositoryLink'] ?? null) ? $summary['repositoryLink'] : null)
            ->setSyncedAt(new \DateTimeImmutable())
            ->setExperiment($study)
            ->setAccount($connectedApp->getAccount())
            ->touch()
        ;

        $this->em->persist($link);
        $connectedApp->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return [
            'type' => 'study',
            'id' => $study->getId()?->toRfc4122(),
            'name' => $study->getName(),
            'externalId' => $externalId,
            'linkId' => $link->getId()?->toRfc4122(),
        ];
    }

    public function syncOneFromExternal(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void
    {
        $this->syncAllFromExternal($connectedApp, $entityClassname);
    }

    public function fullSynchronizationFromExternal(ConnectedApp $connectedApp): void
    {
        foreach (self::SUPPORTED_ENTITIES as $entityClassname) {
            $this->syncAllFromExternal($connectedApp, $entityClassname);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function findProtocol(ConnectedApp $connectedApp, string $protocolId): array
    {
        foreach ($this->client->listProtocols($connectedApp) as $protocol) {
            if ($protocolId === $this->mapper->extractProtocolId($protocol)) {
                return $protocol;
            }
        }

        $protocol = $this->client->getProtocol($connectedApp, $protocolId);
        if ([] === $protocol) {
            throw new \RuntimeException(\sprintf('PreclinicalTrials.eu protocol "%s" was not found.', $protocolId));
        }

        return $protocol;
    }
}
