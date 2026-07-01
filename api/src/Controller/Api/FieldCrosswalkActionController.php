<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CanonicalFormField;
use App\Entity\CommonDataElement;
use App\Entity\ExtractedTemplateField;
use App\Entity\FieldCrosswalk;
use App\Entity\User;
use App\Enum\CrosswalkMappingType;
use App\Enum\MappingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Human-review actions on a single field-level mapping: accept or reject.
 * Accepting/rejecting records the reviewer + timestamp on the parent crosswalk.
 */
#[IsGranted('ROLE_USER')]
final class FieldCrosswalkActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[Route(path: '/api/field-crosswalks/{id}/accept', name: 'api_field_crosswalk_accept', methods: ['POST'])]
    public function accept(string $id): JsonResponse
    {
        return $this->transition($id, MappingStatus::ACCEPTED);
    }

    #[Route(path: '/api/field-crosswalks/{id}/reject', name: 'api_field_crosswalk_reject', methods: ['POST'])]
    public function reject(string $id): JsonResponse
    {
        return $this->transition($id, MappingStatus::REJECTED);
    }

    /**
     * Partial update of a single field mapping: mapping type/status, curator notes,
     * datatype/unit/value mappings, and the linked CDE / canonical field / ELN field.
     * Only keys present in the request body are changed.
     */
    #[Route(path: '/api/field-crosswalks/{id}', name: 'api_field_crosswalk_patch', methods: ['PATCH'])]
    public function patch(string $id, Request $request): JsonResponse
    {
        $fieldCrosswalk = $this->loadFieldCrosswalk($id);

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['message' => 'Invalid JSON body.'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        \assert($user instanceof User);
        $accountId = $user->getAccount()->getId();

        if (\array_key_exists('mappingType', $payload)) {
            $type = CrosswalkMappingType::tryFrom((string) $payload['mappingType']);
            if (null === $type) {
                return new JsonResponse(['message' => 'Invalid mappingType.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $fieldCrosswalk->setMappingType($type);
        }

        if (\array_key_exists('mappingStatus', $payload)) {
            $status = MappingStatus::tryFrom((string) $payload['mappingStatus']);
            if (null === $status) {
                return new JsonResponse(['message' => 'Invalid mappingStatus.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $fieldCrosswalk->setMappingStatus($status);
        }

        if (\array_key_exists('curatorNotes', $payload)) {
            $fieldCrosswalk->setCuratorNotes(null !== $payload['curatorNotes'] ? (string) $payload['curatorNotes'] : null);
        }
        if (\array_key_exists('evidence', $payload)) {
            $fieldCrosswalk->setEvidence(null !== $payload['evidence'] ? (string) $payload['evidence'] : null);
        }
        if (\array_key_exists('confidence', $payload)) {
            $fieldCrosswalk->setConfidence(null !== $payload['confidence'] ? (float) $payload['confidence'] : null);
        }
        if (\array_key_exists('cdeFormFieldId', $payload)) {
            $fieldCrosswalk->setCdeFormFieldId(null !== $payload['cdeFormFieldId'] ? (string) $payload['cdeFormFieldId'] : null);
        }
        foreach (['datatypeMapping' => 'setDatatypeMapping', 'unitMapping' => 'setUnitMapping', 'valueMapping' => 'setValueMapping'] as $key => $setter) {
            if (\array_key_exists($key, $payload) && \is_array($payload[$key])) {
                $fieldCrosswalk->{$setter}($payload[$key]);
            }
        }

        if (\array_key_exists('cde', $payload)) {
            if (null === $payload['cde']) {
                $fieldCrosswalk->setCde(null);
            } else {
                $cde = $this->resolve(CommonDataElement::class, (string) $payload['cde']);
                if (null === $cde || null === $accountId || null === $cde->getAccount()->getId() || !$accountId->equals($cde->getAccount()->getId())) {
                    return new JsonResponse(['message' => 'CDE not accessible.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $fieldCrosswalk->setCde($cde);
            }
        }

        if (\array_key_exists('canonicalFormField', $payload)) {
            if (null === $payload['canonicalFormField']) {
                $fieldCrosswalk->setCanonicalFormField(null);
            } else {
                $cff = $this->resolve(CanonicalFormField::class, (string) $payload['canonicalFormField']);
                $cffAccountId = $cff?->getCanonicalForm()->getAccount()->getId();
                if (null === $cff || null === $accountId || null === $cffAccountId || !$accountId->equals($cffAccountId)) {
                    return new JsonResponse(['message' => 'Canonical form field not accessible.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $fieldCrosswalk->setCanonicalFormField($cff);
            }
        }

        if (\array_key_exists('elnField', $payload)) {
            if (null === $payload['elnField']) {
                $fieldCrosswalk->setElnField(null);
            } else {
                $elnField = $this->resolve(ExtractedTemplateField::class, (string) $payload['elnField']);
                $elnAccountId = $elnField?->getExternalTemplate()->getAccount()->getId();
                if (null === $elnField || null === $accountId || null === $elnAccountId || !$accountId->equals($elnAccountId)) {
                    return new JsonResponse(['message' => 'ELN field not accessible.'], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $fieldCrosswalk->setElnField($elnField);
            }
        }

        $this->entityManager->flush();

        $json = $this->serializer->serialize($fieldCrosswalk, 'json', ['groups' => ['field_crosswalk.read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * Resolve a related entity from either a bare UUID or an IRI such as
     * `/api/cdes/{uuid}`; the last path segment is treated as the id.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function resolve(string $class, string $ref): ?object
    {
        $segment = basename(trim($ref));
        try {
            $uuid = Uuid::fromString($segment);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return $this->entityManager->getRepository($class)->find($uuid);
    }

    private function transition(string $id, MappingStatus $status): JsonResponse
    {
        $fieldCrosswalk = $this->loadFieldCrosswalk($id);

        $user = $this->getUser();
        \assert($user instanceof User);

        $fieldCrosswalk->setMappingStatus($status);

        $elnField = $fieldCrosswalk->getElnField();
        if (null !== $elnField) {
            $elnField->setMapped(MappingStatus::ACCEPTED === $status);
        }

        // Record reviewer + timestamp on the parent crosswalk. The security token's
        // user is detached under the stateless firewall, so resolve the managed
        // instance before associating it (mirrors SetUserProcessor).
        $reviewer = null !== $user->getId() ? $this->entityManager->find(User::class, $user->getId()) : null;
        $parent = $fieldCrosswalk->getTemplateFormCrosswalk();
        $parent->setReviewedBy($reviewer ?? $user);
        $parent->setReviewedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $json = $this->serializer->serialize($fieldCrosswalk, 'json', ['groups' => ['field_crosswalk.read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    private function loadFieldCrosswalk(string $id): FieldCrosswalk
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException('Invalid field crosswalk ID format.');
        }

        $fieldCrosswalk = $this->entityManager->getRepository(FieldCrosswalk::class)->find($uuid);
        if (null === $fieldCrosswalk) {
            throw $this->createNotFoundException(\sprintf('Field crosswalk %s not found.', $id));
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $crosswalkAccountId = $fieldCrosswalk->getTemplateFormCrosswalk()->getAccount()->getId();
        $userAccountId = $user->getAccount()->getId();
        if (null === $crosswalkAccountId || null === $userAccountId || !$crosswalkAccountId->equals($userAccountId)) {
            throw $this->createAccessDeniedException('Field crosswalk not accessible.');
        }

        return $fieldCrosswalk;
    }
}
