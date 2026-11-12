<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401112000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore structured authentication parameters on connected apps for per-user external app credentials.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE connected_app ADD authentication_parameters JSON DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN connected_app.authentication_parameters IS \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE connected_app DROP authentication_parameters');
    }
}
