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
        $protocol = $this->normalizeProtocol($protocol);
        $id = $this->extractProtocolId($protocol);
        if (null === $id) {
            throw new \InvalidArgumentException('PreclinicalTrials.eu protocol is missing an identifier.');
        }

        $title = $this->firstString($protocol, ['short_title', 'scientific_title', 'public_title', 'title', 'name']) ?? 'PreclinicalTrials protocol ' . $id;

        return $project
            ->setExternalId('preclinicaltrials:' . $id)
            ->setName($this->formatName($id, $title))
            ->setGoal($this->firstString($protocol, ['hypothesis', 'objective', 'primary_objective', 'goal', 'research_field']))
            ->setDescription($this->buildDescription($protocol))
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
        $protocol = $this->normalizeProtocol($protocol);

        return $this->firstString($protocol, ['pct_id', 'protocol_id', 'id', 'identifier']);
    }

    /**
     * @param array<string, mixed> $protocol
     *
     * @return array<string, mixed>
     */
    public function summarize(array $protocol): array
    {
        $protocol = $this->normalizeProtocol($protocol);
        $id = $this->extractProtocolId($protocol);

        return [
            'id' => $id,
            'title' => $this->firstString($protocol, ['short_title', 'scientific_title', 'public_title', 'title', 'name']),
            'status' => $this->firstString($protocol, ['study_status', 'status']),
            'registrationDate' => $this->firstString($protocol, ['registration_date']),
            'startDate' => $this->firstString($protocol, ['start_date', 'date_start']),
            'endDate' => $this->firstString($protocol, ['end_date', 'date_end', 'completion_date']),
            'researchField' => $this->firstString($protocol, ['research_field']),
            'species' => $this->firstString($protocol, ['species']),
            'strain' => $this->firstString($protocol, ['strain']),
            'sex' => $this->firstString($protocol, ['sex']),
            'animals' => $this->firstString($protocol, ['sum_of_animals']),
            'repositoryLink' => null === $id ? null : $this->buildRepositoryLink($protocol, $id),
        ];
    }

    /**
     * @param array<string, mixed> $protocol
     *
     * @return array<string, mixed>
     */
    private function normalizeProtocol(array $protocol): array
    {
        $normalized = $protocol;
        $details = $protocol['details'] ?? null;
        if (!\is_array($details)) {
            return $normalized;
        }

        foreach ($details as $detail) {
            if (!\is_array($detail)) {
                continue;
            }
            $key = $detail['key'] ?? null;
            if (!\is_string($key) || '' === trim($key) || !\array_key_exists('value', $detail)) {
                continue;
            }
            $value = $detail['value'];
            if (null === $value || (\is_string($value) && '' === trim($value))) {
                continue;
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $protocol
     * @param list<string>         $keys
     */
    private function firstString(array $protocol, array $keys): ?string
    {
        $value = $this->firstScalar($protocol, $keys);

        return null === $value ? null : $this->cleanText((string) $value);
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

        return $url ?? \sprintf('https://preclinicaltrials.eu/protocol/%s', rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $protocol
     */
    private function buildDescription(array $protocol): ?string
    {
        $lines = [];
        $summary = $this->firstString($protocol, ['description', 'summary', 'abstract']);
        if (null !== $summary) {
            $lines[] = $summary;
        }

        $metadata = [
            'PCT ID' => $this->firstString($protocol, ['pct_id', 'protocol_id', 'identifier']),
            'Registration date' => $this->firstString($protocol, ['registration_date']),
            'Status' => $this->firstString($protocol, ['study_status', 'status']),
            'Research field' => $this->firstString($protocol, ['research_field']),
            'Study stage' => $this->firstString($protocol, ['study_stage']),
            'Species' => $this->firstString($protocol, ['species']),
            'Strain' => $this->firstString($protocol, ['strain']),
            'Sex' => $this->firstString($protocol, ['sex']),
            'Animals' => $this->firstString($protocol, ['sum_of_animals']),
            'Application number' => $this->firstString($protocol, ['application_number']),
        ];

        $metadataLines = [];
        foreach ($metadata as $label => $value) {
            if (null !== $value) {
                $metadataLines[] = $label . ': ' . $value;
            }
        }
        if ([] !== $metadataLines) {
            $lines[] = implode("\n", $metadataLines);
        }

        foreach ([
            'Study centre' => $this->formatStudyCentres($protocol['study_centre'] ?? null),
            'Study arms' => $this->formatStudyArms($protocol['study_arms'] ?? null),
            'Primary readout' => $this->firstString($protocol, ['primary_readout_parameter']),
            'Secondary readout' => $this->firstString($protocol, ['secondary_readout_parameter']),
            'Experimental design' => $this->firstString($protocol, ['experimental_design']),
        ] as $label => $value) {
            if (null !== $value) {
                $lines[] = $label . ":\n" . $value;
            }
        }

        return [] === $lines ? null : implode("\n\n", $lines);
    }

    private function formatName(string $id, string $title): string
    {
        $title = $this->cleanText($title);

        return str_starts_with($title, $id) ? $title : $id . ' - ' . $title;
    }

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
    }

    private function formatStudyCentres(mixed $value): ?string
    {
        if (!\is_array($value)) {
            return \is_scalar($value) && '' !== trim((string) $value) ? $this->cleanText((string) $value) : null;
        }

        $centres = [];
        foreach ($value as $centre) {
            if (!\is_array($centre)) {
                continue;
            }
            $parts = array_filter([
                $this->stringValue($centre['name'] ?? null),
                $this->stringValue($centre['city'] ?? null),
                $this->stringValue($centre['country'] ?? null),
            ]);
            if ([] !== $parts) {
                $centres[] = implode(', ', $parts);
            }
        }

        return [] === $centres ? null : implode("\n", $centres);
    }

    private function formatStudyArms(mixed $value): ?string
    {
        if (!\is_array($value)) {
            return \is_scalar($value) && '' !== trim((string) $value) ? $this->cleanText((string) $value) : null;
        }

        $arms = [];
        foreach ($value as $arm) {
            if (!\is_array($arm)) {
                continue;
            }
            $type = $this->stringValue($arm['type'] ?? null);
            $number = $this->stringValue($arm['number'] ?? null);
            $intervention = $this->stringValue($arm['intervention'] ?? null);
            $prefix = implode(' ', array_filter([$type, null === $number ? null : '(' . $number . ')']));
            if (null !== $intervention) {
                $arms[] = '' === $prefix ? $intervention : $prefix . ': ' . $intervention;
            }
        }

        return [] === $arms ? null : implode("\n", $arms);
    }

    private function stringValue(mixed $value): ?string
    {
        return \is_scalar($value) && '' !== trim((string) $value) ? $this->cleanText((string) $value) : null;
    }
}
