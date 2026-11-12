<?php

declare(strict_types=1);

namespace App\Command;

use App\Crosswalk\Export\CrosswalkJsonLdExporter;
use App\Crosswalk\Import\ElnRoCrateImporter;
use App\Crosswalk\Import\ExtractedFieldData;
use App\Entity\Account;
use App\Entity\CanonicalForm;
use App\Entity\CanonicalFormField;
use App\Entity\CommonDataElement;
use App\Entity\ExternalTemplate;
use App\Entity\ExtractedTemplateField;
use App\Entity\FieldCrosswalk;
use App\Entity\TemplateFormCrosswalk;
use App\Entity\User;
use App\Enum\CanonicalFormStatus;
use App\Enum\CdeSource;
use App\Enum\CdeStatus;
use App\Enum\CrosswalkMappingType;
use App\Enum\ExternalTemplateFormat;
use App\Enum\ExternalTemplateSource;
use App\Enum\MappingStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Builds the ELN.community demo crosswalk entirely offline from an embedded
 * RO-Crate: imports the template, extracts fields, creates the local Metadatapp
 * CDEs, maps fields -> CDEs (accepted), and is ready for enriched JSON-LD export.
 */
#[AsCommand(
    name: 'app:crosswalk:demo-seed',
    description: 'Seeds the ELN.community demo crosswalk (offline) for the Template Crosswalk Studio.',
)]
final class CrosswalkDemoSeedCommand extends Command
{
    private const DEMO_RECORD_URL = 'https://eln.community/record/019bff9f-df44-71a0-9d3b-11a62730d34c';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ElnRoCrateImporter $importer,
        private readonly CrosswalkJsonLdExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding ELN-CDE demo crosswalk (offline)');

        $user = $this->entityManager->getRepository(User::class)->findOneBy([]);
        $account = $user?->getAccount();
        if (null === $user || null === $account) {
            $io->error('No user/account found. Load base fixtures first.');

            return Command::FAILURE;
        }

        $parsed = $this->importer->parseGraph($this->embeddedRoCrate());

        // 1. ExternalTemplate + extracted fields.
        $template = new ExternalTemplate();
        $template->setSource(ExternalTemplateSource::ELN_COMMUNITY);
        $template->setFormat(ExternalTemplateFormat::ELN_RO_CRATE);
        $template->setTitle($parsed->title ?? 'ELN.community demo record');
        $template->setDescription($parsed->description);
        $template->setVersion($parsed->version);
        $template->setExternalUrl(self::DEMO_RECORD_URL);
        $template->setRoCrateMetadata($parsed->metadataJson);
        $template->setRawPayload(['rootDataset' => $parsed->rootDataset]);
        $template->setFileHash($parsed->fileHash);
        $template->setUser($user);
        $template->setAccount($account);

        $fieldsByLabel = [];
        $order = 0;
        foreach ($parsed->extractedFields as $data) {
            $field = $this->buildExtractedField($data);
            $template->addExtractedField($field);
            $fieldsByLabel[$this->key($data->label)] = $field;
            $order = max($order, $data->orderIndex + 1);
        }

        // Ensure the dataset creator is represented as an extracted field even
        // though Person nodes are not auto-extracted as crosswalk fields.
        if (!isset($fieldsByLabel['adalovelace'])) {
            $creator = new ExtractedTemplateField();
            $creator->setLabel('Ada Lovelace');
            $creator->setDatatype('string');
            $creator->setExampleValue('Ada Lovelace');
            $creator->setJsonldId('#person-ada-lovelace');
            $creator->setJsonldPath(self::DEMO_RECORD_URL . '/person[Ada Lovelace]');
            $creator->setOrderIndex($order);
            $creator->setRawNode(['@id' => '#person-ada-lovelace', '@type' => 'Person', 'name' => 'Ada Lovelace']);
            $template->addExtractedField($creator);
            $fieldsByLabel['adalovelace'] = $creator;
        }
        $this->entityManager->persist($template);

        // 2. Local Metadatapp CDEs.
        $cdes = $this->seedCdes($account);

