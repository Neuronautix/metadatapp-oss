<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260403091713 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external account key for Keycloak organization mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account ADD external_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7D3656AAB7D90E22 ON account (external_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_7D3656AAB7D90E22');
        $this->addSql('ALTER TABLE account DROP external_key');
    }
}
