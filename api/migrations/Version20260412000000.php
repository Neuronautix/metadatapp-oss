<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mapping_template table for reusable curation column-mapping configurations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mapping_template (id UUID NOT NULL, name VARCHAR(255) NOT NULL, mappings JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_MAPPING_TEMPLATE_ACCOUNT ON mapping_template (account_id)');
        $this->addSql('CREATE INDEX IDX_MAPPING_TEMPLATE_USER ON mapping_template (user_id)');
        $this->addSql('ALTER TABLE mapping_template ADD CONSTRAINT FK_MAPPING_TEMPLATE_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE mapping_template ADD CONSTRAINT FK_MAPPING_TEMPLATE_USER FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mapping_template DROP CONSTRAINT FK_MAPPING_TEMPLATE_ACCOUNT');
        $this->addSql('ALTER TABLE mapping_template DROP CONSTRAINT FK_MAPPING_TEMPLATE_USER');
        $this->addSql('DROP TABLE mapping_template');
    }
}