        // 3. Canonical form + fields.
        $canonicalForm = new CanonicalForm();
        $canonicalForm->setTitle('Chemical Sample Metadata');
        $canonicalForm->setDescription('Canonical form linking ELN sample exports to Metadatapp CDEs.');
        $canonicalForm->setDomain('chemistry');
        $canonicalForm->setStatus(CanonicalFormStatus::ACTIVE);
        $canonicalForm->setAccount($account);

        $order = 0;
        $canonicalFieldsByKey = [];
        foreach ($cdes as $localKey => $cde) {
            $cff = new CanonicalFormField();
            $cff->setLocalKey($localKey);
            $cff->setLabel($cde->getLabel());
            $cff->setDescription($cde->getDefinition());
            $cff->setDatatype($cde->getDatatype());
            $cff->setUnitConstraint($cde->getUnitConstraint());
            $cff->setOrderIndex($order++);
            $canonicalForm->addField($cff);
            $canonicalFieldsByKey[$localKey] = $cff;
        }
        $this->entityManager->persist($canonicalForm);

        // 4. Crosswalk + accepted field crosswalks.
        $crosswalk = new TemplateFormCrosswalk();
        $crosswalk->setExternalTemplate($template);
        $crosswalk->setCanonicalForm($canonicalForm);
        $crosswalk->setMappingType(CrosswalkMappingType::CLOSE);
        $crosswalk->setMappingStatus(MappingStatus::ACCEPTED);
        $crosswalk->setReviewedBy($user);
        $crosswalk->setReviewedAt(new \DateTimeImmutable());
        $crosswalk->setNotes('Demo crosswalk seeded offline from the ELN.community sample record.');
        $crosswalk->setAccount($account);

        // Map the extracted fields we recognise onto their CDEs.
        $mapping = [
            'molecularweight' => 'molecular_weight',
            'sampleidentifier' => 'sample_identifier',
            'massspectrometry' => 'measurement_technique',
            'brukermicrotof' => 'instrument',
            'ccby40' => 'license',
            'adalovelace' => 'dataset_creator',
            'samplecsv' => 'file_format',
        ];

        foreach ($mapping as $fieldKey => $cdeKey) {
            $elnField = $fieldsByLabel[$fieldKey] ?? null;
            $cde = $cdes[$cdeKey] ?? null;
            if (null === $elnField || null === $cde) {
                continue;
            }

            $fc = new FieldCrosswalk();
            $fc->setElnField($elnField);
            $fc->setElnJsonldPath($elnField->getJsonldPath());
            $fc->setElnPropertyId($elnField->getPropertyId());
            $fc->setCde($cde);
            $fc->setCanonicalFormField($canonicalFieldsByKey[$cdeKey] ?? null);
            $fc->setMappingType('molecular_weight' === $cdeKey ? CrosswalkMappingType::EXACT : CrosswalkMappingType::CLOSE);
            $fc->setMappingStatus(MappingStatus::ACCEPTED);
            $fc->setConfidence('molecular_weight' === $cdeKey ? 0.94 : 0.8);
            $crosswalk->addFieldCrosswalk($fc);
            $elnField->setMapped(true);
        }

        $this->entityManager->persist($crosswalk);
        $this->entityManager->flush();

        $export = $this->exporter->exportEnrichedRoCrate($crosswalk);
        $addedNodes = \count($export['@graph'] ?? []) - \count($parsed->metadataJson['@graph'] ?? []);

        $io->success(\sprintf(
            'Seeded crosswalk %s: %d extracted fields, %d CDEs, %d field mappings, +%d enriched JSON-LD nodes.',
            $crosswalk->getId()?->toRfc4122() ?? 'unknown',
            \count($parsed->extractedFields),
            \count($cdes),
            $crosswalk->getFieldCrosswalks()->count(),
            $addedNodes,
        ));

