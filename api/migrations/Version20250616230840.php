<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616230840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE connected_app DROP authentication_parameters');
        $this->addSql('ALTER TABLE experiment ADD external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE procedure ADD external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD external_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE procedure DROP external_id');
        $this->addSql('ALTER TABLE experiment DROP external_id');
        $this->addSql('ALTER TABLE project DROP external_id');
        $this->addSql('ALTER TABLE connected_app ADD authentication_parameters JSON DEFAULT NULL');
    }
}
