<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401120815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persisted Zefix system and environmental observation tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE environmental_observation (id UUID NOT NULL, metric VARCHAR(255) NOT NULL, value DOUBLE PRECISION NOT NULL, timestamp TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, source VARCHAR(255) NOT NULL, notes TEXT DEFAULT NULL, system_id UUID DEFAULT NULL, room_id UUID DEFAULT NULL, user_id UUID NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2C3D45BD0952FA5 ON environmental_observation (system_id)');
        $this->addSql('CREATE INDEX IDX_2C3D45B54177093 ON environmental_observation (room_id)');
        $this->addSql('CREATE INDEX IDX_2C3D45BA76ED395 ON environmental_observation (user_id)');
        $this->addSql('CREATE INDEX IDX_2C3D45B9B6B5FBA ON environmental_observation (account_id)');

        $this->addSql('CREATE TABLE system (id UUID NOT NULL, code VARCHAR(64) NOT NULL, manufacturer VARCHAR(255) NOT NULL, model VARCHAR(255) NOT NULL, water_temperature DOUBLE PRECISION DEFAULT NULL, p_h DOUBLE PRECISION DEFAULT NULL, conductivity DOUBLE PRECISION NOT NULL, oxygen DOUBLE PRECISION NOT NULL, water_flow DOUBLE PRECISION NOT NULL, filtration_status VARCHAR(16) NOT NULL, room_id UUID NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C94D118B54177093 ON system (room_id)');
        $this->addSql('CREATE INDEX IDX_C94D118B9B6B5FBA ON system (account_id)');
        $this->addSql('ALTER TABLE rack ADD system_id UUID DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_2801F9480952FA5 ON rack (system_id)');

        $this->addSql('ALTER TABLE environmental_observation ADD CONSTRAINT FK_2C3D45BD0952FA5 FOREIGN KEY (system_id) REFERENCES system (id)');
        $this->addSql('ALTER TABLE environmental_observation ADD CONSTRAINT FK_2C3D45B54177093 FOREIGN KEY (room_id) REFERENCES room (id)');
        $this->addSql('ALTER TABLE environmental_observation ADD CONSTRAINT FK_2C3D45BA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE environmental_observation ADD CONSTRAINT FK_2C3D45B9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');

        $this->addSql('ALTER TABLE system ADD CONSTRAINT FK_C94D118B54177093 FOREIGN KEY (room_id) REFERENCES room (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE system ADD CONSTRAINT FK_C94D118B9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rack ADD CONSTRAINT FK_2801F9480952FA5 FOREIGN KEY (system_id) REFERENCES system (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE environmental_observation DROP CONSTRAINT FK_2C3D45BD0952FA5');
        $this->addSql('ALTER TABLE environmental_observation DROP CONSTRAINT FK_2C3D45B54177093');
        $this->addSql('ALTER TABLE environmental_observation DROP CONSTRAINT FK_2C3D45BA76ED395');
        $this->addSql('ALTER TABLE environmental_observation DROP CONSTRAINT FK_2C3D45B9B6B5FBA');

        $this->addSql('ALTER TABLE system DROP CONSTRAINT FK_C94D118B54177093');
        $this->addSql('ALTER TABLE system DROP CONSTRAINT FK_C94D118B9B6B5FBA');
        $this->addSql('ALTER TABLE rack DROP CONSTRAINT FK_2801F9480952FA5');
        $this->addSql('DROP INDEX IDX_2801F9480952FA5');
        $this->addSql('ALTER TABLE rack DROP system_id');

        $this->addSql('DROP TABLE environmental_observation');
        $this->addSql('DROP TABLE system');
    }
}
