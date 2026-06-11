<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250519055147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE animal_facility ALTER account_id TYPE UUID');
        $this->addSql('ALTER TABLE animal_facility ALTER account_id SET NOT NULL');
        $this->addSql('ALTER TABLE cage ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE cage ADD CONSTRAINT FK_56A64E519B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_56A64E519B6B5FBA ON cage (account_id)');
        $this->addSql('ALTER TABLE cage_hcm_measure DROP CONSTRAINT fk_5f52ec3ea76ed395');
        $this->addSql('DROP INDEX idx_5f52ec3ea76ed395');
        $this->addSql('ALTER TABLE cage_hcm_measure ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE cage_hcm_measure DROP user_id');
        $this->addSql('ALTER TABLE cage_hcm_measure ADD CONSTRAINT FK_5F52EC3E9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_5F52EC3E9B6B5FBA ON cage_hcm_measure (account_id)');
        $this->addSql('ALTER TABLE connected_app ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE connected_app ALTER token TYPE TEXT');
        $this->addSql('ALTER TABLE connected_app ALTER token DROP NOT NULL');
        $this->addSql('ALTER TABLE connected_app ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE connected_app ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE connected_app ADD CONSTRAINT FK_EA7AE4509B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EA7AE4509B6B5FBA ON connected_app (account_id)');
        $this->addSql('ALTER TABLE environment_housing ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE environment_housing ADD CONSTRAINT FK_C920F54F9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_C920F54F9B6B5FBA ON environment_housing (account_id)');
        $this->addSql('ALTER TABLE experiment ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE experiment ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE experiment ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE experiment ADD CONSTRAINT FK_136F58B29B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_136F58B29B6B5FBA ON experiment (account_id)');
        $this->addSql('ALTER TABLE home_cage_monitoring ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE home_cage_monitoring ADD CONSTRAINT FK_73E2F2A59B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_73E2F2A59B6B5FBA ON home_cage_monitoring (account_id)');
        $this->addSql('ALTER TABLE laboratory ALTER account_id TYPE UUID');
        $this->addSql('ALTER TABLE laboratory ALTER account_id SET NOT NULL');
        $this->addSql('ALTER TABLE life_event DROP CONSTRAINT fk_17cf3691a76ed395');
        $this->addSql('DROP INDEX idx_17cf3691a76ed395');
        $this->addSql('ALTER TABLE life_event ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE life_event DROP user_id');
        $this->addSql('ALTER TABLE life_event ADD CONSTRAINT FK_17CF36919B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_17CF36919B6B5FBA ON life_event (account_id)');
        $this->addSql('ALTER TABLE procedure ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE procedure ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE procedure ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE procedure ADD CONSTRAINT FK_9C3CBC1F9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_9C3CBC1F9B6B5FBA ON procedure (account_id)');
        $this->addSql('ALTER TABLE project ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE project ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE project ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE9B6B5FBA ON project (account_id)');
        $this->addSql('ALTER TABLE subject ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_FBCE3E7A9B6B5FBA ON subject (account_id)');
        $this->addSql('ALTER TABLE time_series DROP CONSTRAINT fk_63e64edba76ed395');
        $this->addSql('DROP INDEX idx_63e64edba76ed395');
        $this->addSql('ALTER TABLE time_series ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE time_series DROP user_id');
        $this->addSql('ALTER TABLE time_series ADD CONSTRAINT FK_63E64EDB9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_63E64EDB9B6B5FBA ON time_series (account_id)');
        $this->addSql('ALTER TABLE "user" ALTER account_id TYPE UUID');
        $this->addSql('ALTER TABLE "user" ALTER account_id SET NOT NULL');
        $this->addSql('DROP INDEX uniq_cc4d878d5e237e06');
        $this->addSql('ALTER TABLE variable ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE variable ADD CONSTRAINT FK_CC4D878D9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_CC4D878D9B6B5FBA ON variable (account_id)');
        $this->addSql('ALTER TABLE weight_measurement ADD account_id UUID NOT NULL');
        $this->addSql('ALTER TABLE weight_measurement ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE weight_measurement ALTER user_id SET NOT NULL');
        $this->addSql('ALTER TABLE weight_measurement ADD CONSTRAINT FK_395208CE9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_395208CE9B6B5FBA ON weight_measurement (account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE environment_housing DROP CONSTRAINT FK_C920F54F9B6B5FBA');
        $this->addSql('DROP INDEX IDX_C920F54F9B6B5FBA');
        $this->addSql('ALTER TABLE environment_housing DROP account_id');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EE9B6B5FBA');
        $this->addSql('DROP INDEX IDX_2FB3D0EE9B6B5FBA');
        $this->addSql('ALTER TABLE project DROP account_id');
        $this->addSql('ALTER TABLE project ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE project ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE home_cage_monitoring DROP CONSTRAINT FK_73E2F2A59B6B5FBA');
        $this->addSql('DROP INDEX IDX_73E2F2A59B6B5FBA');
        $this->addSql('ALTER TABLE home_cage_monitoring DROP account_id');
        $this->addSql('ALTER TABLE time_series DROP CONSTRAINT FK_63E64EDB9B6B5FBA');
        $this->addSql('DROP INDEX IDX_63E64EDB9B6B5FBA');
        $this->addSql('ALTER TABLE time_series ADD user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE time_series DROP account_id');
        $this->addSql('ALTER TABLE time_series ADD CONSTRAINT fk_63e64edba76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_63e64edba76ed395 ON time_series (user_id)');
        $this->addSql('ALTER TABLE laboratory ALTER account_id TYPE UUID');
        $this->addSql('ALTER TABLE laboratory ALTER account_id DROP NOT NULL');
        $this->addSql('ALTER TABLE cage DROP CONSTRAINT FK_56A64E519B6B5FBA');
        $this->addSql('DROP INDEX IDX_56A64E519B6B5FBA');
        $this->addSql('ALTER TABLE cage DROP account_id');
        $this->addSql('ALTER TABLE procedure DROP CONSTRAINT FK_9C3CBC1F9B6B5FBA');
        $this->addSql('DROP INDEX IDX_9C3CBC1F9B6B5FBA');
        $this->addSql('ALTER TABLE procedure DROP account_id');
        $this->addSql('ALTER TABLE procedure ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE procedure ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE connected_app DROP CONSTRAINT FK_EA7AE4509B6B5FBA');
        $this->addSql('DROP INDEX IDX_EA7AE4509B6B5FBA');
        $this->addSql('ALTER TABLE connected_app DROP account_id');
        $this->addSql('ALTER TABLE connected_app ALTER token TYPE TEXT');
        $this->addSql('ALTER TABLE connected_app ALTER token SET NOT NULL');
        $this->addSql('ALTER TABLE connected_app ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE connected_app ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE variable DROP CONSTRAINT FK_CC4D878D9B6B5FBA');
        $this->addSql('DROP INDEX IDX_CC4D878D9B6B5FBA');
        $this->addSql('ALTER TABLE variable DROP account_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_cc4d878d5e237e06 ON variable (name)');
        $this->addSql('ALTER TABLE life_event DROP CONSTRAINT FK_17CF36919B6B5FBA');
        $this->addSql('DROP INDEX IDX_17CF36919B6B5FBA');
        $this->addSql('ALTER TABLE life_event ADD user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE life_event DROP account_id');
        $this->addSql('ALTER TABLE life_event ADD CONSTRAINT fk_17cf3691a76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_17cf3691a76ed395 ON life_event (user_id)');
        $this->addSql('ALTER TABLE animal_facility ALTER account_id TYPE UUID');
        $this->addSql('ALTER TABLE animal_facility ALTER account_id DROP NOT NULL');
        $this->addSql('ALTER TABLE experiment DROP CONSTRAINT FK_136F58B29B6B5FBA');
        $this->addSql('DROP INDEX IDX_136F58B29B6B5FBA');
        $this->addSql('ALTER TABLE experiment DROP account_id');
        $this->addSql('ALTER TABLE experiment ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE experiment ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE weight_measurement DROP CONSTRAINT FK_395208CE9B6B5FBA');
        $this->addSql('DROP INDEX IDX_395208CE9B6B5FBA');
        $this->addSql('ALTER TABLE weight_measurement DROP account_id');
        $this->addSql('ALTER TABLE weight_measurement ALTER user_id TYPE UUID');
        $this->addSql('ALTER TABLE weight_measurement ALTER user_id DROP NOT NULL');
        $this->addSql('ALTER TABLE cage_hcm_measure DROP CONSTRAINT FK_5F52EC3E9B6B5FBA');
        $this->addSql('DROP INDEX IDX_5F52EC3E9B6B5FBA');
        $this->addSql('ALTER TABLE cage_hcm_measure ADD user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE cage_hcm_measure DROP account_id');
        $this->addSql('ALTER TABLE cage_hcm_measure ADD CONSTRAINT fk_5f52ec3ea76ed395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_5f52ec3ea76ed395 ON cage_hcm_measure (user_id)');
        $this->addSql('ALTER TABLE subject DROP CONSTRAINT FK_FBCE3E7A9B6B5FBA');
        $this->addSql('DROP INDEX IDX_FBCE3E7A9B6B5FBA');
        $this->addSql('ALTER TABLE subject DROP account_id');
        $this->addSql('ALTER TABLE "user" ALTER account_id TYPE UUID');
        $this->addSql('ALTER TABLE "user" ALTER account_id DROP NOT NULL');
    }
}
