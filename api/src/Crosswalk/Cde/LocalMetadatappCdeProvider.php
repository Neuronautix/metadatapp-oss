<?php

declare(strict_types=1);

namespace App\Crosswalk\Cde;

use App\Entity\Account;
use App\Entity\CommonDataElement;
use App\Entity\User;
use App\Repository\CommonDataElementRepository;
use App\Repository\ExternalFormRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Serves CDEs (and CDE Forms) already stored locally in Metadatapp, scoped to the
 * current user's account. This is the primary, source-agnostic provider.
 */
final class LocalMetadatappCdeProvider implements CdeProviderInterface
{
    public function __construct(
        private readonly CommonDataElementRepository $cdeRepository,
        private readonly ExternalFormRepository $formRepository,
        private readonly Security $security,
    ) {
    }

    public function key(): string
    {
        return 'metadatapp';
    }

    public function searchCdes(string $query): array
    {
        $account = $this->currentAccountOrNull();
        $criteria = null !== $account ? ['account' => $account] : [];

        $needle = $this->normalize($query);
        $results = [];
        foreach ($this->cdeRepository->findBy($criteria) as $cde) {
            if ('' === $needle
                || str_contains($this->normalize($cde->getLabel()), $needle)
                || ($cde->getLocalKey() && str_contains($this->normalize($cde->getLocalKey()), $needle))
            ) {
                $results[] = $this->toCandidate($cde);
            }
        }

        return $results;
    }

    public function getCde(string $externalId): ?CdeCandidate
    {
        $account = $this->currentAccountOrNull();
        if (null !== $account) {
            // Authenticated: never fall back to a cross-account lookup, otherwise a
            // user could pull another tenant's CDE (raw payload/provenance) by id.
            $cde = $this->cdeRepository->findOneByLocalKey($account, $externalId)
                ?? $this->cdeRepository->findOneBy(['account' => $account, 'externalId' => $externalId]);

            return null !== $cde ? $this->toCandidate($cde) : null;
        }

        // No authenticated account (e.g. CLI/system context): global lookup is allowed.
        $cde = $this->cdeRepository->findOneBy(['externalId' => $externalId]);

        return null !== $cde ? $this->toCandidate($cde) : null;
    }

    public function searchForms(string $query): array
    {
        $account = $this->currentAccountOrNull();
        $criteria = null !== $account ? ['account' => $account] : [];

        $needle = $this->normalize($query);
        $results = [];
        foreach ($this->formRepository->findBy($criteria) as $form) {
            if ('' === $needle || str_contains($this->normalize($form->getTitle()), $needle)) {
                $results[] = new FormCandidate(
                    source: 'metadatapp',
                    title: $form->getTitle(),
                    externalId: $form->getExternalId(),
                    externalUrl: $form->getExternalUrl(),
                    version: $form->getVersion(),
                    description: $form->getDescription(),
                    provenance: ['provider' => 'metadatapp'],
                    rawPayload: $form->getRawPayload() ?? [],
                );
            }
        }

        return $results;
    }

    public function getForm(string $externalId): ?FormCandidate
    {
        $form = $this->formRepository->findOneBy(['externalId' => $externalId]);
        if (null === $form) {
            return null;
        }

        return new FormCandidate(
            source: 'metadatapp',
            title: $form->getTitle(),
            externalId: $form->getExternalId(),
            externalUrl: $form->getExternalUrl(),
            version: $form->getVersion(),
            description: $form->getDescription(),
            provenance: ['provider' => 'metadatapp'],
            rawPayload: $form->getRawPayload() ?? [],
        );
    }

    private function toCandidate(CommonDataElement $cde): CdeCandidate
    {
        /** @var list<array<string, mixed>> $permissible */
        $permissible = array_values($cde->getPermissibleValues());
        /** @var list<array<string, mixed>> $ontology */
        $ontology = array_values($cde->getOntologyMappings());

        return new CdeCandidate(
            source: $cde->getSource()->value,
            label: $cde->getLabel(),
            externalId: $cde->getExternalId(),
            externalUrl: $cde->getExternalUrl(),
            version: $cde->getVersion(),
            definition: $cde->getDefinition(),
            questionText: $cde->getQuestionText(),
            datatype: $cde->getDatatype(),
            unitConstraint: $cde->getUnitConstraint(),
            status: $cde->getStatus()->value,
            localKey: $cde->getLocalKey(),
            permissibleValues: $permissible,
            ontologyMappings: $ontology,
            provenance: $cde->getProvenance(),
            rawPayload: $cde->getRawPayload() ?? [],
        );
    }

    private function currentAccountOrNull(): ?Account
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getAccount() : null;
    }

    private function normalize(string $value): string
    {
        return trim(strtolower(preg_replace('/[\s_\-]+/', ' ', $value) ?? $value));
    }
}
