<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Mapper;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentCreateRequestDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentUpdateRequestDto;
use App\ConnectedApps\Error\SyncValidityException;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\Experiment;
use App\Enum\ExperimentType;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ExperimentMapper
{
    private Serializer $serializer;

    public function __construct()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $normalizers = [
            new ObjectNormalizer($classMetadataFactory),
            new ArrayDenormalizer(),
        ];
        $this->serializer = new Serializer($normalizers, [new JsonEncoder()]);
    }

    /**
     * @throws SyncValidityException
     */
    public function mapToExternal(ConnectedAppEntityInterface $entity): ExperimentCreateRequestDto|ExperimentUpdateRequestDto
    {
        if (!$entity instanceof Experiment) {
            throw new \InvalidArgumentException('Expected Experiment entity.');
        }

        $requestArray = $this->makeRequestArray($entity);
        $dtoClass = $entity->getElaftwExternalId() ? ExperimentUpdateRequestDto::class : ExperimentCreateRequestDto::class;
        $group = $entity->getElaftwExternalId() ? 'elabftw:experiment:update' : 'elabftw:experiment:create';

        return $this->serializer->denormalize(
            $requestArray,
            $dtoClass,
            'array',
            ['groups' => [$group]]
        );
    }

    /**
     * @throws SyncValidityException
     */
    private function makeRequestArray(Experiment $experiment): array
    {
        $title = $experiment->getName();
        $body = $experiment->getDescription() ?? $experiment->getProtocol();
        $tags = [];
        if ($experiment->getGoal()) {
            $tags[] = $experiment->getGoal();
        }
        $metadata = $this->normalizeMetadataForExternal($experiment);
        $status = $this->mapStatus($experiment->getType());

        if (!$title || !$body) {
            throw new SyncValidityException('Missing required experiment fields for eLabFTW sync.');
        }

        return [
            'title' => $title,
            'body' => $body,
            'tags' => $tags,
            'status' => $status,
            'metadata' => json_encode($metadata, \JSON_THROW_ON_ERROR),
        ];
    }

    private function mapStatus(ExperimentType $type): int
    {
        // TODO: Status IDs are team-specific in eLabFTW and not constant.
        // This mapping assumes a specific configuration in the target eLabFTW instance.
        // In the future, these should be configurable or fetched dynamically.
        return match ($type) {
            ExperimentType::TypeA => 1,
            ExperimentType::TypeB => 2,
            ExperimentType::TypeC => 3,
        };
    }

    private function reverseMapStatus(?int $status): ExperimentType
    {
        return match ($status) {
            1 => ExperimentType::TypeA,
            2 => ExperimentType::TypeB,
            3 => ExperimentType::TypeC,
            default => ExperimentType::TypeA,
        };
    }

    public function mapToInternal(ExperimentDto $dto, Experiment $experiment): Experiment
    {
        $experiment->setName($dto->title);

        if ($dto->body) {
            $experiment->setDescription($dto->body);
        }

        $tags = $dto->tags;
        if (!empty($tags)) {
            // We only map the first tag to 'goal' as 'goal' is a string, not an array.
            // This is a lossy mapping, but consistent with makeRequestArray.
            $firstTag = $tags[0] ?? null;
            if ($firstTag) {
                $experiment->setGoal((string) $firstTag);
            }
        }

        $experiment->setType($this->reverseMapStatus($dto->status));
        $experiment->setElaftwExternalId((string) $dto->id);
        $experiment->setElabftwMetadata($dto->metadata);
        $this->applyDateFieldsFromMetadata($dto->metadata, $experiment);

        return $experiment;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetadataForExternal(Experiment $experiment): array
    {
        $metadata = $experiment->getElabftwMetadata() ?? [];

        if ([] === $metadata) {
            $metadata = ['extra_fields' => []];
        }

        if (!isset($metadata['extra_fields']) || !\is_array($metadata['extra_fields'])) {
            $metadata = ['extra_fields' => $metadata];
        }

        $extraFields = $metadata['extra_fields'];
        $startAt = $this->getStartAtOrNull($experiment);
        $endAt = $experiment->getEndAt();

        if ($startAt instanceof \DateTimeInterface) {
            $extraFields['Start'] = $this->mergeExtraField(
                $extraFields['Start'] ?? null,
                'date',
                $startAt->format('Y-m-d')
            );
        }

        if ($endAt instanceof \DateTimeInterface) {
            $extraFields['End'] = $this->mergeExtraField(
                $extraFields['End'] ?? null,
                'date',
                $endAt->format('Y-m-d')
            );
        }

        $metadata['extra_fields'] = $extraFields;

        return $metadata;
    }

    private function getStartAtOrNull(Experiment $experiment): ?\DateTimeInterface
    {
        try {
            return $experiment->getStartAt();
        } catch (\Error) {
            return null;
        }
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function applyDateFieldsFromMetadata(?array $metadata, Experiment $experiment): void
    {
        if (!\is_array($metadata)) {
            return;
        }

        $extraFields = $metadata['extra_fields'] ?? null;
        if (!\is_array($extraFields)) {
            return;
        }

        $startValue = $this->extractExtraFieldValue($extraFields['Start'] ?? null);
        if (\is_string($startValue) && '' !== trim($startValue)) {
            try {
                $experiment->setStartAt(new \DateTimeImmutable($startValue));
            } catch (\Throwable) {
            }
        }

        $endValue = $this->extractExtraFieldValue($extraFields['End'] ?? null);
        if (\is_string($endValue) && '' !== trim($endValue)) {
            try {
                $experiment->setEndAt(new \DateTimeImmutable($endValue));
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeExtraField(mixed $field, string $type, string $value): array
    {
        $base = \is_array($field) ? $field : [];
        $base['type'] = $base['type'] ?? $type;
        $base['value'] = $value;

        return $base;
    }

    private function extractExtraFieldValue(mixed $field): ?string
    {
        if (\is_array($field) && isset($field['value']) && \is_scalar($field['value'])) {
            return (string) $field['value'];
        }

        return null;
    }
}
