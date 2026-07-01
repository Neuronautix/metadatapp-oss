<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audited curation suggestions sidecar for subject metadata curation with provider metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE curation_suggestion (id UUID NOT NULL, subject_id UUID NOT NULL, reviewed_by_id UUID DEFAULT NULL, resource_type VARCHAR(100) NOT NULL, resource_id VARCHAR(36) NOT NULL, task_type VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, field_name VARCHAR(255) NOT NULL, current_value JSON DEFAULT NULL, proposed_value JSON DEFAULT NULL, ontology_id VARCHAR(255) DEFAULT NULL, confidence DOUBLE PRECISION DEFAULT NULL, reason TEXT NOT NULL, warning_code VARCHAR(255) DEFAULT NULL, raw_llm_response TEXT DEFAULT NULL, provider_name VARCHAR(100) NOT NULL, provider_metadata JSON DEFAULT NULL, failure_details JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8FE8AFEF23EDC87 ON curation_suggestion (subject_id)');
        $this->addSql('CREATE INDEX IDX_8FE8AFEA76ED395 ON curation_suggestion (reviewed_by_id)');
        $this->addSql('COMMENT ON COLUMN curation_suggestion.current_value IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestion.proposed_value IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestion.provider_metadata IS \'(DC2Type:json)\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestion.failure_details IS \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE curation_suggestion ADD CONSTRAINT FK_8FE8AFEF23EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE curation_suggestion ADD CONSTRAINT FK_8FE8AFEA76ED395 FOREIGN KEY (reviewed_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE curation_suggestion');
    }
}
