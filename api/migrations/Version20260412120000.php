<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add featurePreset column to account table for account-level feature-flag profiles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD feature_preset VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account DROP COLUMN feature_preset');
    }
}
