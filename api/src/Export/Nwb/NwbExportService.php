<?php

declare(strict_types=1);

namespace App\Export\Nwb;

use App\Entity\ConnectedResourceLink;
use App\Entity\Experiment;
use App\Entity\Procedure;
use Symfony\Component\Process\Process;

final class NwbExportService
{
    public function __construct(
        private readonly string $nwbToolsDir,
    ) {
    }

    public function exportExperiment(Experiment $experiment): string
    {
        $metadataPath = $this->temporaryPath('metadatapp-nwb-export-', '.json');
        $nwbPath = $this->temporaryPath('metadatapp-nwb-', '.nwb');

        file_put_contents($metadataPath, json_encode($this->buildPayload($experiment), \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));

        try {
            $process = new Process(['python3', $this->nwbToolsDir . '/export_nwb.py', $metadataPath, $nwbPath]);
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'NWB export failed.');
            }
        } finally {
            @unlink($metadataPath);
        }

        return $nwbPath;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Experiment $experiment): array
    {
        return [
            'identifier' => $experiment->getId()?->toRfc4122(),
            'name' => $experiment->getName(),
            'description' => $experiment->getDescription(),
            'goal' => $experiment->getGoal(),
            'protocol' => $experiment->getProtocol(),
            'repositoryLink' => $experiment->getRepositoryLink(),
            'externalId' => $experiment->getExternalId(),
            'startAt' => $experiment->getStartAt()->format(\DATE_ATOM),
            'endAt' => $experiment->getEndAt()?->format(\DATE_ATOM),
            'project' => null === $experiment->getProject() ? null : [
                'id' => $experiment->getProject()->getId()?->toRfc4122(),
                'name' => $experiment->getProject()->getName(),
                'externalId' => $experiment->getProject()->getExternalId(),
            ],
            'procedures' => array_map(
                static fn (Procedure $procedure): array => [
                    'id' => $procedure->getId()?->toRfc4122(),
                    'name' => $procedure->getName(),
                    'description' => $procedure->getDescription(),
                ],
                $experiment->getProcedures()->toArray()
            ),
            'connectedResources' => array_map(
                static fn (ConnectedResourceLink $link): array => [
                    'provider' => $link->getProvider(),
                    'externalId' => $link->getExternalId(),
                    'resourceType' => $link->getResourceType(),
                    'label' => $link->getLabel(),
                    'url' => $link->getUrl(),
                ],
                $experiment->getConnectedResourceLinks()->toArray()
            ),
        ];
    }

    private function temporaryPath(string $prefix, string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if (false === $path) {
            throw new \RuntimeException('Unable to allocate temporary file.');
        }

        $target = $path . $suffix;
        rename($path, $target);

        return $target;
    }
}
