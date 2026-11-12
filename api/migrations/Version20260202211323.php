<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260202211323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cage_hcm_measure RENAME COLUMN data_type TO dataType');
        $this->addSql('ALTER TABLE procedure ADD procedure_type_enum VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE surgery ALTER invasiveness_type SET NOT NULL');
        $this->addSql('ALTER TABLE treatment ALTER treatment_type SET NOT NULL');
        $this->addSql('ALTER TABLE treatment ALTER category SET NOT NULL');
        $this->addSql('ALTER TABLE treatment ALTER route SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cage_hcm_measure RENAME COLUMN dataType TO data_type');
        $this->addSql('ALTER TABLE procedure DROP procedure_type_enum');
        $this->addSql('ALTER TABLE surgery ALTER invasiveness_type DROP NOT NULL');
        $this->addSql('ALTER TABLE treatment ALTER treatment_type DROP NOT NULL');
        $this->addSql('ALTER TABLE treatment ALTER category DROP NOT NULL');
        $this->addSql('ALTER TABLE treatment ALTER route DROP NOT NULL');
    }
}
