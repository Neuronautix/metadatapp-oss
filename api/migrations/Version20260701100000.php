<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove the Precliniset connected-app integration columns. Precliniset itself
 * (client, mapper, service, command) has been removed from the codebase; this
 * drops the last remaining live schema artifacts tied to it. The original
 * migration that added these columns (Version20260220102152) is left
 * untouched, per policy of never editing already-applied migrations.
 */
final class Version20260701100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop precliniset_external_id columns from procedure and variable (Precliniset integration removed).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE procedure DROP precliniset_external_id');
        $this->addSql('ALTER TABLE variable DROP precliniset_external_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE procedure ADD precliniset_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE variable ADD precliniset_external_id VARCHAR(255) DEFAULT NULL');
    }
}
