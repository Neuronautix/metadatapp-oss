<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Apps\Cedar\Export;

use App\ConnectedApps\Apps\Cedar\Export\CanonicalFormCedarTemplateBuilder;
use App\Entity\CanonicalForm;
use App\Entity\CanonicalFormField;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CanonicalFormCedarTemplateBuilderTest extends TestCase
{
    private function field(string $localKey, string $label, int $order, ?string $datatype = null, bool $required = false): CanonicalFormField
    {
        return (new CanonicalFormField())
            ->setLocalKey($localKey)
            ->setLabel($label)
            ->setDatatype($datatype)
            ->setRequired($required)
            ->setOrderIndex($order)
        ;
    }

    private function form(): CanonicalForm
    {
        $form = (new CanonicalForm())
            ->setTitle('Housing & Care Metadata')
            ->setDescription('Minimal HCM metadata form.')
            ->setVersion('1.2.0')
        ;

        // Added out of order to prove the builder sorts by orderIndex.
        $form->addField($this->field('weight_g', 'Body weight', 1, 'number', true));
        $form->addField($this->field('strain', 'Strain', 0, 'string', true));
        $form->addField(
            $this->field('housing', 'Housing type', 2, 'string')
                ->setAllowedValues([['label' => 'Individual'], ['label' => 'Group']])
        );

        return $form;
    }

    #[Test]
    public function buildsACedarTemplateEnvelope(): void
    {
        $template = (new CanonicalFormCedarTemplateBuilder())->build($this->form());

        self::assertNull($template['@id'], 'CEDAR mints the @id, so it must be nulled on submission.');
        self::assertSame('https://schema.metadatacenter.org/core/Template', $template['@type']);
        self::assertSame('Housing & Care Metadata', $template['schema:name']);
        self::assertSame('Minimal HCM metadata form.', $template['schema:description']);
        self::assertSame('1.2.0', $template['pav:version']);
        self::assertSame('object', $template['type']);
        self::assertFalse($template['additionalProperties']);
        self::assertArrayHasKey('schema', $template['@context']);
    }

    #[Test]
    public function ordersFieldsByOrderIndexInUiAndProperties(): void
    {
        $template = (new CanonicalFormCedarTemplateBuilder())->build($this->form());

        self::assertSame(['strain', 'weight_g', 'housing'], $template['_ui']['order']);
        self::assertSame('Strain', $template['_ui']['propertyLabels']['strain']);
        foreach (['strain', 'weight_g', 'housing'] as $key) {
            self::assertArrayHasKey($key, $template['properties']);
            self::assertSame('https://schema.metadatacenter.org/core/TemplateField', $template['properties'][$key]['@type']);
        }
    }

    #[Test]
    public function requiredFieldsArePromotedToTheRequiredList(): void
    {
        $template = (new CanonicalFormCedarTemplateBuilder())->build($this->form());

        self::assertContains('strain', $template['required']);
        self::assertContains('weight_g', $template['required']);
        self::assertNotContains('housing', $template['required']);
    }

    #[Test]
    public function mapsDatatypesToCedarInputTypes(): void
    {
        $template = (new CanonicalFormCedarTemplateBuilder())->build($this->form());

        self::assertSame('numeric', $template['properties']['weight_g']['_ui']['inputType']);
        self::assertSame('textfield', $template['properties']['strain']['_ui']['inputType']);
        // A field with permissible values becomes a single-choice list.
        self::assertSame('radio', $template['properties']['housing']['_ui']['inputType']);
    }

    #[Test]
    public function permissibleValuesBecomeLiteralValueConstraints(): void
    {
        $template = (new CanonicalFormCedarTemplateBuilder())->build($this->form());

        $constraints = $template['properties']['housing']['_valueConstraints'];
        self::assertSame([['label' => 'Individual'], ['label' => 'Group']], $constraints['literals']);
        self::assertFalse($constraints['requiredValue']);

        self::assertTrue($template['properties']['strain']['_valueConstraints']['requiredValue']);
    }

    #[Test]
    public function sanitisesAndDeduplicatesPropertyKeys(): void
    {
        $form = (new CanonicalForm())->setTitle('Edge cases')->setVersion('1.0.0');
        $form->addField($this->field('weight (g)', 'Weight', 0));
        $form->addField($this->field('weight (g)', 'Weight again', 1));

        $template = (new CanonicalFormCedarTemplateBuilder())->build($form);

        self::assertSame(['weight_g', 'weight_g_2'], array_keys($template['properties']));
    }
}
