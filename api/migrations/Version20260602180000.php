<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cage_hcm_sinusoid_metadata table for MIROsine sinusoidal fit parameters per cage per day.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cage_hcm_sinusoid_metadata (id UUID NOT NULL, account_id UUID NOT NULL, cage_id UUID NOT NULL, home_cage_monitoring_id UUID NOT NULL, date DATE NOT NULL, amplitude DOUBLE PRECISION NOT NULL, mesor DOUBLE PRECISION NOT NULL, phase DOUBLE PRECISION NOT NULL, peak_hour DOUBLE PRECISION NOT NULL, source_task_id VARCHAR(64) DEFAULT NULL, source_app_code VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CAGE_HCM_SINUSOID_ACCOUNT ON cage_hcm_sinusoid_metadata (account_id)');
        $this->addSql('CREATE INDEX IDX_CAGE_HCM_SINUSOID_CAGE ON cage_hcm_sinusoid_metadata (cage_id)');
        $this->addSql('CREATE INDEX IDX_CAGE_HCM_SINUSOID_HCM ON cage_hcm_sinusoid_metadata (home_cage_monitoring_id)');
        $this->addSql('COMMENT ON COLUMN cage_hcm_sinusoid_metadata.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cage_hcm_sinusoid_metadata.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cage_hcm_sinusoid_metadata.cage_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN cage_hcm_sinusoid_metadata.home_cage_monitoring_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE cage_hcm_sinusoid_metadata ADD CONSTRAINT FK_CAGE_HCM_SINUSOID_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cage_hcm_sinusoid_metadata ADD CONSTRAINT FK_CAGE_HCM_SINUSOID_CAGE FOREIGN KEY (cage_id) REFERENCES cage (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cage_hcm_sinusoid_metadata ADD CONSTRAINT FK_CAGE_HCM_SINUSOID_HCM FOREIGN KEY (home_cage_monitoring_id) REFERENCES home_cage_monitoring (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cage_hcm_sinusoid_metadata DROP CONSTRAINT FK_CAGE_HCM_SINUSOID_ACCOUNT');
        $this->addSql('ALTER TABLE cage_hcm_sinusoid_metadata DROP CONSTRAINT FK_CAGE_HCM_SINUSOID_CAGE');
        $this->addSql('ALTER TABLE cage_hcm_sinusoid_metadata DROP CONSTRAINT FK_CAGE_HCM_SINUSOID_HCM');
        $this->addSql('DROP TABLE cage_hcm_sinusoid_metadata');
    }
}
