<?php

declare(strict_types=1);

namespace App\Crosswalk;

use App\Entity\CanonicalForm;
use App\Entity\CanonicalFormField;
use App\Entity\ExternalTemplate;
use App\Entity\FieldCrosswalk;
use App\Entity\TemplateFormCrosswalk;
use App\Entity\User;
use App\Enum\CanonicalFormStatus;
use App\Enum\CrosswalkMappingType;
use App\Enum\MappingStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates a draft {@see CanonicalForm} and a suggested {@see TemplateFormCrosswalk}
 * from a freshly imported {@see ExternalTemplate}, with one canonical field and one
 * (unmapped, suggested) {@see FieldCrosswalk} per extracted ELN field.
 *
 * This gives every imported template an immediate, reviewable entry in the
 * Crosswalk Studio so curators can start mapping fields to CDEs; without it an
 * import would persist a template that never appears in `/template-crosswalks`.
 * Entities are persisted but NOT flushed — the caller owns the transaction.
 */
final class CrosswalkBootstrapper
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function bootstrapDraft(ExternalTemplate $template, User $user): TemplateFormCrosswalk
    {
        $account = $user->getAccount();

        $canonicalForm = new CanonicalForm();
        $canonicalForm->setTitle(\sprintf('%s — canonical form', $template->getTitle()));
        $canonicalForm->setDescription('Draft canonical form generated from the imported ELN template. Review the fields and map them to Common Data Elements.');
        $canonicalForm->setStatus(CanonicalFormStatus::DRAFT);
        $canonicalForm->setSourceLinks(array_values(array_filter([
            $template->getExternalUrl(),
            null !== $template->getId() ? 'metadatapp:external-template:' . $template->getId()->toRfc4122() : null,
        ])));
        $canonicalForm->setAccount($account);

        $crosswalk = new TemplateFormCrosswalk();
        $crosswalk->setExternalTemplate($template);
        $crosswalk->setCanonicalForm($canonicalForm);
        $crosswalk->setMappingType(CrosswalkMappingType::PARTIAL);
        $crosswalk->setMappingStatus(MappingStatus::SUGGESTED);
        $crosswalk->setNotes('Auto-created on ELN import. Map fields to CDEs and have a curator review before accepting.');
        $crosswalk->setAccount($account);

        $order = 0;
        $seenKeys = [];
        foreach ($template->getExtractedFields() as $elnField) {
            $localKey = $this->uniqueLocalKey($elnField->getLabel(), $order, $seenKeys);
            $seenKeys[$localKey] = true;

            $canonicalField = new CanonicalFormField();
            $canonicalField->setLocalKey($localKey);
            $canonicalField->setLabel($elnField->getLabel());
            $canonicalField->setDatatype($elnField->getDatatype());
            $canonicalField->setUnitConstraint($elnField->getUnit());
            $canonicalField->setJsonldPath($elnField->getJsonldPath());
            $canonicalField->setSourceFieldReference($elnField->getPropertyId());
            $canonicalField->setOrderIndex($order);
            $canonicalForm->addField($canonicalField);

            $fieldCrosswalk = new FieldCrosswalk();
            $fieldCrosswalk->setElnField($elnField);
            $fieldCrosswalk->setElnJsonldPath($elnField->getJsonldPath());
            $fieldCrosswalk->setElnPropertyId($elnField->getPropertyId());
            $fieldCrosswalk->setCanonicalFormField($canonicalField);
            $fieldCrosswalk->setMappingType(CrosswalkMappingType::RELATED);
            $fieldCrosswalk->setMappingStatus(MappingStatus::SUGGESTED);
            $crosswalk->addFieldCrosswalk($fieldCrosswalk);

            ++$order;
        }

        $this->entityManager->persist($canonicalForm);
        $this->entityManager->persist($crosswalk);

        return $crosswalk;
    }

    /**
     * @param array<string, bool> $seen
     */
    private function uniqueLocalKey(string $label, int $index, array $seen): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '_', strtolower($label)) ?? '';
        $base = trim($base, '_');
        if ('' === $base) {
            $base = 'field_' . $index;
        }
        $key = $base;
        $suffix = 2;
        while (isset($seen[$key])) {
            $key = $base . '_' . $suffix;
            ++$suffix;
        }

        return $key;
    }
}
