<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the guideline QC/review workflow: denormalized review state on
 * guideline_field_value, plus the append-only guideline_review_event audit log.
 */
final class Version20260701090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add guideline field review state and the guideline_review_event audit log.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE guideline_field_value
                ADD review_status VARCHAR(16) NOT NULL DEFAULT 'draft',
                ADD reviewed_by_id UUID DEFAULT NULL,
                ADD reviewed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            SQL);
        $this->addSql('COMMENT ON COLUMN guideline_field_value.reviewed_by_id IS \'(DC2Type:uuid)\'');
        $this->addSql('CREATE INDEX idx_guideline_field_value_reviewed_by ON guideline_field_value (reviewed_by_id)');
        $this->addSql('ALTER TABLE guideline_field_value ADD CONSTRAINT fk_guideline_field_value_reviewed_by FOREIGN KEY (reviewed_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE guideline_review_event (
                id UUID NOT NULL,
                resource_type VARCHAR(32) NOT NULL,
                resource_id UUID NOT NULL,
                template_id VARCHAR(128) NOT NULL,
                field_id VARCHAR(128) DEFAULT NULL,
                action VARCHAR(32) NOT NULL,
                actor_id UUID NOT NULL,
                occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                note VARCHAR(1000) DEFAULT NULL,
                account_id UUID NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('COMMENT ON COLUMN guideline_review_event.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN guideline_review_event.resource_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN guideline_review_event.actor_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN guideline_review_event.account_id IS \'(DC2Type:uuid)\'');

        $this->addSql('CREATE INDEX idx_guideline_review_event_lookup ON guideline_review_event (resource_type, resource_id, template_id)');
        $this->addSql('CREATE INDEX idx_guideline_review_event_actor ON guideline_review_event (actor_id)');
        $this->addSql('CREATE INDEX idx_guideline_review_event_account ON guideline_review_event (account_id)');

        $this->addSql('ALTER TABLE guideline_review_event ADD CONSTRAINT fk_guideline_review_event_actor FOREIGN KEY (actor_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE guideline_review_event ADD CONSTRAINT fk_guideline_review_event_account FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE guideline_review_event');

        $this->addSql('ALTER TABLE guideline_field_value DROP CONSTRAINT fk_guideline_field_value_reviewed_by');
        $this->addSql('DROP INDEX idx_guideline_field_value_reviewed_by');
        $this->addSql(<<<'SQL'
            ALTER TABLE guideline_field_value
                DROP review_status,
                DROP reviewed_by_id,
                DROP reviewed_at
            SQL);
    }
}
