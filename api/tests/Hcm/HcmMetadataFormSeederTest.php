<?php

declare(strict_types=1);

namespace App\Tests\Hcm;

use App\DataFixtures\Factory\AccountFactory;
use App\Entity\Account;
use App\Entity\CanonicalForm;
use App\Entity\CanonicalFormField;
use App\Entity\CommonDataElement;
use App\Hcm\HcmMetadataFormSeeder;
use App\Repository\CanonicalFormRepository;
use App\Repository\CommonDataElementRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HcmMetadataFormSeederTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private const DOMAIN = 'home-cage-monitoring';
    private const EXPECTED_SECTIONS = 8;
    private const EXPECTED_FIELDS = 42;

    #[Test]
    public function itSeedsTheCanonicalFormWithSectionsFieldsAndCdes(): void
    {
        self::bootKernel();
        $account = AccountFactory::createOne();

        $summary = $this->seeder()->seed($account);

        self::assertTrue($summary->created);
        self::assertSame(self::EXPECTED_SECTIONS, $summary->sections);
        self::assertSame(self::EXPECTED_FIELDS, $summary->fields);
        self::assertSame(self::EXPECTED_FIELDS, $summary->cdes);

        $form = $this->canonicalFormRepository()->findOneBy([
            'account' => $account,
            'domain' => self::DOMAIN,
        ]);
        self::assertInstanceOf(CanonicalForm::class, $form);
        self::assertSame('HCM Minimal Metadata Form', $form->getTitle());
        self::assertCount(self::EXPECTED_SECTIONS, $form->getSections());
        self::assertCount(self::EXPECTED_FIELDS, $form->getFields());

        // The ISA mapping is preserved on the form's source links.
        self::assertArrayHasKey('isaMapping', $form->getSourceLinks());

        // Every canonical field has a matching CDE addressed by localKey.
        foreach ($form->getFields() as $field) {
            self::assertInstanceOf(CanonicalFormField::class, $field);
            self::assertSame('cde:' . $field->getLocalKey(), $field->getSourceFieldReference());
            self::assertNotNull($field->getSection());

            $cde = $this->commonDataElementRepository()->findOneByLocalKey($account, $field->getLocalKey());
            self::assertInstanceOf(CommonDataElement::class, $cde);
            self::assertSame($field->getLabel(), $cde->getLabel());
        }
    }

    #[Test]
    public function deviceFieldsUseHcmoSourceAndCarryOntologyMappings(): void
    {
        self::bootKernel();
        $account = AccountFactory::createOne();

        $this->seeder()->seed($account);

        $deviceModel = $this->commonDataElementRepository()->findOneByLocalKey($account, 'device_model');
        self::assertInstanceOf(CommonDataElement::class, $deviceModel);
        self::assertSame('hcmo', $deviceModel->getSource()->value);
        self::assertNotEmpty($deviceModel->getOntologyMappings());

        $activityMetric = $this->commonDataElementRepository()->findOneByLocalKey($account, 'activity_metric_definition');
        self::assertInstanceOf(CommonDataElement::class, $activityMetric);
        self::assertSame('mbo', $activityMetric->getSource()->value);

        // A default-source field falls back to the cdeDefaults source.
        $studyIdentifier = $this->commonDataElementRepository()->findOneByLocalKey($account, 'study_identifier');
        self::assertInstanceOf(CommonDataElement::class, $studyIdentifier);
        self::assertSame('metadatapp', $studyIdentifier->getSource()->value);
        self::assertSame('endorsed', $studyIdentifier->getStatus()->value);
    }

    #[Test]
    public function seedingTwiceIsIdempotent(): void
    {
        self::bootKernel();
        $account = AccountFactory::createOne();

        $first = $this->seeder()->seed($account);
        self::assertTrue($first->created);

        $second = $this->seeder()->seed($account);
        self::assertFalse($second->created);

        $forms = $this->canonicalFormRepository()->findBy([
            'account' => $account,
            'domain' => self::DOMAIN,
        ]);
        self::assertCount(1, $forms);

        $cdes = $this->commonDataElementRepository()->findBy(['account' => $account]);
        self::assertCount(self::EXPECTED_FIELDS, $cdes);
    }

    private function seeder(): HcmMetadataFormSeeder
    {
        /** @var HcmMetadataFormSeeder $seeder */
        $seeder = self::getContainer()->get(HcmMetadataFormSeeder::class);

        return $seeder;
    }

    private function canonicalFormRepository(): CanonicalFormRepository
    {
        /** @var CanonicalFormRepository $repository */
        $repository = self::getContainer()->get(CanonicalFormRepository::class);

        return $repository;
    }

    private function commonDataElementRepository(): CommonDataElementRepository
    {
        /** @var CommonDataElementRepository $repository */
        $repository = self::getContainer()->get(CommonDataElementRepository::class);

        return $repository;
    }
}
