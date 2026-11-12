<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\Factory\AccountFactory;
use App\DataFixtures\Factory\CanonicalFormFactory;
use App\DataFixtures\Factory\CommonDataElementFactory;
use App\DataFixtures\Factory\ExternalTemplateFactory;
use App\DataFixtures\Factory\TemplateFormCrosswalkFactory;
use App\DataFixtures\Factory\UserFactory;
use App\Entity\ExtractedTemplateField;
use App\Entity\FieldCrosswalk;
use App\Enum\CdeSource;
use App\Enum\MappingStatus;
use App\Security\Roles;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use function Zenstruck\Foundry\Persistence\save;

final class CrosswalkStudioControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    private const FIXTURE_ELN = __DIR__ . '/../Fixtures/eln/sample.eln';

    #[Test]
    public function importElnRequiresAuthentication(): void
    {
        static::createClient()->request('POST', '/api/external-templates/import-eln');

        $this->assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function importElnCreatesAnExternalTemplateWithExtractedFields(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/api/external-templates/import-eln', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'files' => [
                    'file' => $this->uploadedEln(),
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $payload = $response->toArray();

        self::assertSame('ELN.community demo sample record', $payload['title']);
        self::assertArrayHasKey('extractedFields', $payload);
        self::assertNotEmpty($payload['extractedFields']);

        $labels = array_map(static fn (array $f): ?string => $f['label'] ?? null, $payload['extractedFields']);
        self::assertContains('molecularWeight', $labels);
    }

    #[Test]
    public function importLocalCdeFromPayloadPersistsTheCde(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);

        $client = static::createClient();
        $client->loginUser($user);

        $response = $client->request('POST', '/api/cdes/import', [
            'json' => [
                'payload' => [
                    'cdes' => [
                        [
                            'source' => 'metadatapp',
                            'label' => 'Molecular weight',
                            'localKey' => 'molecular_weight',
                            'datatype' => 'decimal',
                            'unitConstraint' => 'g/mol',
                            'definition' => 'Mass of one mole of a substance.',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $payload = $response->toArray();

        self::assertCount(1, $payload);
        self::assertSame('Molecular weight', $payload[0]['label']);
        self::assertSame('molecular_weight', $payload[0]['localKey']);
    }

    #[Test]
    public function fieldCrosswalkCanBeMappedToACdeAndAccepted(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();

        $crosswalk = TemplateFormCrosswalkFactory::createOne(['account' => $account]);
        $template = $crosswalk->getExternalTemplate();

        $field = new ExtractedTemplateField();
        $field->setLabel('molecularWeight')->setDatatype('decimal')->setUnit('g/mol');
        $template->addExtractedField($field);

        $cde = CommonDataElementFactory::createOne([
            'account' => $account,
            'source' => CdeSource::METADATAPP,
            'label' => 'Molecular weight',
            'localKey' => 'molecular_weight',
            'datatype' => 'decimal',
            'unitConstraint' => 'g/mol',
        ]);

        $fieldCrosswalk = new FieldCrosswalk();
        $fieldCrosswalk->setCde($cde);
        $fieldCrosswalk->setElnField($field);
        $fieldCrosswalk->setMappingStatus(MappingStatus::SUGGESTED);
        $crosswalk->addFieldCrosswalk($fieldCrosswalk);

        // Cascade-persist the new extracted field + field crosswalk via their parents.
        save($template);
        save($crosswalk);

        $fieldCrosswalkId = $fieldCrosswalk->getId()?->toRfc4122();
        self::assertNotNull($fieldCrosswalkId);

        $client = static::createClient();
        $client->loginUser($user);
        $response = $client->request('POST', \sprintf('/api/field-crosswalks/%s/accept', $fieldCrosswalkId));

        $this->assertResponseIsSuccessful();
        $payload = $response->toArray();
        self::assertSame(MappingStatus::ACCEPTED->value, $payload['mappingStatus']);
    }

    #[Test]
    public function validateAndExportJsonLdEmitTheCdeAndCrosswalkNodes(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();

        $crosswalk = TemplateFormCrosswalkFactory::createOne([
            'account' => $account,
            'mappingStatus' => MappingStatus::ACCEPTED,
        ]);
        $template = $crosswalk->getExternalTemplate();

        $field = new ExtractedTemplateField();
        $field->setLabel('molecularWeight')
            ->setExampleValue('128.12592')
            ->setDatatype('decimal')
            ->setUnit('g/mol')
            ->setJsonldId('#molecularWeight');
        $template->addExtractedField($field);

        $cde = CommonDataElementFactory::createOne([
            'account' => $account,
            'source' => CdeSource::METADATAPP,
            'label' => 'Molecular weight',
            'localKey' => 'molecular_weight',
            'datatype' => 'decimal',
            'unitConstraint' => 'g/mol',
        ]);

        $fieldCrosswalk = new FieldCrosswalk();
        $fieldCrosswalk->setCde($cde);
        $fieldCrosswalk->setElnField($field);
        $fieldCrosswalk->setMappingStatus(MappingStatus::ACCEPTED);
        $crosswalk->addFieldCrosswalk($fieldCrosswalk);

        // Cascade-persist the new extracted field + field crosswalk via their parents.
        save($template);
        save($crosswalk);

        $crosswalkId = $crosswalk->getId()?->toRfc4122();
        self::assertNotNull($crosswalkId);

        // The firewall is stateless, so each request needs its own freshly
        // authenticated client (same pattern as CryoRecordTest).
        $authedRequest = function (string $method, string $uri) use ($user) {
            $client = static::createClient();
            $client->loginUser($user);

            return $client->request($method, $uri);
        };

        // --- validate -----------------------------------------------------
        $validateResponse = $authedRequest('POST', \sprintf('/api/template-crosswalks/%s/validate', $crosswalkId));
        self::assertSame(200, $validateResponse->getStatusCode(), $validateResponse->getContent(false));
        $report = $validateResponse->toArray();
        self::assertArrayHasKey('counts', $report);
        self::assertArrayHasKey('semanticCompletenessScore', $report);
        self::assertSame(1, $report['counts']['fieldsMappedToCdes']);

        // --- export-jsonld ------------------------------------------------
        $exportResponse = $authedRequest('POST', \sprintf('/api/template-crosswalks/%s/export-jsonld', $crosswalkId));
        self::assertSame(200, $exportResponse->getStatusCode(), $exportResponse->getContent(false));
        $graph = $exportResponse->toArray();

        $ids = array_map(
            static fn (array $node): mixed => $node['@id'] ?? null,
            $graph['@graph'],
        );

        self::assertContains('metadatapp:cde:molecular_weight', $ids);
        self::assertContains('#field-molecular-weight', $ids);
        // A first-class crosswalk node is present.
        self::assertNotEmpty(array_filter(
            $ids,
            static fn (mixed $id): bool => \is_string($id) && str_starts_with($id, 'metadatapp:crosswalk:'),
        ));
    }

    #[Test]
    public function anotherAccountIsForbiddenFromAValidateAction(): void
    {
        $owner = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $crosswalk = TemplateFormCrosswalkFactory::createOne(['account' => $owner->getAccount()]);

        $otherUser = UserFactory::createOne([
            'roles' => [Roles::ROLE_USER],
            'account' => AccountFactory::createOne(),
        ]);

        $client = static::createClient();
        $client->loginUser($otherUser);
        $client->request('POST', \sprintf('/api/template-crosswalks/%s/validate', $crosswalk->getId()?->toRfc4122()));

        $this->assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function externalTemplateAndCanonicalFormCanBeCreatedViaFactories(): void
    {
        $user = UserFactory::createOne(['roles' => [Roles::ROLE_USER]]);
        $account = $user->getAccount();

        $template = ExternalTemplateFactory::createOne([
            'account' => $account,
            'user' => $user,
            'title' => 'Crosswalk demo template',
        ]);
        $form = CanonicalFormFactory::createOne([
            'account' => $account,
            'title' => 'Canonical chemistry form',
            'domain' => 'chemistry',
        ]);

        self::assertNotNull($template->getId());
        self::assertNotNull($form->getId());
        self::assertSame('chemistry', $form->getDomain());
    }

    private function uploadedEln(): UploadedFile
    {
        // Copy to a temp path so the test client can consume it as a real upload.
        $tmp = sys_get_temp_dir() . '/crosswalk_sample_' . uniqid('', true) . '.eln';
        copy(self::FIXTURE_ELN, $tmp);

        return new UploadedFile($tmp, 'sample.eln', 'application/octet-stream', null, true);
    }
}
