<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the ELN-CDE Crosswalk ("Template Crosswalk Studio") module tables.
 */
final class Version20260617120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create crosswalk_ tables for the ELN-CDE Crosswalk (Template Crosswalk Studio) module.';
    }

    public function up(Schema $schema): void
    {
        // crosswalk_external_template
        $this->addSql('CREATE TABLE crosswalk_external_template (id UUID NOT NULL, account_id UUID NOT NULL, user_id UUID NOT NULL, source VARCHAR(255) NOT NULL, external_url VARCHAR(1024) DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(100) DEFAULT NULL, format VARCHAR(255) NOT NULL, ro_crate_metadata JSON DEFAULT NULL, raw_payload JSON DEFAULT NULL, file_hash VARCHAR(128) DEFAULT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_EXTERNAL_TEMPLATE_ACCOUNT ON crosswalk_external_template (account_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_EXTERNAL_TEMPLATE_USER ON crosswalk_external_template (user_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_external_template.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_external_template.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_external_template.user_id IS \'(DC2Type:uuid)\'');

        // crosswalk_extracted_template_field
        $this->addSql('CREATE TABLE crosswalk_extracted_template_field (id UUID NOT NULL, external_template_id UUID NOT NULL, jsonld_id VARCHAR(512) DEFAULT NULL, jsonld_path VARCHAR(1024) DEFAULT NULL, label VARCHAR(255) NOT NULL, property_id VARCHAR(512) DEFAULT NULL, datatype VARCHAR(100) DEFAULT NULL, example_value TEXT DEFAULT NULL, unit VARCHAR(100) DEFAULT NULL, ontology_terms JSON NOT NULL, raw_node JSON NOT NULL, order_index INT DEFAULT 0 NOT NULL, mapped BOOLEAN DEFAULT false NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_EXTRACTED_FIELD_TEMPLATE ON crosswalk_extracted_template_field (external_template_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_extracted_template_field.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_extracted_template_field.external_template_id IS \'(DC2Type:uuid)\'');

        // crosswalk_external_form
        $this->addSql('CREATE TABLE crosswalk_external_form (id UUID NOT NULL, account_id UUID NOT NULL, user_id UUID NOT NULL, source VARCHAR(255) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, external_url VARCHAR(1024) DEFAULT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, version VARCHAR(100) DEFAULT NULL, raw_payload JSON DEFAULT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_EXTERNAL_FORM_ACCOUNT ON crosswalk_external_form (account_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_EXTERNAL_FORM_USER ON crosswalk_external_form (user_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_external_form.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_external_form.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_external_form.user_id IS \'(DC2Type:uuid)\'');

        // crosswalk_canonical_form
        $this->addSql('CREATE TABLE crosswalk_canonical_form (id UUID NOT NULL, account_id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, domain VARCHAR(255) DEFAULT NULL, version VARCHAR(50) DEFAULT \'1.0.0\' NOT NULL, status VARCHAR(255) NOT NULL, source_links JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_CANONICAL_FORM_ACCOUNT ON crosswalk_canonical_form (account_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_canonical_form.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_canonical_form.account_id IS \'(DC2Type:uuid)\'');

        // crosswalk_canonical_form_section
        $this->addSql('CREATE TABLE crosswalk_canonical_form_section (id UUID NOT NULL, canonical_form_id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, order_index INT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_CANONICAL_SECTION_FORM ON crosswalk_canonical_form_section (canonical_form_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_canonical_form_section.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_canonical_form_section.canonical_form_id IS \'(DC2Type:uuid)\'');

        // crosswalk_canonical_form_field
        $this->addSql('CREATE TABLE crosswalk_canonical_form_field (id UUID NOT NULL, canonical_form_id UUID NOT NULL, section_id UUID DEFAULT NULL, local_key VARCHAR(255) NOT NULL, label VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, datatype VARCHAR(100) DEFAULT NULL, required BOOLEAN DEFAULT false NOT NULL, unit_constraint VARCHAR(100) DEFAULT NULL, allowed_values JSON NOT NULL, ontology_mappings JSON NOT NULL, jsonld_path VARCHAR(1024) DEFAULT NULL, source_field_reference VARCHAR(1024) DEFAULT NULL, order_index INT DEFAULT 0 NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_CANONICAL_FIELD_FORM ON crosswalk_canonical_form_field (canonical_form_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_CANONICAL_FIELD_SECTION ON crosswalk_canonical_form_field (section_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_canonical_form_field.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_canonical_form_field.canonical_form_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_canonical_form_field.section_id IS \'(DC2Type:uuid)\'');

        // crosswalk_common_data_element
        $this->addSql('CREATE TABLE crosswalk_common_data_element (id UUID NOT NULL, account_id UUID NOT NULL, source VARCHAR(255) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, external_url VARCHAR(1024) DEFAULT NULL, version VARCHAR(100) DEFAULT NULL, label VARCHAR(255) NOT NULL, definition TEXT DEFAULT NULL, question_text TEXT DEFAULT NULL, datatype VARCHAR(100) DEFAULT NULL, unit_constraint VARCHAR(100) DEFAULT NULL, permissible_values JSON NOT NULL, ontology_mappings JSON NOT NULL, provenance JSON NOT NULL, raw_payload JSON DEFAULT NULL, status VARCHAR(255) NOT NULL, local_key VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_CDE_ACCOUNT ON crosswalk_common_data_element (account_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_common_data_element.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_common_data_element.account_id IS \'(DC2Type:uuid)\'');

        // crosswalk_template_form_crosswalk
        $this->addSql('CREATE TABLE crosswalk_template_form_crosswalk (id UUID NOT NULL, account_id UUID NOT NULL, external_template_id UUID NOT NULL, external_form_id UUID DEFAULT NULL, canonical_form_id UUID NOT NULL, reviewed_by_id UUID DEFAULT NULL, mapping_type VARCHAR(255) NOT NULL, mapping_status VARCHAR(255) NOT NULL, confidence DOUBLE PRECISION DEFAULT NULL, evidence TEXT DEFAULT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, version VARCHAR(50) DEFAULT \'1.0.0\' NOT NULL, notes TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_TFC_ACCOUNT ON crosswalk_template_form_crosswalk (account_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_TFC_TEMPLATE ON crosswalk_template_form_crosswalk (external_template_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_TFC_FORM ON crosswalk_template_form_crosswalk (external_form_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_TFC_CANONICAL_FORM ON crosswalk_template_form_crosswalk (canonical_form_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_TFC_REVIEWED_BY ON crosswalk_template_form_crosswalk (reviewed_by_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_template_form_crosswalk.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_template_form_crosswalk.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_template_form_crosswalk.external_template_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_template_form_crosswalk.external_form_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_template_form_crosswalk.canonical_form_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_template_form_crosswalk.reviewed_by_id IS \'(DC2Type:uuid)\'');

        // crosswalk_field_crosswalk
        $this->addSql('CREATE TABLE crosswalk_field_crosswalk (id UUID NOT NULL, template_form_crosswalk_id UUID NOT NULL, canonical_form_field_id UUID DEFAULT NULL, cde_id UUID DEFAULT NULL, eln_field_id UUID DEFAULT NULL, eln_jsonld_path VARCHAR(1024) DEFAULT NULL, eln_property_id VARCHAR(512) DEFAULT NULL, cde_form_field_id VARCHAR(255) DEFAULT NULL, mapping_type VARCHAR(255) NOT NULL, mapping_status VARCHAR(255) NOT NULL, confidence DOUBLE PRECISION DEFAULT NULL, evidence TEXT DEFAULT NULL, datatype_mapping JSON NOT NULL, unit_mapping JSON NOT NULL, value_mapping JSON NOT NULL, curator_notes TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_FC_TFC ON crosswalk_field_crosswalk (template_form_crosswalk_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_FC_CANONICAL_FIELD ON crosswalk_field_crosswalk (canonical_form_field_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_FC_CDE ON crosswalk_field_crosswalk (cde_id)');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_FC_ELN_FIELD ON crosswalk_field_crosswalk (eln_field_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_field_crosswalk.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_field_crosswalk.template_form_crosswalk_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_field_crosswalk.canonical_form_field_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_field_crosswalk.cde_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_field_crosswalk.eln_field_id IS \'(DC2Type:uuid)\'');

        // crosswalk_value_crosswalk
        $this->addSql('CREATE TABLE crosswalk_value_crosswalk (id UUID NOT NULL, field_crosswalk_id UUID NOT NULL, source_value VARCHAR(512) DEFAULT NULL, target_value VARCHAR(512) DEFAULT NULL, target_code VARCHAR(255) DEFAULT NULL, ontology_term VARCHAR(512) DEFAULT NULL, mapping_type VARCHAR(255) NOT NULL, mapping_status VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CROSSWALK_VC_FIELD_CROSSWALK ON crosswalk_value_crosswalk (field_crosswalk_id)');
        $this->addSql('COMMENT ON COLUMN crosswalk_value_crosswalk.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN crosswalk_value_crosswalk.field_crosswalk_id IS \'(DC2Type:uuid)\'');

        // Foreign keys
        $this->addSql('ALTER TABLE crosswalk_external_template ADD CONSTRAINT FK_CROSSWALK_EXTERNAL_TEMPLATE_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_external_template ADD CONSTRAINT FK_CROSSWALK_EXTERNAL_TEMPLATE_USER FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE crosswalk_extracted_template_field ADD CONSTRAINT FK_CROSSWALK_EXTRACTED_FIELD_TEMPLATE FOREIGN KEY (external_template_id) REFERENCES crosswalk_external_template (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE crosswalk_external_form ADD CONSTRAINT FK_CROSSWALK_EXTERNAL_FORM_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_external_form ADD CONSTRAINT FK_CROSSWALK_EXTERNAL_FORM_USER FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE crosswalk_canonical_form ADD CONSTRAINT FK_CROSSWALK_CANONICAL_FORM_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE crosswalk_canonical_form_section ADD CONSTRAINT FK_CROSSWALK_CANONICAL_SECTION_FORM FOREIGN KEY (canonical_form_id) REFERENCES crosswalk_canonical_form (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE crosswalk_canonical_form_field ADD CONSTRAINT FK_CROSSWALK_CANONICAL_FIELD_FORM FOREIGN KEY (canonical_form_id) REFERENCES crosswalk_canonical_form (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_canonical_form_field ADD CONSTRAINT FK_CROSSWALK_CANONICAL_FIELD_SECTION FOREIGN KEY (section_id) REFERENCES crosswalk_canonical_form_section (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE crosswalk_common_data_element ADD CONSTRAINT FK_CROSSWALK_CDE_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk ADD CONSTRAINT FK_CROSSWALK_TFC_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk ADD CONSTRAINT FK_CROSSWALK_TFC_TEMPLATE FOREIGN KEY (external_template_id) REFERENCES crosswalk_external_template (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk ADD CONSTRAINT FK_CROSSWALK_TFC_FORM FOREIGN KEY (external_form_id) REFERENCES crosswalk_external_form (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk ADD CONSTRAINT FK_CROSSWALK_TFC_CANONICAL_FORM FOREIGN KEY (canonical_form_id) REFERENCES crosswalk_canonical_form (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk ADD CONSTRAINT FK_CROSSWALK_TFC_REVIEWED_BY FOREIGN KEY (reviewed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE crosswalk_field_crosswalk ADD CONSTRAINT FK_CROSSWALK_FC_TFC FOREIGN KEY (template_form_crosswalk_id) REFERENCES crosswalk_template_form_crosswalk (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_field_crosswalk ADD CONSTRAINT FK_CROSSWALK_FC_CANONICAL_FIELD FOREIGN KEY (canonical_form_field_id) REFERENCES crosswalk_canonical_form_field (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_field_crosswalk ADD CONSTRAINT FK_CROSSWALK_FC_CDE FOREIGN KEY (cde_id) REFERENCES crosswalk_common_data_element (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE crosswalk_field_crosswalk ADD CONSTRAINT FK_CROSSWALK_FC_ELN_FIELD FOREIGN KEY (eln_field_id) REFERENCES crosswalk_extracted_template_field (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE crosswalk_value_crosswalk ADD CONSTRAINT FK_CROSSWALK_VC_FIELD_CROSSWALK FOREIGN KEY (field_crosswalk_id) REFERENCES crosswalk_field_crosswalk (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE crosswalk_value_crosswalk DROP CONSTRAINT FK_CROSSWALK_VC_FIELD_CROSSWALK');

        $this->addSql('ALTER TABLE crosswalk_field_crosswalk DROP CONSTRAINT FK_CROSSWALK_FC_TFC');
        $this->addSql('ALTER TABLE crosswalk_field_crosswalk DROP CONSTRAINT FK_CROSSWALK_FC_CANONICAL_FIELD');
        $this->addSql('ALTER TABLE crosswalk_field_crosswalk DROP CONSTRAINT FK_CROSSWALK_FC_CDE');
        $this->addSql('ALTER TABLE crosswalk_field_crosswalk DROP CONSTRAINT FK_CROSSWALK_FC_ELN_FIELD');

        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk DROP CONSTRAINT FK_CROSSWALK_TFC_ACCOUNT');
        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk DROP CONSTRAINT FK_CROSSWALK_TFC_TEMPLATE');
        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk DROP CONSTRAINT FK_CROSSWALK_TFC_FORM');
        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk DROP CONSTRAINT FK_CROSSWALK_TFC_CANONICAL_FORM');
        $this->addSql('ALTER TABLE crosswalk_template_form_crosswalk DROP CONSTRAINT FK_CROSSWALK_TFC_REVIEWED_BY');

        $this->addSql('ALTER TABLE crosswalk_common_data_element DROP CONSTRAINT FK_CROSSWALK_CDE_ACCOUNT');

        $this->addSql('ALTER TABLE crosswalk_canonical_form_field DROP CONSTRAINT FK_CROSSWALK_CANONICAL_FIELD_FORM');
        $this->addSql('ALTER TABLE crosswalk_canonical_form_field DROP CONSTRAINT FK_CROSSWALK_CANONICAL_FIELD_SECTION');

        $this->addSql('ALTER TABLE crosswalk_canonical_form_section DROP CONSTRAINT FK_CROSSWALK_CANONICAL_SECTION_FORM');

        $this->addSql('ALTER TABLE crosswalk_canonical_form DROP CONSTRAINT FK_CROSSWALK_CANONICAL_FORM_ACCOUNT');

        $this->addSql('ALTER TABLE crosswalk_external_form DROP CONSTRAINT FK_CROSSWALK_EXTERNAL_FORM_ACCOUNT');
        $this->addSql('ALTER TABLE crosswalk_external_form DROP CONSTRAINT FK_CROSSWALK_EXTERNAL_FORM_USER');

        $this->addSql('ALTER TABLE crosswalk_extracted_template_field DROP CONSTRAINT FK_CROSSWALK_EXTRACTED_FIELD_TEMPLATE');

        $this->addSql('ALTER TABLE crosswalk_external_template DROP CONSTRAINT FK_CROSSWALK_EXTERNAL_TEMPLATE_ACCOUNT');
        $this->addSql('ALTER TABLE crosswalk_external_template DROP CONSTRAINT FK_CROSSWALK_EXTERNAL_TEMPLATE_USER');

        $this->addSql('DROP TABLE crosswalk_value_crosswalk');
        $this->addSql('DROP TABLE crosswalk_field_crosswalk');
        $this->addSql('DROP TABLE crosswalk_template_form_crosswalk');
        $this->addSql('DROP TABLE crosswalk_common_data_element');
        $this->addSql('DROP TABLE crosswalk_canonical_form_field');
        $this->addSql('DROP TABLE crosswalk_canonical_form_section');
        $this->addSql('DROP TABLE crosswalk_canonical_form');
        $this->addSql('DROP TABLE crosswalk_external_form');
        $this->addSql('DROP TABLE crosswalk_extracted_template_field');
        $this->addSql('DROP TABLE crosswalk_external_template');
    }
}
