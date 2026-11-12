<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Cage;
use App\Entity\CageHcmMeasure;
use App\Entity\CageHcmSinusoidMetadata;
use App\Entity\HomeCageMonitoring;
use App\Entity\Subject;
use App\Entity\User;
use App\Entity\Variable;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/cages', name: 'api_cages_')]
#[IsGranted(Roles::ROLE_USER)]
final class CageController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/{id}/hcm', name: 'hcm', methods: ['GET'])]
    public function hcm(string $id): JsonResponse
    {
        $cage = $this->getAccessibleCage($id);
        $account = $cage->getAccount();

        /** @var list<CageHcmMeasure> $measures */
        $measures = $this->entityManager->getRepository(CageHcmMeasure::class)->findBy(
            ['account' => $account, 'cage' => $cage],
            ['recordedAt' => 'ASC'],
        );

        /** @var list<CageHcmSinusoidMetadata> $sinusoids */
        $sinusoids = $this->entityManager->getRepository(CageHcmSinusoidMetadata::class)->findBy(
            ['account' => $account, 'cage' => $cage],
            ['date' => 'ASC'],
        );

        $systems = [];
        if ($cage->getHomeCageMonitoring() instanceof HomeCageMonitoring) {
            $this->ensureHcmSystem($systems, $cage->getHomeCageMonitoring(), $cage);
        }

        foreach ($measures as $measure) {
            $hcm = $measure->getHomeCageMonitoring();
            $hcmId = (string) $hcm->getId();
            $this->ensureHcmSystem($systems, $hcm, $cage);
            $variable = $measure->getVariable();
            $variableId = (string) $variable->getId();
            $systems[$hcmId]['variables'][$variableId] = $this->serializeVariable($variable);
            $systems[$hcmId]['modalities'][$measure->getDataType()->value] = $measure->getDataType()->value;
            $variableName = strtolower($variable->getName());
            if (str_contains($variableName, 'temperature')) {
                $systems[$hcmId]['environment']['temperature'] = $measure->getValue();
                $systems[$hcmId]['modalities']['temperature'] = 'temperature';
            }
            if (str_contains($variableName, 'humidity')) {
                $systems[$hcmId]['environment']['humidity'] = $measure->getValue();
                $systems[$hcmId]['modalities']['humidity'] = 'humidity';
            }
            if (str_contains($variableName, 'light intensity')) {
                $systems[$hcmId]['environment']['lightIntensity'] = $measure->getValue();
                $systems[$hcmId]['modalities']['light intensity'] = 'light intensity';
            }
            if (str_contains($variableName, 'noise')) {
                $systems[$hcmId]['environment']['noise'] = $measure->getValue();
                $systems[$hcmId]['modalities']['noise'] = 'noise';
            }
            if (str_contains($variableName, 'human movement')) {
                $systems[$hcmId]['environment']['humanMovementWorkday'] = $measure->getValue();
                $systems[$hcmId]['modalities']['human movement'] = 'human movement';
            }
            $systems[$hcmId]['sourceAppCodes'][$measure->getSourceAppCode() ?? $this->sourceCodeForHcmSystem($hcm->getSystem())] = true;
            $systems[$hcmId]['measures'][] = [
                'id' => (string) $measure->getId(),
                'variable' => $this->serializeVariable($variable),
                'dataType' => $measure->getDataType()->value,
                'value' => $measure->getValue(),
                'recordedAt' => $measure->getRecordedAt()->format(\DateTimeInterface::ATOM),
                'sourceTaskId' => $measure->getSourceTaskId(),
                'sourceAppCode' => $measure->getSourceAppCode() ?? $this->sourceCodeForHcmSystem($hcm->getSystem()),
            ];
            $systems[$hcmId]['provenance'][] = [
                'type' => 'measure',
                'sourceAppCode' => $measure->getSourceAppCode() ?? $this->sourceCodeForHcmSystem($hcm->getSystem()),
                'sourceTaskId' => $measure->getSourceTaskId(),
                'at' => $measure->getRecordedAt()->format(\DateTimeInterface::ATOM),
                'label' => $variable->getName(),
            ];
        }

        foreach ($sinusoids as $sinusoid) {
            $hcm = $sinusoid->getHomeCageMonitoring();
            $hcmId = (string) $hcm->getId();
            $this->ensureHcmSystem($systems, $hcm, $cage);
            $systems[$hcmId]['modalities']['sinusoid'] = 'sinusoid';
            $systems[$hcmId]['sourceAppCodes'][$sinusoid->getSourceAppCode() ?? $this->sourceCodeForHcmSystem($hcm->getSystem())] = true;
            $systems[$hcmId]['sinusoidMetadata'][] = [
                'id' => (string) $sinusoid->getId(),
                'date' => $sinusoid->getDate()->format('Y-m-d'),
                'amplitude' => $sinusoid->getAmplitude(),
                'mesor' => $sinusoid->getMesor(),
                'phase' => $sinusoid->getPhase(),
                'peakHour' => $sinusoid->getPeakHour(),
                'sourceTaskId' => $sinusoid->getSourceTaskId(),
                'sourceAppCode' => $sinusoid->getSourceAppCode() ?? $this->sourceCodeForHcmSystem($hcm->getSystem()),
            ];
            $systems[$hcmId]['provenance'][] = [
                'type' => 'sinusoid',
                'sourceAppCode' => $sinusoid->getSourceAppCode() ?? $this->sourceCodeForHcmSystem($hcm->getSystem()),
                'sourceTaskId' => $sinusoid->getSourceTaskId(),
                'at' => $sinusoid->getDate()->format('Y-m-d'),
                'label' => 'MIROsine sinusoid fit',
            ];
        }

        return $this->json([
            'cageId' => $id,
            'systems' => array_values(array_map(
                static fn (array $system): array => [
                    ...$system,
                    'sourceAppCodes' => array_keys($system['sourceAppCodes']),
                    'sourceAppCode' => array_key_first($system['sourceAppCodes']),
                    'modalities' => array_values($system['modalities']),
                    'variables' => array_values($system['variables']),
                ],
                $systems,
            )),
        ]);
    }

    #[Route('/{id}/subjects', name: 'subjects', methods: ['GET'])]
    public function subjects(string $id): JsonResponse
    {
        $cage = $this->getAccessibleCage($id);

        $subjects = array_map(
            static fn (Subject $subject): array => [
                'id' => (string) $subject->getId(),
                'cageId' => $id,
                'label' => $subject->getExternalId() ?? $subject->getName(),
                'species' => $subject->getSpecies()->value,
                'sex' => $subject->getSex()->value,
                'strain' => $subject->getStrain()->getName(),
                'status' => 'active',
                'dob' => $subject->getBirthAt()->format(\DateTimeInterface::ATOM),
            ],
            $cage->getSubjects()->toArray()
        );

        return $this->json([
            'cageId' => $id,
            'subjects' => $subjects,
        ]);
    }

    /**
     * @param array<string, array<string, mixed>> $systems
     */
    private function ensureHcmSystem(array &$systems, HomeCageMonitoring $hcm, Cage $cage): void
    {
        $hcmId = (string) $hcm->getId();
        if (isset($systems[$hcmId])) {
            return;
        }

        $experiment = $hcm->getExperiment();
        $project = $experiment->getProject();
        $systems[$hcmId] = [
            'id' => $hcmId,
            'system' => $hcm->getSystem(),
            'lightsOnUtcHour' => $hcm->getLightsOnUtcHour(),
            'lightDurationHours' => $hcm->getLightDurationHours(),
            'experiment' => [
                'id' => (string) $experiment->getId(),
                'name' => $experiment->getName(),
                'protocol' => $experiment->getProtocol(),
                'project' => null === $project ? null : [
                    'id' => (string) $project->getId(),
                    'name' => $project->getName(),
                ],
            ],
            'sourceAppCodes' => [],
            'modalities' => [],
            'environment' => $this->serializeEnvironment($cage),
            'variables' => [],
            'measures' => [],
            'sinusoidMetadata' => [],
            'provenance' => [],
        ];
    }

    private function getAccessibleCage(string $id): Cage
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException('Cage not found.');
        }

        /** @var Cage|null $cage */
        $cage = $this->entityManager->getRepository(Cage::class)->find(Uuid::fromString($id));
        if (!$cage instanceof Cage) {
            throw $this->createNotFoundException('Cage not found.');
        }

        $currentUser = $this->getCurrentUser();
        $cageAccountId = $cage->getAccount()->getId();
        $userAccountId = $currentUser->getAccount()->getId();
        if (null === $cageAccountId || null === $userAccountId || !$cageAccountId->equals($userAccountId)) {
            throw $this->createAccessDeniedException('Resource not accessible.');
        }

        return $cage;
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        return $user;
    }

    /**
     * @return array{id: string, name: string, externalId: ?string, tecniplastExternalId: ?string}
     */
    private function serializeVariable(Variable $variable): array
    {
        return [
            'id' => (string) $variable->getId(),
            'name' => $variable->getName(),
            'externalId' => $variable->getExternalId(),
            'tecniplastExternalId' => $variable->getTecniplastExternalId(),
        ];
    }

    private function sourceCodeForHcmSystem(string $system): string
    {
        return match ($system) {
            'Olden Labs HCM' => 'olden_labs_hcm',
            'Live Mouse Tracker' => 'live_mouse_tracker',
            'Open-BEATBox' => 'open_beatbox',
            default => 'tecniplast_dvc',
        };
    }

    /**
     * @return array{temperature: ?float, humidity: ?float, lightIntensity: ?float, noise: ?float, lightCycle: ?string, humanMovementWorkday: ?float}
     */
    private function serializeEnvironment(Cage $cage): array
    {
        $environment = $cage->getEnvironmentHousing();

        return [
            'temperature' => null === $environment ? null : $environment->getTemperature(),
            'humidity' => null === $environment ? null : $environment->getHumidity(),
            'lightIntensity' => null === $environment ? null : $environment->getLuminosity(),
            'noise' => null === $environment ? null : $environment->getNoise(),
            'lightCycle' => null === $environment ? null : $environment->getLightCycle()?->value,
            'humanMovementWorkday' => null,
        ];
    }
}
