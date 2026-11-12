<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Mapper;

use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreCreateRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreUpsertRequestDto;
use App\ConnectedApps\Error\SyncValidityException;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\Experiment;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

// use App\ConnectedApps\Apps\Fair3r\Exception\EmptyExperimentException;

class ExperimentMapper
{
    private Serializer $serializer;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());

        $normalizers = [
            new ObjectNormalizer($classMetadataFactory),
            new ArrayDenormalizer(),
        ];

        $this->serializer = new Serializer($normalizers, [new JsonEncoder()]);
    }

    /**
     * @throws \InvalidArgumentException
     * @throws SyncValidityException
     */
    public function mapToExternal(ConnectedAppEntityInterface $entity): DatastoreCreateRequestDto|DatastoreUpsertRequestDto
    {
        if (!$entity instanceof Experiment) {
            throw new \InvalidArgumentException('Expected Experiment entity.');
        }

        if (!$entity->getProject()?->getFair3rExternalId()) {
            throw new SyncValidityException('mapToExternal: Project external ID not found, you need to create project, dataset (aka packages) first, But Project creation in Fair3R is not implemented yet.');
        }

        $requestArray = $this->mapToArray($entity);

        return $this->serializer->denormalize(
            $requestArray,
            $entity->getFair3rExternalId() ? DatastoreUpsertRequestDto::class : DatastoreCreateRequestDto::class,
            'array',
        );
    }

    /**
     * Temporary array mapping until proper serializer groups are implemented.
     *
     * Follows the ISA (Investigation / Study / Assay) organization:
     *   - Investigation → Project
     *   - Study         → Experiment
     *   - Assay         → Procedure / Behavior
     *
     * @throws \Exception
     */
    protected function mapToArray(Experiment $experiment): array
    {
        $project = $experiment->getProject();
        if (null === $project || null === $project->getFair3rExternalId()) {
            throw new \Exception('mapToExternal: Project external ID not found, you need to create project, dataset (aka packages) first, But Project creation in Fair3R is not implemented yet.');
        }

        // ISA-level metadata embedded as resource description
        $isaDescription = \sprintf(
            '[ISA] Investigation: %s | Study: %s | Start: %s',
            $project->getName(),
            $experiment->getName(),
            $experiment->getStartAt()->format('Y-m-d'),
        );

        $requestArray = [
            'resource' => [
                'name' => $experiment->getName(),
                'description' => $experiment->getDescription() ?? $isaDescription,
                'package_id' => $project->getFair3rExternalId(),
            ],
        ];
        if ($experiment->getFair3rExternalId()) {
            $requestArray['resource_id'] = $experiment->getFair3rExternalId();
        }
        if ($experiment->getProcedures()->isEmpty()) {
            return $requestArray;
        }

        // ISA fields: core measurement columns + ISA provenance columns
        $fields = [
            ['id' => 'isa_investigation', 'type' => 'text'],
            ['id' => 'isa_study', 'type' => 'text'],
            ['id' => 'isa_assay', 'type' => 'text'],
            ['id' => 'subject', 'type' => 'text'],
            ['id' => 'weight', 'type' => 'float'],
            ['id' => 'date', 'type' => 'timestamp'],
        ];
        $records = [];

        foreach ($experiment->getProcedures() as $procedure) {
            $assayName = $procedure->getName();
            foreach ($procedure->getSubjects() as $subject) {
                foreach ($subject->getWeightMeasurements()->filter(static fn ($w) => !$w->getFair3rExternalId() && $w->getWeight() > 0) as $weightMeasurement) {
                    $records[] = [
                        'isa_investigation' => $project->getName(),
                        'isa_study' => $experiment->getName(),
                        'isa_assay' => $assayName,
                        // Subject identifier: prefer SoftMouse external ID for cross-system traceability;
                        // long-term this should be an IRI derived from ->getId() or a configurable mapping.
                        'subject' => $subject->getSoftmouseExternalId() ?? (string) $subject->getId(),
                        'weight' => $weightMeasurement->getWeight(),
                        'date' => $weightMeasurement->getMeasuredAt()->format('Y-m-d\TH:i:s'),
                    ];
                }
            }
        }

        if ([] === $records) {
            $this->logger->info('Experiment records are null or empty, cannot sync to Fair3R.');
            throw new \Exception('Experiment records are null or empty, cannot sync to Fair3R.');
        }
        $requestArray['fields'] = $fields;
        $requestArray['records'] = $records;

        return $requestArray;
    }
}
