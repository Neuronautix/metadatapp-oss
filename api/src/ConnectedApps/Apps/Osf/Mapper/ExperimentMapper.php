<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Osf\Mapper;

use App\Entity\ConnectedApp;
use App\Entity\ConnectedResourceLink;
use App\Entity\Experiment;
use App\Enum\ExperimentType;

final class ExperimentMapper
{
    /**
     * @param array<string, mixed> $node
     */
    public function mapNode(ConnectedApp $connectedApp, array $node, Experiment $experiment): Experiment
    {
        $attributes = \is_array($node['attributes'] ?? null) ? $node['attributes'] : [];
        $id = $this->stringOrNull($node['id'] ?? null);
        if (null === $id) {
            throw new \InvalidArgumentException('OSF node is missing an id.');
        }

        $title = $this->stringOrNull($attributes['title'] ?? null) ?? 'OSF project ' . $id;

        $experiment
            ->setExternalId('osf:' . $id)
            ->setName($title)
            ->setDescription($this->stringOrNull($attributes['description'] ?? null))
            ->setRepositoryLink($this->buildWebUrl($node, $id))
            ->setType(ExperimentType::TypeA)
            ->setAccount($connectedApp->getAccount())
            ->setUser($connectedApp->getUser())
            ->setStartAt($this->dateOrNow($attributes['date_created'] ?? null))
        ;

        $modifiedAt = $this->dateOrNull($attributes['date_modified'] ?? null);
        if (null !== $modifiedAt) {
            $experiment->setEndAt($modifiedAt);
        }

        return $experiment;
    }

    /**
     * @param array<string, mixed> $node
     */
    public function mapResourceLink(ConnectedApp $connectedApp, array $node, Experiment $experiment, ConnectedResourceLink $link): ConnectedResourceLink
    {
        $attributes = \is_array($node['attributes'] ?? null) ? $node['attributes'] : [];
        $id = $this->stringOrNull($node['id'] ?? null);
        if (null === $id) {
            throw new \InvalidArgumentException('OSF node is missing an id.');
        }

        return $link
            ->setProvider('osf')
            ->setExternalId('osf:' . $id)
            ->setResourceType('project')
            ->setLabel($this->stringOrNull($attributes['title'] ?? null))
            ->setUrl($this->buildWebUrl($node, $id))
            ->setExperiment($experiment)
            ->setAccount($connectedApp->getAccount())
            ->setSyncedAt(new \DateTimeImmutable())
        ;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function buildWebUrl(array $node, string $id): string
    {
        $links = \is_array($node['links'] ?? null) ? $node['links'] : [];
        $html = $this->stringOrNull($links['html'] ?? null);

        return $html ?? \sprintf('https://osf.io/%s/', $id);
    }

    private function dateOrNow(mixed $value): \DateTimeInterface
    {
        return $this->dateOrNull($value) ?? new \DateTimeImmutable();
    }

    private function dateOrNull(mixed $value): ?\DateTimeInterface
    {
        if (!\is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_scalar($value) && '' !== trim((string) $value) ? trim((string) $value) : null;
    }
}