        return Command::SUCCESS;
    }

    private function buildExtractedField(ExtractedFieldData $data): ExtractedTemplateField
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

        return $field;
    }

    /**
     * @return array<string, CommonDataElement>
     */
    private function seedCdes(Account $account): array
    {
        $definitions = [
            'molecular_weight' => ['Molecular weight', 'The mass of one mole of a substance.', 'decimal', 'g/mol', []],
            'sample_identifier' => ['Sample identifier', 'A unique identifier assigned to a physical sample.', 'string', null, []],
            'measurement_technique' => ['Measurement technique', 'The analytical technique used to acquire the measurement.', 'string', null, [
                ['value' => 'Mass spectrometry', 'ontologyTerm' => 'http://purl.obolibrary.org/obo/CHMO_0000470'],
                ['value' => 'NMR spectroscopy', 'ontologyTerm' => 'http://purl.obolibrary.org/obo/CHMO_0000591'],
            ]],
            'instrument' => ['Instrument', 'The instrument used to perform the measurement.', 'string', null, []],
            'license' => ['License', 'The license under which the dataset is released.', 'string', null, []],
            'dataset_creator' => ['Dataset creator', 'The person or organisation that created the dataset.', 'string', null, []],
            'file_format' => ['File format', 'The encoding format of a data file.', 'string', null, []],
        ];

        $cdes = [];
        foreach ($definitions as $localKey => [$label, $definition, $datatype, $unit, $permissible]) {
            $cde = new CommonDataElement();
            $cde->setSource(CdeSource::METADATAPP);
            $cde->setLabel($label);
            $cde->setDefinition($definition);
            $cde->setDatatype($datatype);
            $cde->setUnitConstraint($unit);
            $cde->setPermissibleValues($permissible);
            $cde->setStatus(CdeStatus::ENDORSED);
            $cde->setVersion('1.0.0');
            $cde->setLocalKey($localKey);
            $cde->setProvenance(['provider' => 'metadatapp', 'seed' => 'crosswalk-demo']);
            $cde->setAccount($account);
            if ('measurement_technique' === $localKey) {
                $cde->setOntologyMappings([['iri' => 'http://purl.obolibrary.org/obo/CHMO_0000470', 'label' => 'Mass spectrometry', 'source' => 'CHMO']]);
            }
            $this->entityManager->persist($cde);
            $cdes[$localKey] = $cde;
        }

        return $cdes;
    }

    private function key(string $label): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($label)) ?? strtolower($label);
    }

    /**
     * @return array<string, mixed>
     */
    private function embeddedRoCrate(): array
    {
        $path = \dirname(__DIR__, 2) . '/tests/Fixtures/eln/ro-crate-metadata.json';
        $json = @file_get_contents($path);
        if (false !== $json) {
            $decoded = json_decode($json, true);
            if (\is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        }

        // Inline fallback so the demo never depends on the test fixture file.
        return [
            '@context' => 'https://w3id.org/ro/crate/1.1/context',
            '@graph' => [
                ['@id' => 'ro-crate-metadata.json', '@type' => 'CreativeWork', 'about' => ['@id' => './']],
                [
                    '@id' => './',
                    '@type' => 'Dataset',
                    'name' => 'ELN.community demo sample record',
                    'description' => 'Offline demo ELN export.',
                    'version' => '1.0.0',
                    'url' => self::DEMO_RECORD_URL,
                ],
                ['@id' => '#molecularWeight', '@type' => 'PropertyValue', 'name' => 'molecularWeight', 'propertyID' => 'molecularWeight', 'value' => '128.12592', 'unitCode' => 'g/mol', 'additionalType' => 'decimal'],
                ['@id' => '#sampleId', '@type' => 'PropertyValue', 'name' => 'sampleIdentifier', 'propertyID' => 'sampleIdentifier', 'value' => 'SMP-2026-0042', 'additionalType' => 'string'],
                ['@id' => '#measurementTechnique', '@type' => 'DefinedTerm', 'name' => 'Mass spectrometry', 'termCode' => 'http://purl.obolibrary.org/obo/CHMO_0000470'],
                ['@id' => '#instrument', '@type' => 'CreativeWork', 'name' => 'Bruker microTOF'],
                ['@id' => 'data/sample.csv', '@type' => 'File', 'name' => 'sample.csv', 'encodingFormat' => 'text/csv'],
                ['@id' => 'https://creativecommons.org/licenses/by/4.0/', '@type' => 'CreativeWork', 'name' => 'CC BY 4.0'],
                ['@id' => '#person-ada-lovelace', '@type' => 'Person', 'name' => 'Ada Lovelace'],
            ],
        ];
    }
}
