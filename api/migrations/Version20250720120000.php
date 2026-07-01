<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250720120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow weight to be nullable on subject and Add external ids for connected apps';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cage ADD elaftw_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE cage ADD fair3r_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE cage ADD softmouse_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE experiment ADD elaftw_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE experiment ADD fair3r_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE experiment ADD softmouse_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE procedure ADD elaftw_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE procedure ADD fair3r_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE procedure ADD softmouse_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD elaftw_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD fair3r_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD softmouse_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subject ADD elaftw_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subject ADD fair3r_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subject ADD softmouse_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_measurement ADD elaftw_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_measurement ADD fair3r_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE weight_measurement ADD softmouse_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE subject ALTER weight DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subject ALTER weight SET NOT NULL');
        $this->addSql('ALTER TABLE cage DROP elaftw_external_id');
        $this->addSql('ALTER TABLE cage DROP fair3r_external_id');
        $this->addSql('ALTER TABLE cage DROP softmouse_external_id');
        $this->addSql('ALTER TABLE experiment DROP elaftw_external_id');
        $this->addSql('ALTER TABLE experiment DROP fair3r_external_id');
        $this->addSql('ALTER TABLE experiment DROP softmouse_external_id');
        $this->addSql('ALTER TABLE procedure DROP elaftw_external_id');
        $this->addSql('ALTER TABLE procedure DROP fair3r_external_id');
        $this->addSql('ALTER TABLE procedure DROP softmouse_external_id');
        $this->addSql('ALTER TABLE project DROP elaftw_external_id');
        $this->addSql('ALTER TABLE project DROP fair3r_external_id');
        $this->addSql('ALTER TABLE project DROP softmouse_external_id');
        $this->addSql('ALTER TABLE subject DROP elaftw_external_id');
        $this->addSql('ALTER TABLE subject DROP fair3r_external_id');
        $this->addSql('ALTER TABLE subject DROP softmouse_external_id');
        $this->addSql('ALTER TABLE weight_measurement DROP elaftw_external_id');
        $this->addSql('ALTER TABLE weight_measurement DROP fair3r_external_id');
        $this->addSql('ALTER TABLE weight_measurement DROP softmouse_external_id');
    }
}
