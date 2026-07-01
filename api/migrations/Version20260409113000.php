<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill curation session tables after the legacy placeholder migration split.';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('import_session')) {
            return;
        }

        $this->addSql('CREATE TABLE curation_decision (id UUID NOT NULL, proposal_type VARCHAR(50) NOT NULL, proposal_id UUID NOT NULL, decision VARCHAR(50) NOT NULL, decided_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, session_id UUID NOT NULL, decided_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7C766F6A613FECDF ON curation_decision (session_id)');
        $this->addSql('CREATE INDEX IDX_7C766F6AE26B496B ON curation_decision (decided_by_id)');
        $this->addSql('CREATE TABLE import_column (id UUID NOT NULL, column_index INT NOT NULL, header_name VARCHAR(255) NOT NULL, sample_values JSON DEFAULT NULL, session_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1623D66613FECDF ON import_column (session_id)');
        $this->addSql('CREATE TABLE import_row (id UUID NOT NULL, row_index INT NOT NULL, row_hash VARCHAR(255) NOT NULL, source_payload JSON NOT NULL, normalized_payload JSON DEFAULT NULL, resolution_status VARCHAR(255) NOT NULL, resolved_subject_id UUID DEFAULT NULL, row_error_codes JSON DEFAULT NULL, session_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_105D565B613FECDF ON import_row (session_id)');
        $this->addSql('CREATE TABLE import_session (id UUID NOT NULL, status VARCHAR(255) NOT NULL, source_file_fingerprint VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, profiled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, schema_mappings_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, normalizations_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, vocabulary_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, patches_built_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, applied_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, account_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C3547C389B6B5FBA ON import_session (account_id)');
        $this->addSql('CREATE INDEX IDX_C3547C38A76ED395 ON import_session (user_id)');
        $this->addSql('CREATE TABLE model_run_trace (id UUID NOT NULL, run_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, step VARCHAR(50) NOT NULL, model_info VARCHAR(255) NOT NULL, prompt_payload JSON DEFAULT NULL, response_payload JSON DEFAULT NULL, session_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_340DA6BC613FECDF ON model_run_trace (session_id)');
        $this->addSql('CREATE TABLE ontology_term_proposal (id UUID NOT NULL, target_field VARCHAR(255) NOT NULL, original_value VARCHAR(255) NOT NULL, term_id VARCHAR(255) NOT NULL, term_label VARCHAR(255) NOT NULL, decision_status VARCHAR(50) NOT NULL, source VARCHAR(50) NOT NULL, session_id UUID NOT NULL, column_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9B237E31613FECDF ON ontology_term_proposal (session_id)');
        $this->addSql('CREATE INDEX IDX_9B237E31BE8E8ED5 ON ontology_term_proposal (column_id)');
        $this->addSql('CREATE TABLE patch_proposal (id UUID NOT NULL, subject_id UUID NOT NULL, target_field VARCHAR(255) NOT NULL, proposed_value JSON DEFAULT NULL, decision_status VARCHAR(50) NOT NULL, source VARCHAR(50) NOT NULL, session_id UUID NOT NULL, row_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B3D702D3613FECDF ON patch_proposal (session_id)');
        $this->addSql('CREATE INDEX IDX_B3D702D383A269F2 ON patch_proposal (row_id)');
        $this->addSql('CREATE TABLE schema_field_mapping_proposal (id UUID NOT NULL, target_field VARCHAR(255) NOT NULL, decision_status VARCHAR(50) NOT NULL, source VARCHAR(50) NOT NULL, session_id UUID NOT NULL, column_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B73B4B91613FECDF ON schema_field_mapping_proposal (session_id)');
        $this->addSql('CREATE INDEX IDX_B73B4B91BE8E8ED5 ON schema_field_mapping_proposal (column_id)');
        $this->addSql('CREATE TABLE value_normalization_proposal (id UUID NOT NULL, target_field VARCHAR(255) NOT NULL, original_value VARCHAR(255) NOT NULL, normalized_value VARCHAR(255) NOT NULL, decision_status VARCHAR(50) NOT NULL, source VARCHAR(50) NOT NULL, session_id UUID NOT NULL, column_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FF278008613FECDF ON value_normalization_proposal (session_id)');
        $this->addSql('CREATE INDEX IDX_FF278008BE8E8ED5 ON value_normalization_proposal (column_id)');
        $this->addSql('ALTER TABLE curation_decision ADD CONSTRAINT FK_7C766F6A613FECDF FOREIGN KEY (session_id) REFERENCES import_session (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE curation_decision ADD CONSTRAINT FK_7C766F6AE26B496B FOREIGN KEY (decided_by_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE import_column ADD CONSTRAINT FK_1623D66613FECDF FOREIGN KEY (session_id) REFERENCES import_session (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE import_row ADD CONSTRAINT FK_105D565B613FECDF FOREIGN KEY (session_id) REFERENCES import_session (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE import_session ADD CONSTRAINT FK_C3547C389B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE import_session ADD CONSTRAINT FK_C3547C38A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE model_run_trace ADD CONSTRAINT FK_340DA6BC613FECDF FOREIGN KEY (session_id) REFERENCES import_session (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ontology_term_proposal ADD CONSTRAINT FK_9B237E31613FECDF FOREIGN KEY (session_id) REFERENCES import_session (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ontology_term_proposal ADD CONSTRAINT FK_9B237E31BE8E8ED5 FOREIGN KEY (column_id) REFERENCES import_column (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE patch_proposal ADD CONSTRAINT FK_B3D702D3613FECDF FOREIGN KEY (session_id) REFERENCES import_session (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE patch_proposal ADD CONSTRAINT FK_B3D702D383A269F2 FOREIGN KEY (row_id) REFERENCES import_row (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE schema_field_mapping_proposal ADD CONSTRAINT FK_B73B4B91613FECDF FOREIGN KEY (session_id) REFERENCES import_session (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE schema_field_mapping_proposal ADD CONSTRAINT FK_B73B4B91BE8E8ED5 FOREIGN KEY (column_id) REFERENCES import_column (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE value_normalization_proposal ADD CONSTRAINT FK_FF278008613FECDF FOREIGN KEY (session_id) REFERENCES import_session (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE value_normalization_proposal ADD CONSTRAINT FK_FF278008BE8E8ED5 FOREIGN KEY (column_id) REFERENCES import_column (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('COMMENT ON COLUMN curation_suggestion.current_value IS \'\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestion.proposed_value IS \'\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestion.provider_metadata IS \'\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestion.failure_details IS \'\'');
        $this->addSql('ALTER INDEX idx_8fe8afef23edc87 RENAME TO IDX_8BE6AFA323EDC87');
        $this->addSql('ALTER INDEX idx_8fe8afea76ed395 RENAME TO IDX_8BE6AFA3FC6B21F1');
    }

    public function down(Schema $schema): void
    {
        // Intentionally left blank. This compatibility migration supports divergent histories
        // where the replaced version may already have created the same schema.
    }
}
