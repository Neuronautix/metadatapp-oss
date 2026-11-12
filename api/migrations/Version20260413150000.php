<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add LLM provider configuration columns to account table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD llm_provider VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE account ADD llm_api_key TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE account ADD llm_model VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP COLUMN llm_model');
        $this->addSql('ALTER TABLE account DROP COLUMN llm_api_key');
        $this->addSql('ALTER TABLE account DROP COLUMN llm_provider');
    }
}
