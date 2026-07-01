<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the imported_reference table (Reference Hub library).
 */
final class Version20260629122002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create imported_reference table for the Reference Hub library.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE imported_reference (id UUID NOT NULL, reference_id VARCHAR(255) NOT NULL, source VARCHAR(64) NOT NULL, source_name VARCHAR(255) NOT NULL, type VARCHAR(64) NOT NULL, title VARCHAR(512) NOT NULL, description TEXT DEFAULT NULL, external_url VARCHAR(2048) DEFAULT NULL, identifiers JSON DEFAULT NULL, raw JSON DEFAULT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F15DC1AB9B6B5FBA ON imported_reference (account_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_imported_reference_account_ref ON imported_reference (account_id, reference_id)');
        $this->addSql('ALTER TABLE imported_reference ADD CONSTRAINT FK_F15DC1AB9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE imported_reference DROP CONSTRAINT FK_F15DC1AB9B6B5FBA');
        $this->addSql('DROP TABLE imported_reference');
    }
}
