<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250505025609 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE account (id UUID NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE animal_facility (id UUID NOT NULL, sanitary_status VARCHAR(50) NOT NULL, name VARCHAR(50) NOT NULL, account_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_D6DA926F9B6B5FBA ON animal_facility (account_id)');
        $this->addSql('CREATE TABLE behavior (apparatus VARCHAR(255) DEFAULT NULL, context VARCHAR(255) DEFAULT NULL, lighting DOUBLE PRECISION DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE cage (id UUID NOT NULL, type VARCHAR(255) NOT NULL, format VARCHAR(255) NOT NULL, enrichment_type VARCHAR(255) DEFAULT NULL, has_enrichments BOOLEAN DEFAULT false NOT NULL, water BOOLEAN DEFAULT false NOT NULL, food BOOLEAN NOT NULL, bedding_type VARCHAR(255) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, environment_housing_id UUID DEFAULT NULL, animal_facility_id UUID DEFAULT NULL, home_cage_monitoring_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_56A64E514F223A34 ON cage (environment_housing_id)');
        $this->addSql('CREATE INDEX IDX_56A64E51BB9AA9 ON cage (animal_facility_id)');
        $this->addSql('CREATE INDEX IDX_56A64E51D17C5AA3 ON cage (home_cage_monitoring_id)');
        $this->addSql('CREATE TABLE cage_hcm_measure (id UUID NOT NULL, data_type VARCHAR(255) NOT NULL, value DOUBLE PRECISION NOT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID DEFAULT NULL, cage_id UUID NOT NULL, variable_id UUID NOT NULL, home_cage_monitoring_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5F52EC3EA76ED395 ON cage_hcm_measure (user_id)');
        $this->addSql('CREATE INDEX IDX_5F52EC3E5A70E5B7 ON cage_hcm_measure (cage_id)');
        $this->addSql('CREATE INDEX IDX_5F52EC3EF3037E8E ON cage_hcm_measure (variable_id)');
        $this->addSql('CREATE INDEX IDX_5F52EC3ED17C5AA3 ON cage_hcm_measure (home_cage_monitoring_id)');
        $this->addSql('CREATE TABLE connected_app (id UUID NOT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, is_active BOOLEAN DEFAULT false NOT NULL, token TEXT NOT NULL, last_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EA7AE450A76ED395 ON connected_app (user_id)');
        $this->addSql('CREATE TABLE environment_housing (id UUID NOT NULL, room VARCHAR(255) NOT NULL, temperature DOUBLE PRECISION DEFAULT NULL, humidity DOUBLE PRECISION DEFAULT NULL, luminosity DOUBLE PRECISION DEFAULT NULL, pressure DOUBLE PRECISION DEFAULT NULL, noise DOUBLE PRECISION DEFAULT NULL, light_cycle VARCHAR(255) DEFAULT NULL, animal_facility_id UUID NOT NULL, experiment_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C920F54FBB9AA9 ON environment_housing (animal_facility_id)');
        $this->addSql('CREATE INDEX IDX_C920F54FFF444C8 ON environment_housing (experiment_id)');
        $this->addSql('CREATE TABLE experiment (id UUID NOT NULL, name VARCHAR(255) NOT NULL, goal VARCHAR(255) DEFAULT NULL, type VARCHAR(50) NOT NULL, protocol TEXT DEFAULT NULL, start_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, description TEXT DEFAULT NULL, repository_link VARCHAR(255) DEFAULT NULL, user_id UUID DEFAULT NULL, project_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_136F58B2A76ED395 ON experiment (user_id)');
        $this->addSql('CREATE INDEX IDX_136F58B2166D1F9C ON experiment (project_id)');
        $this->addSql('CREATE TABLE home_cage_monitoring (id UUID NOT NULL, system VARCHAR(255) NOT NULL, experiment_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_73E2F2A5FF444C8 ON home_cage_monitoring (experiment_id)');
        $this->addSql('CREATE TABLE laboratory (id UUID NOT NULL, name VARCHAR(255) NOT NULL, account_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FDC719A89B6B5FBA ON laboratory (account_id)');
        $this->addSql('CREATE TABLE laboratory_project (laboratory_id UUID NOT NULL, project_id UUID NOT NULL, PRIMARY KEY(laboratory_id, project_id))');
        $this->addSql('CREATE INDEX IDX_6C5CAE572F2A371E ON laboratory_project (laboratory_id)');
        $this->addSql('CREATE INDEX IDX_6C5CAE57166D1F9C ON laboratory_project (project_id)');
        $this->addSql('CREATE TABLE life_event (id UUID NOT NULL, event_type VARCHAR(255) NOT NULL, event_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, description TEXT DEFAULT NULL, user_id UUID DEFAULT NULL, subject_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_17CF3691A76ED395 ON life_event (user_id)');
        $this->addSql('CREATE INDEX IDX_17CF369123EDC87 ON life_event (subject_id)');
        $this->addSql('CREATE TABLE procedure (id UUID NOT NULL, start_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, duration DOUBLE PRECISION DEFAULT NULL, name VARCHAR(255) NOT NULL, protocol TEXT DEFAULT NULL, protocol_uri VARCHAR(255) DEFAULT NULL, state VARCHAR(255) NOT NULL, experiment_id UUID NOT NULL, user_id UUID DEFAULT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_9C3CBC1FFF444C8 ON procedure (experiment_id)');
        $this->addSql('CREATE INDEX IDX_9C3CBC1FA76ED395 ON procedure (user_id)');
        $this->addSql('CREATE TABLE project (id UUID NOT NULL, name VARCHAR(255) NOT NULL, goal VARCHAR(255) DEFAULT NULL, start_at DATE DEFAULT NULL, end_at DATE DEFAULT NULL, description TEXT DEFAULT NULL, repository_link VARCHAR(255) DEFAULT NULL, user_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEA76ED395 ON project (user_id)');
        $this->addSql('CREATE TABLE strain (id UUID NOT NULL, name VARCHAR(255) NOT NULL, link VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE subject (id UUID NOT NULL, name VARCHAR(255) NOT NULL, species VARCHAR(255) NOT NULL, sex VARCHAR(255) NOT NULL, birth_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, genotype VARCHAR(255) DEFAULT NULL, weight DOUBLE PRECISION NOT NULL, health_status VARCHAR(255) NOT NULL, is_pregnant BOOLEAN DEFAULT NULL, external_id VARCHAR(255) DEFAULT NULL, strain_id UUID NOT NULL, cage_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_FBCE3E7A69B9E007 ON subject (strain_id)');
        $this->addSql('CREATE INDEX IDX_FBCE3E7A5A70E5B7 ON subject (cage_id)');
        $this->addSql('CREATE TABLE subject_procedure (subject_id UUID NOT NULL, procedure_id UUID NOT NULL, PRIMARY KEY(subject_id, procedure_id))');
        $this->addSql('CREATE INDEX IDX_DF6D283523EDC87 ON subject_procedure (subject_id)');
        $this->addSql('CREATE INDEX IDX_DF6D28351624BCD2 ON subject_procedure (procedure_id)');
        $this->addSql('CREATE TABLE surgery (surgery_name VARCHAR(255) DEFAULT NULL, invasiveness_type VARCHAR(255) DEFAULT NULL, invasiveness_details TEXT DEFAULT NULL, description TEXT DEFAULT NULL, has_anesthesia BOOLEAN DEFAULT NULL, anesthesia_details VARCHAR(255) DEFAULT NULL, surgery_duration INT DEFAULT NULL, recovery_duration INT DEFAULT NULL, recovery_details TEXT DEFAULT NULL, complications TEXT DEFAULT NULL, actor_id UUID DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_442C2E8810DAF24A ON surgery (actor_id)');
        $this->addSql('CREATE TABLE time_series (id UUID NOT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, value NUMERIC(10, 5) NOT NULL, unit VARCHAR(50) DEFAULT NULL, user_id UUID DEFAULT NULL, subject_id UUID NOT NULL, experiment_id UUID NOT NULL, variable_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_63E64EDBA76ED395 ON time_series (user_id)');
        $this->addSql('CREATE INDEX IDX_63E64EDB23EDC87 ON time_series (subject_id)');
        $this->addSql('CREATE INDEX IDX_63E64EDBFF444C8 ON time_series (experiment_id)');
        $this->addSql('CREATE INDEX IDX_63E64EDBF3037E8E ON time_series (variable_id)');
        $this->addSql('CREATE TABLE treatment (treatment_type VARCHAR(255) DEFAULT NULL, category VARCHAR(255) DEFAULT NULL, drug VARCHAR(255) DEFAULT NULL, dose DOUBLE PRECISION DEFAULT NULL, dose_unit VARCHAR(255) DEFAULT NULL, route VARCHAR(255) DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, email VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, orcid VARCHAR(19) DEFAULT NULL, roles JSONB DEFAULT \'[]\' NOT NULL, account_id UUID DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE INDEX IDX_8D93D6499B6B5FBA ON "user" (account_id)');
        $this->addSql('CREATE TABLE variable (id UUID NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CC4D878D5E237E06 ON variable (name)');
        $this->addSql('CREATE TABLE weight_measurement (id UUID NOT NULL, measured_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, weight DOUBLE PRECISION NOT NULL, user_id UUID DEFAULT NULL, subject_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_395208CEA76ED395 ON weight_measurement (user_id)');
        $this->addSql('CREATE INDEX IDX_395208CE23EDC87 ON weight_measurement (subject_id)');
        $this->addSql('ALTER TABLE animal_facility ADD CONSTRAINT FK_D6DA926F9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE behavior ADD CONSTRAINT FK_3BABA0B0BF396750 FOREIGN KEY (id) REFERENCES procedure (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cage ADD CONSTRAINT FK_56A64E514F223A34 FOREIGN KEY (environment_housing_id) REFERENCES environment_housing (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cage ADD CONSTRAINT FK_56A64E51BB9AA9 FOREIGN KEY (animal_facility_id) REFERENCES animal_facility (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cage ADD CONSTRAINT FK_56A64E51D17C5AA3 FOREIGN KEY (home_cage_monitoring_id) REFERENCES home_cage_monitoring (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cage_hcm_measure ADD CONSTRAINT FK_5F52EC3EA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cage_hcm_measure ADD CONSTRAINT FK_5F52EC3E5A70E5B7 FOREIGN KEY (cage_id) REFERENCES cage (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cage_hcm_measure ADD CONSTRAINT FK_5F52EC3EF3037E8E FOREIGN KEY (variable_id) REFERENCES variable (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cage_hcm_measure ADD CONSTRAINT FK_5F52EC3ED17C5AA3 FOREIGN KEY (home_cage_monitoring_id) REFERENCES home_cage_monitoring (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE connected_app ADD CONSTRAINT FK_EA7AE450A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE environment_housing ADD CONSTRAINT FK_C920F54FBB9AA9 FOREIGN KEY (animal_facility_id) REFERENCES animal_facility (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE environment_housing ADD CONSTRAINT FK_C920F54FFF444C8 FOREIGN KEY (experiment_id) REFERENCES experiment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE experiment ADD CONSTRAINT FK_136F58B2A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE experiment ADD CONSTRAINT FK_136F58B2166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE home_cage_monitoring ADD CONSTRAINT FK_73E2F2A5FF444C8 FOREIGN KEY (experiment_id) REFERENCES experiment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE laboratory ADD CONSTRAINT FK_FDC719A89B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE laboratory_project ADD CONSTRAINT FK_6C5CAE572F2A371E FOREIGN KEY (laboratory_id) REFERENCES laboratory (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE laboratory_project ADD CONSTRAINT FK_6C5CAE57166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE life_event ADD CONSTRAINT FK_17CF3691A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE life_event ADD CONSTRAINT FK_17CF369123EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE procedure ADD CONSTRAINT FK_9C3CBC1FFF444C8 FOREIGN KEY (experiment_id) REFERENCES experiment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE procedure ADD CONSTRAINT FK_9C3CBC1FA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A69B9E007 FOREIGN KEY (strain_id) REFERENCES strain (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subject ADD CONSTRAINT FK_FBCE3E7A5A70E5B7 FOREIGN KEY (cage_id) REFERENCES cage (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subject_procedure ADD CONSTRAINT FK_DF6D283523EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subject_procedure ADD CONSTRAINT FK_DF6D28351624BCD2 FOREIGN KEY (procedure_id) REFERENCES procedure (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE surgery ADD CONSTRAINT FK_442C2E8810DAF24A FOREIGN KEY (actor_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE surgery ADD CONSTRAINT FK_442C2E88BF396750 FOREIGN KEY (id) REFERENCES procedure (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE time_series ADD CONSTRAINT FK_63E64EDBA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE time_series ADD CONSTRAINT FK_63E64EDB23EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE time_series ADD CONSTRAINT FK_63E64EDBFF444C8 FOREIGN KEY (experiment_id) REFERENCES experiment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE time_series ADD CONSTRAINT FK_63E64EDBF3037E8E FOREIGN KEY (variable_id) REFERENCES variable (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE treatment ADD CONSTRAINT FK_98013C31BF396750 FOREIGN KEY (id) REFERENCES procedure (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D6499B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE weight_measurement ADD CONSTRAINT FK_395208CEA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE weight_measurement ADD CONSTRAINT FK_395208CE23EDC87 FOREIGN KEY (subject_id) REFERENCES subject (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE animal_facility DROP CONSTRAINT FK_D6DA926F9B6B5FBA');
        $this->addSql('ALTER TABLE behavior DROP CONSTRAINT FK_3BABA0B0BF396750');
        $this->addSql('ALTER TABLE cage DROP CONSTRAINT FK_56A64E514F223A34');
        $this->addSql('ALTER TABLE cage DROP CONSTRAINT FK_56A64E51BB9AA9');
        $this->addSql('ALTER TABLE cage DROP CONSTRAINT FK_56A64E51D17C5AA3');
        $this->addSql('ALTER TABLE cage_hcm_measure DROP CONSTRAINT FK_5F52EC3EA76ED395');
        $this->addSql('ALTER TABLE cage_hcm_measure DROP CONSTRAINT FK_5F52EC3E5A70E5B7');
        $this->addSql('ALTER TABLE cage_hcm_measure DROP CONSTRAINT FK_5F52EC3EF3037E8E');
        $this->addSql('ALTER TABLE cage_hcm_measure DROP CONSTRAINT FK_5F52EC3ED17C5AA3');
        $this->addSql('ALTER TABLE connected_app DROP CONSTRAINT FK_EA7AE450A76ED395');
        $this->addSql('ALTER TABLE environment_housing DROP CONSTRAINT FK_C920F54FBB9AA9');
        $this->addSql('ALTER TABLE environment_housing DROP CONSTRAINT FK_C920F54FFF444C8');
        $this->addSql('ALTER TABLE experiment DROP CONSTRAINT FK_136F58B2A76ED395');
        $this->addSql('ALTER TABLE experiment DROP CONSTRAINT FK_136F58B2166D1F9C');
        $this->addSql('ALTER TABLE home_cage_monitoring DROP CONSTRAINT FK_73E2F2A5FF444C8');
        $this->addSql('ALTER TABLE laboratory DROP CONSTRAINT FK_FDC719A89B6B5FBA');
        $this->addSql('ALTER TABLE laboratory_project DROP CONSTRAINT FK_6C5CAE572F2A371E');
        $this->addSql('ALTER TABLE laboratory_project DROP CONSTRAINT FK_6C5CAE57166D1F9C');
        $this->addSql('ALTER TABLE life_event DROP CONSTRAINT FK_17CF3691A76ED395');
        $this->addSql('ALTER TABLE life_event DROP CONSTRAINT FK_17CF369123EDC87');
        $this->addSql('ALTER TABLE procedure DROP CONSTRAINT FK_9C3CBC1FFF444C8');
        $this->addSql('ALTER TABLE procedure DROP CONSTRAINT FK_9C3CBC1FA76ED395');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EEA76ED395');
        $this->addSql('ALTER TABLE subject DROP CONSTRAINT FK_FBCE3E7A69B9E007');
        $this->addSql('ALTER TABLE subject DROP CONSTRAINT FK_FBCE3E7A5A70E5B7');
        $this->addSql('ALTER TABLE subject_procedure DROP CONSTRAINT FK_DF6D283523EDC87');
        $this->addSql('ALTER TABLE subject_procedure DROP CONSTRAINT FK_DF6D28351624BCD2');
        $this->addSql('ALTER TABLE surgery DROP CONSTRAINT FK_442C2E8810DAF24A');
        $this->addSql('ALTER TABLE surgery DROP CONSTRAINT FK_442C2E88BF396750');
        $this->addSql('ALTER TABLE time_series DROP CONSTRAINT FK_63E64EDBA76ED395');
        $this->addSql('ALTER TABLE time_series DROP CONSTRAINT FK_63E64EDB23EDC87');
        $this->addSql('ALTER TABLE time_series DROP CONSTRAINT FK_63E64EDBFF444C8');
        $this->addSql('ALTER TABLE time_series DROP CONSTRAINT FK_63E64EDBF3037E8E');
        $this->addSql('ALTER TABLE treatment DROP CONSTRAINT FK_98013C31BF396750');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D6499B6B5FBA');
        $this->addSql('ALTER TABLE weight_measurement DROP CONSTRAINT FK_395208CEA76ED395');
        $this->addSql('ALTER TABLE weight_measurement DROP CONSTRAINT FK_395208CE23EDC87');
        $this->addSql('DROP TABLE account');
        $this->addSql('DROP TABLE animal_facility');
        $this->addSql('DROP TABLE behavior');
        $this->addSql('DROP TABLE cage');
        $this->addSql('DROP TABLE cage_hcm_measure');
        $this->addSql('DROP TABLE connected_app');
        $this->addSql('DROP TABLE environment_housing');
        $this->addSql('DROP TABLE experiment');
        $this->addSql('DROP TABLE home_cage_monitoring');
        $this->addSql('DROP TABLE laboratory');
        $this->addSql('DROP TABLE laboratory_project');
        $this->addSql('DROP TABLE life_event');
        $this->addSql('DROP TABLE procedure');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE strain');
        $this->addSql('DROP TABLE subject');
        $this->addSql('DROP TABLE subject_procedure');
        $this->addSql('DROP TABLE surgery');
        $this->addSql('DROP TABLE time_series');
        $this->addSql('DROP TABLE treatment');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE variable');
        $this->addSql('DROP TABLE weight_measurement');
    }
}
