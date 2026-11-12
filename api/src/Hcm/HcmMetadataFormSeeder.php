<?php

declare(strict_types=1);

namespace App\Hcm;

use App\Entity\Account;
use App\Entity\CanonicalForm;
use App\Entity\CanonicalFormField;
use App\Entity\CanonicalFormSection;
use App\Entity\CommonDataElement;
use App\Enum\CanonicalFormStatus;
use App\Enum\CdeSource;
use App\Enum\CdeStatus;
use App\Repository\CanonicalFormRepository;
use App\Repository\CommonDataElementRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Materialises the HCM Minimal Metadata Form from its canonical JSON definition
 * into CommonDataElement + CanonicalForm + CanonicalFormSection +
 * CanonicalFormField entities for a given account.
 *
 * The JSON file at resources/hcm/hcm-minimal-metadata-form.json is the single
 * source of truth. Seeding is idempotent: if a CanonicalForm with the form's
 * domain already exists for the account, seeding is skipped; CDEs are reused by
 * (account, localKey, source) when present.
 */
final class HcmMetadataFormSeeder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CanonicalFormRepository $canonicalForms,
        private readonly CommonDataElementRepository $commonDataElements,
    ) {
    }

    public function seed(Account $account): HcmMetadataFormSeedSummary
    {
        $definition = $this->loadDefinition();

        /** @var array<string, mixed> $form */
        $form = $definition['form'];
        $domain = (string) $form['domain'];

        $existing = $this->canonicalForms->findOneBy(['account' => $account, 'domain' => $domain]);
        if (null !== $existing) {
            return HcmMetadataFormSeedSummary::skipped();
        }

        /** @var array<string, mixed> $cdeDefaults */
        $cdeDefaults = $definition['cdeDefaults'];
        $defaultSource = CdeSource::from((string) $cdeDefaults['source']);
        $defaultStatus = CdeStatus::from((string) $cdeDefaults['status']);
        $defaultVersion = (string) $cdeDefaults['version'];

        $canonicalForm = new CanonicalForm();
        $canonicalForm->setTitle((string) $form['title']);
        $canonicalForm->setDescription((string) ($form['description'] ?? ''));
        $canonicalForm->setDomain($domain);
        $canonicalForm->setVersion((string) ($form['version'] ?? '1.0.0'));
        $canonicalForm->setStatus(CanonicalFormStatus::from((string) ($form['status'] ?? 'draft')));
        $canonicalForm->setSourceLinks([
            'key' => (string) $form['key'],
            'isaMapping' => $form['isaMapping'] ?? [],
        ]);
        $canonicalForm->setAccount($account);

        $cdeCount = 0;
        $sectionCount = 0;
        $fieldCount = 0;
        $fieldOrder = 0;

        /** @var array<int, array<string, mixed>> $sections */
        $sections = $definition['sections'];
        foreach ($sections as $sectionIndex => $sectionData) {
            $section = new CanonicalFormSection();
            $section->setTitle((string) $sectionData['title']);
            $section->setDescription((string) ($sectionData['description'] ?? ''));
            $section->setOrderIndex($sectionIndex);
            $canonicalForm->addSection($section);
            ++$sectionCount;

            $isaLevel = (string) ($sectionData['isaLevel'] ?? '');
            $isaNode = (string) ($sectionData['isaNode'] ?? '');

            /** @var array<int, array<string, mixed>> $fields */
            $fields = $sectionData['fields'];
            foreach ($fields as $fieldData) {
                $localKey = (string) $fieldData['localKey'];
                $source = isset($fieldData['source'])
                    ? CdeSource::from((string) $fieldData['source'])
                    : $defaultSource;

                /** @var array<int|string, mixed> $permissibleValues */
                $permissibleValues = $fieldData['permissibleValues'] ?? [];
                /** @var array<int|string, mixed> $ontologyMappings */
                $ontologyMappings = $fieldData['ontologyMappings'] ?? [];
                $datatype = isset($fieldData['datatype']) ? (string) $fieldData['datatype'] : null;
                $unitConstraint = isset($fieldData['unitConstraint']) ? (string) $fieldData['unitConstraint'] : null;
                $required = (bool) ($fieldData['required'] ?? false);

                $cde = $this->commonDataElements->findOneBy([
                    'account' => $account,
                    'localKey' => $localKey,
                    'source' => $source,
                ]);

                if (null === $cde) {
                    $cde = new CommonDataElement();
                    $cde->setSource($source);
                    $cde->setLabel((string) $fieldData['label']);
                    $cde->setDefinition((string) ($fieldData['definition'] ?? ''));
                    $cde->setQuestionText(isset($fieldData['questionText']) ? (string) $fieldData['questionText'] : null);
                    $cde->setDatatype($datatype);
                    $cde->setUnitConstraint($unitConstraint);
                    $cde->setPermissibleValues($permissibleValues);
                    $cde->setOntologyMappings($ontologyMappings);
                    $cde->setStatus($defaultStatus);
                    $cde->setVersion($defaultVersion);
                    $cde->setLocalKey($localKey);
                    $cde->setProvenance([
                        'provider' => 'metadatapp',
                        'seed' => 'hcm-minimal-metadata-form',
                        'isaLevel' => $isaLevel,
                        'isaNode' => $isaNode,
                    ]);
                    $cde->setAccount($account);
                    $this->entityManager->persist($cde);
                    ++$cdeCount;
                }

                $cff = new CanonicalFormField();
                $cff->setSection($section);
                $cff->setLocalKey($localKey);
                $cff->setLabel((string) $fieldData['label']);
                $cff->setDescription((string) ($fieldData['definition'] ?? ''));
                $cff->setDatatype($datatype);
                $cff->setRequired($required);
                $cff->setUnitConstraint($unitConstraint);
                $cff->setAllowedValues($permissibleValues);
                $cff->setOntologyMappings($ontologyMappings);
                $cff->setSourceFieldReference('cde:' . $localKey);
                $cff->setOrderIndex($fieldOrder++);
                $canonicalForm->addField($cff);
                ++$fieldCount;
            }
        }

        $this->entityManager->persist($canonicalForm);
        $this->entityManager->flush();

        return new HcmMetadataFormSeedSummary(true, $cdeCount, $sectionCount, $fieldCount);
    }

    /**
     * @return array{form: array<string, mixed>, cdeDefaults: array<string, mixed>, sections: array<int, array<string, mixed>>}
     */
    private function loadDefinition(): array
    {
        $path = \dirname(__DIR__, 2) . '/resources/hcm/hcm-minimal-metadata-form.json';
        $json = @file_get_contents($path);
        if (false === $json) {
            throw new \RuntimeException(\sprintf('HCM metadata form definition not found at "%s".', $path));
        }

        $decoded = json_decode($json, true);
        if (!\is_array($decoded)
            || !isset($decoded['form'], $decoded['cdeDefaults'], $decoded['sections'])
            || !\is_array($decoded['form'])
            || !\is_array($decoded['cdeDefaults'])
            || !\is_array($decoded['sections'])
        ) {
            throw new \RuntimeException(\sprintf('HCM metadata form definition at "%s" is invalid or malformed.', $path));
        }

        /** @var array{form: array<string, mixed>, cdeDefaults: array<string, mixed>, sections: array<int, array<string, mixed>>} $decoded */
        return $decoded;
    }
}
