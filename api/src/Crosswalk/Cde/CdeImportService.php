<?php

declare(strict_types=1);

namespace App\Crosswalk\Cde;

use App\Entity\CommonDataElement;
use App\Entity\ExternalForm;
use App\Entity\User;
use App\Enum\CdeSource;
use App\Enum\CdeStatus;
use App\Enum\ExternalFormSource;
use App\Repository\CommonDataElementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Normalizes provider candidates into persisted {@see CommonDataElement} /
 * {@see ExternalForm} entities, preserving raw payload + provenance. Importing the
 * same CDE again (matched by localKey/externalId within the account) updates in
 * place rather than duplicating.
 */
final class CdeImportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommonDataElementRepository $cdeRepository,
        private readonly CdeProviderRegistry $registry,
        private readonly CustomJsonCdeProvider $customJsonProvider,
    ) {
    }

    /**
     * Fetch one CDE from a named provider and persist it for the user's account.
     */
    public function importFromProvider(string $providerKey, string $externalId, User $user): ?CommonDataElement
    {
        $provider = $this->registry->get($providerKey);
        if (null === $provider) {
            throw new \InvalidArgumentException(\sprintf('Unknown CDE provider "%s".', $providerKey));
        }

        $candidate = $provider->getCde($externalId);
        if (null === $candidate) {
            return null;
        }

        return $this->persistCde($candidate, $user, true);
    }

    /**
     * Import a manual JSON payload (one or many CDE definitions).
     *
     * @param array<int|string, mixed>|string $payload
     *
     * @return list<CommonDataElement>
     */
    public function importFromPayload(array|string $payload, User $user): array
    {
        $candidates = $this->customJsonProvider->parse($payload);

        $persisted = [];
        foreach ($candidates as $candidate) {
            $persisted[] = $this->persistCde($candidate, $user, false);
        }
        $this->entityManager->flush();

        return $persisted;
    }

    /**
     * Persist a single normalized CDE candidate, flushing immediately by default.
     */
    public function persistCde(CdeCandidate $candidate, User $user, bool $flush = true): CommonDataElement
    {
        $user = $this->managedUser($user);
        $account = $user->getAccount();
        $localKey = $candidate->localKey ?? $this->slug($candidate->label);

        $cde = $this->cdeRepository->findOneByLocalKey($account, $localKey);
        if (null === $cde && null !== $candidate->externalId) {
            $cde = $this->cdeRepository->findOneBy([
                'account' => $account,
                'source' => $this->mapSource($candidate->source),
                'externalId' => $candidate->externalId,
            ]);
        }
        $cde ??= new CommonDataElement();

        $cde->setSource($this->mapSource($candidate->source));
        $cde->setExternalId($candidate->externalId);
        $cde->setExternalUrl($candidate->externalUrl);
        $cde->setVersion($candidate->version);
        $cde->setLabel($candidate->label);
        $cde->setDefinition($candidate->definition);
        $cde->setQuestionText($candidate->questionText);
        $cde->setDatatype($candidate->datatype);
        $cde->setUnitConstraint($candidate->unitConstraint);
        $cde->setPermissibleValues($candidate->permissibleValues);
        $cde->setOntologyMappings($candidate->ontologyMappings);
        $cde->setProvenance($candidate->provenance);
        $cde->setRawPayload($candidate->rawPayload);
        $cde->setStatus($this->mapStatus($candidate->status));
        $cde->setLocalKey($localKey);
        $cde->setAccount($account);

        $this->entityManager->persist($cde);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $cde;
    }

    /**
     * Fetch one CDE Form from a named provider and persist it as an ExternalForm.
     */
    public function importFormFromProvider(string $providerKey, string $externalId, User $user): ?ExternalForm
    {
        $provider = $this->registry->get($providerKey);
        if (null === $provider) {
            throw new \InvalidArgumentException(\sprintf('Unknown CDE provider "%s".', $providerKey));
        }

        $candidate = $provider->getForm($externalId);
        if (null === $candidate) {
            return null;
        }

        return $this->persistForm($candidate, $user);
    }

    public function persistForm(FormCandidate $candidate, User $user, bool $flush = true): ExternalForm
    {
        $user = $this->managedUser($user);
        $form = new ExternalForm();
        $form->setSource($this->mapFormSource($candidate->source));
        $form->setExternalId($candidate->externalId);
        $form->setExternalUrl($candidate->externalUrl);
        $form->setTitle($candidate->title);
        $form->setDescription($candidate->description);
        $form->setVersion($candidate->version);
        $form->setRawPayload($candidate->rawPayload + ['provenance' => $candidate->provenance]);
        $form->setUser($user);
        $form->setAccount($user->getAccount());

        $this->entityManager->persist($form);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $form;
    }

    /**
     * The security token's user is detached under the stateless firewall; resolve
     * the managed instance so account/user associations persist cleanly
     * (mirrors SetUserProcessor).
     */
    private function managedUser(User $user): User
    {
        if (null !== $user->getId()) {
            return $this->entityManager->find(User::class, $user->getId()) ?? $user;
        }

        return $user;
    }

    private function mapSource(string $source): CdeSource
    {
        return CdeSource::tryFrom($source) ?? CdeSource::CUSTOM;
    }

    private function mapFormSource(string $source): ExternalFormSource
    {
        return ExternalFormSource::tryFrom($source) ?? ExternalFormSource::CUSTOM;
    }

    private function mapStatus(?string $status): CdeStatus
    {
        if (null === $status) {
            return CdeStatus::DRAFT;
        }

        return CdeStatus::tryFrom(strtolower($status)) ?? CdeStatus::DRAFT;
    }

    private function slug(string $label): string
    {
        $slug = strtolower(trim($label));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? $slug;

        return trim($slug, '_');
    }
}
