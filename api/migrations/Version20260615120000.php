<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cde_element table for storing imported NIH Common Data Elements.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cde_element (id UUID NOT NULL, account_id UUID NOT NULL, tiny_id VARCHAR(64) NOT NULL, version VARCHAR(16) DEFAULT NULL, source VARCHAR(64) DEFAULT NULL, name VARCHAR(512) NOT NULL, definition TEXT DEFAULT NULL, permissible_values JSON DEFAULT NULL, raw_data JSON DEFAULT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CDE_ELEMENT_ACCOUNT ON cde_element (account_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cde_account_tiny_id ON cde_element (account_id, tiny_id)');
        $this->addSql('COMMENT ON COLUMN cde_element.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cde_element.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cde_element.imported_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE cde_element ADD CONSTRAINT FK_CDE_ELEMENT_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cde_element DROP CONSTRAINT FK_CDE_ELEMENT_ACCOUNT');
        $this->addSql('DROP TABLE cde_element');
    }
}
