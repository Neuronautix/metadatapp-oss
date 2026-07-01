<?php

declare(strict_types=1);

namespace App\Crosswalk\Import;

use App\Crosswalk\CrosswalkBootstrapper;
use App\Entity\ExternalTemplate;
use App\Entity\ExtractedTemplateField;
use App\Entity\User;
use App\Enum\ExternalTemplateFormat;
use App\Enum\ExternalTemplateSource;
use App\Repository\ExternalTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Persists an imported ELN RO-Crate as an {@see ExternalTemplate} plus one
 * {@see ExtractedTemplateField} per extracted field. Raw metadata and payload
 * are preserved verbatim; nothing extracted is discarded.
 */
final class ElnImportService
{
    public function __construct(
        private readonly ElnRoCrateImporter $importer,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExternalTemplateRepository $templateRepository,
        private readonly CrosswalkBootstrapper $bootstrapper,
    ) {
    }

    /**
     * Import an uploaded `.eln` file for the given user/account.
     */
    public function importEln(UploadedFile $file, User $user): ExternalTemplate
    {
        $parsed = $this->importer->importFromFile($file);

        return $this->persist($parsed, $user, ExternalTemplateSource::ELN_FILE);
    }

    /**
     * Import an `.eln` referenced by URL (e.g. ELN.community) for the user/account.
     */
    public function importElnUrl(string $url, User $user): ExternalTemplate
    {
        $parsed = $this->importer->importFromUrl($url);

        return $this->persist($parsed, $user, ExternalTemplateSource::ELN_COMMUNITY);
    }

    private function persist(ParsedRoCrate $parsed, User $user, ExternalTemplateSource $source): ExternalTemplate
    {
        // The security token's user is detached under the stateless firewall; load
        // the managed instance so its account/user associations persist cleanly
        // (mirrors SetUserProcessor).
        if (null !== $user->getId()) {
            $user = $this->entityManager->find(User::class, $user->getId()) ?? $user;
        }

        $existing = $this->templateRepository->findOneByFileHash($parsed->fileHash);
        if (null !== $existing && $existing->getAccount() === $user->getAccount()) {
            return $existing;
        }

        $template = new ExternalTemplate();
        $template->setSource($source);
        $template->setFormat(ExternalTemplateFormat::ELN_RO_CRATE);
        $template->setTitle($parsed->title ?? 'Imported ELN template');
        $template->setDescription($parsed->description);
        $template->setVersion($parsed->version);
        $template->setExternalUrl($parsed->externalUrl);
        $template->setRoCrateMetadata($parsed->metadataJson);
        $template->setRawPayload(['rootDataset' => $parsed->rootDataset]);
        $template->setFileHash($parsed->fileHash);
        $template->setUser($user);
        $template->setAccount($user->getAccount());

        foreach ($parsed->extractedFields as $fieldData) {
            $template->addExtractedField($this->buildField($fieldData));
        }

        $this->entityManager->persist($template);

        // Give the imported template an immediate, reviewable crosswalk entry so it
        // appears in the Crosswalk Studio and curators can start mapping fields.
        $this->bootstrapper->bootstrapDraft($template, $user);

        $this->entityManager->flush();

        return $template;
    }

    private function buildField(ExtractedFieldData $data): ExtractedTemplateField
    {
        $field = new ExtractedTemplateField();
        $field->setLabel($data->label);
        $field->setPropertyId($data->propertyId);
        $field->setDatatype($data->datatype);
        $field->setExampleValue($data->exampleValue);
        $field->setUnit($data->unit);
        $field->setJsonldId($data->jsonldId);
        $field->setJsonldPath($data->jsonldPath);
        $field->setOntologyTerms($data->ontologyTerms);
        $field->setRawNode($data->rawNode);
        $field->setOrderIndex($data->orderIndex);
        $field->setMapped(false);

        return $field;
    }
}
