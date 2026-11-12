<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-app external URL and persist eLabFTW experiment metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE connected_app ADD external_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE experiment ADD elabftw_metadata JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE experiment DROP elabftw_metadata');
        $this->addSql('ALTER TABLE connected_app DROP external_url');
    }
}
