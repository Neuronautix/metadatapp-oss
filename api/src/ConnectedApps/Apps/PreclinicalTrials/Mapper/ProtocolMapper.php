<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\PreclinicalTrials\Mapper;

use App\Entity\ConnectedApp;
use App\Entity\Project;

final class ProtocolMapper
{
    /**
     * @param array<string, mixed> $protocol
     */
    public function mapToProject(ConnectedApp $connectedApp, array $protocol, Project $project): Project
    {
        $id = $this->extractProtocolId($protocol);
        if (null === $id) {
            throw new \InvalidArgumentException('PreclinicalTrials.eu protocol is missing an identifier.');
        }

        $title = $this->firstString($protocol, ['short_title', 'scientific_title', 'public_title', 'title', 'name']) ?? 'PreclinicalTrials protocol ' . $id;
        $description = $this->firstString($protocol, ['description', 'summary', 'abstract']);

        return $project
            ->setExternalId('preclinicaltrials:' . $id)
            ->setName($title)
            ->setGoal($this->firstString($protocol, ['objective', 'primary_objective', 'goal']))
            ->setDescription($description)
            ->setRepositoryLink($this->buildRepositoryLink($protocol, $id))
            ->setStartAt($this->dateOrNull($this->firstScalar($protocol, ['start_date', 'date_start', 'created_at', 'registration_date'])))
            ->setEndAt($this->dateOrNull($this->firstScalar($protocol, ['end_date', 'date_end', 'completion_date'])))
            ->setAccount($connectedApp->getAccount())
            ->setUser($connectedApp->getUser())
        ;
    }

    /**
     * @param array<string, mixed> $protocol
     */
    public function extractProtocolId(array $protocol): ?string
    {
        return $this->firstString($protocol, ['pct_id', 'protocol_id', 'id', 'identifier']);
    }

    /**
     * @param array<string, mixed> $protocol
     * @param list<string>         $keys
     */
    private function firstString(array $protocol, array $keys): ?string
    {
        $value = $this->firstScalar($protocol, $keys);

        return null === $value ? null : trim((string) $value);
    }

    /**
     * @param array<string, mixed> $protocol
     * @param list<string>         $keys
     */
    private function firstScalar(array $protocol, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (\array_key_exists($key, $protocol) && \is_scalar($protocol[$key]) && '' !== trim((string) $protocol[$key])) {
                return $protocol[$key];
            }
        }

        return null;
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

    /**
     * @param array<string, mixed> $protocol
     */
    private function buildRepositoryLink(array $protocol, string $id): string
    {
        $url = $this->firstString($protocol, ['url', 'public_url', 'registry_url']);

        return $url ?? \sprintf('https://www.preclinicaltrials.eu/protocol/%s', rawurlencode($id));
    }
}
