<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the guideline_field_value table backing the International Guidelines
 * Reporting fill persistence (§7). Tenant-scoped (account_id + user_id), keyed
 * uniquely per (resource_type, resource_id, template_id, field_id).
 */
final class Version20260630120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create guideline_field_value table for guideline fill persistence (manual/ai/paper field values).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE guideline_field_value (
                id UUID NOT NULL,
                resource_type VARCHAR(32) NOT NULL,
                resource_id UUID NOT NULL,
                template_id VARCHAR(128) NOT NULL,
                field_id VARCHAR(128) NOT NULL,
                value JSON DEFAULT NULL,
                source VARCHAR(16) NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                account_id UUID NOT NULL,
                user_id UUID NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('COMMENT ON COLUMN guideline_field_value.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN guideline_field_value.resource_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN guideline_field_value.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN guideline_field_value.user_id IS \'(DC2Type:uuid)\'');

        $this->addSql('CREATE UNIQUE INDEX uniq_guideline_field_value ON guideline_field_value (resource_type, resource_id, template_id, field_id)');
        $this->addSql('CREATE INDEX idx_guideline_field_value_lookup ON guideline_field_value (resource_type, resource_id, template_id)');
        $this->addSql('CREATE INDEX idx_guideline_field_value_account ON guideline_field_value (account_id)');
        $this->addSql('CREATE INDEX idx_guideline_field_value_user ON guideline_field_value (user_id)');

        $this->addSql('ALTER TABLE guideline_field_value ADD CONSTRAINT fk_guideline_field_value_account FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE guideline_field_value ADD CONSTRAINT fk_guideline_field_value_user FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE guideline_field_value');
    }
}
