<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260331150659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add minimal Zefix backend resources and persistence model.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plateau (id UUID NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(64) NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5CA87FDC9B6B5FBA ON plateau (account_id)');
        $this->addSql('CREATE TABLE pool (id UUID NOT NULL, position VARCHAR(64) NOT NULL, capacity INT NOT NULL, status VARCHAR(255) NOT NULL, rack_id UUID NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AF91A9868E86A33E ON pool (rack_id)');
        $this->addSql('CREATE INDEX IDX_AF91A9869B6B5FBA ON pool (account_id)');
        $this->addSql('CREATE TABLE rack (id UUID NOT NULL, code VARCHAR(64) NOT NULL, room_id UUID NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3DD796A854177093 ON rack (room_id)');
        $this->addSql('CREATE INDEX IDX_3DD796A89B6B5FBA ON rack (account_id)');
        $this->addSql('CREATE TABLE room (id UUID NOT NULL, name VARCHAR(255) NOT NULL, plateau_id UUID NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_729F519B927847DB ON room (plateau_id)');
        $this->addSql('CREATE INDEX IDX_729F519B9B6B5FBA ON room (account_id)');
        $this->addSql('CREATE TABLE zebrafish_batch (id UUID NOT NULL, birth_date DATE NOT NULL, male_count INT NOT NULL, female_count INT NOT NULL, unknown_sex_count INT NOT NULL, total_population INT NOT NULL, lifecycle_status VARCHAR(255) NOT NULL, line_id UUID NOT NULL, pool_id UUID NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FC34B44D4D7B7542 ON zebrafish_batch (line_id)');
        $this->addSql('CREATE INDEX IDX_FC34B44D7B3406DF ON zebrafish_batch (pool_id)');
        $this->addSql('CREATE INDEX IDX_FC34B44D9B6B5FBA ON zebrafish_batch (account_id)');
        $this->addSql('CREATE TABLE zebrafish_line (id UUID NOT NULL, name VARCHAR(255) NOT NULL, genotype VARCHAR(255) DEFAULT NULL, purpose TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_35D438709B6B5FBA ON zebrafish_line (account_id)');
        $this->addSql('ALTER TABLE plateau ADD CONSTRAINT FK_5CA87FDC9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pool ADD CONSTRAINT FK_AF91A9868E86A33E FOREIGN KEY (rack_id) REFERENCES rack (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE pool ADD CONSTRAINT FK_AF91A9869B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rack ADD CONSTRAINT FK_3DD796A854177093 FOREIGN KEY (room_id) REFERENCES room (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE rack ADD CONSTRAINT FK_3DD796A89B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE room ADD CONSTRAINT FK_729F519B927847DB FOREIGN KEY (plateau_id) REFERENCES plateau (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE room ADD CONSTRAINT FK_729F519B9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE zebrafish_batch ADD CONSTRAINT FK_FC34B44D4D7B7542 FOREIGN KEY (line_id) REFERENCES zebrafish_line (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE zebrafish_batch ADD CONSTRAINT FK_FC34B44D7B3406DF FOREIGN KEY (pool_id) REFERENCES pool (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE zebrafish_batch ADD CONSTRAINT FK_FC34B44D9B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE zebrafish_line ADD CONSTRAINT FK_35D438709B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE plateau DROP CONSTRAINT FK_5CA87FDC9B6B5FBA');
        $this->addSql('ALTER TABLE pool DROP CONSTRAINT FK_AF91A9868E86A33E');
        $this->addSql('ALTER TABLE pool DROP CONSTRAINT FK_AF91A9869B6B5FBA');
        $this->addSql('ALTER TABLE rack DROP CONSTRAINT FK_3DD796A854177093');
        $this->addSql('ALTER TABLE rack DROP CONSTRAINT FK_3DD796A89B6B5FBA');
        $this->addSql('ALTER TABLE room DROP CONSTRAINT FK_729F519B927847DB');
        $this->addSql('ALTER TABLE room DROP CONSTRAINT FK_729F519B9B6B5FBA');
        $this->addSql('ALTER TABLE zebrafish_batch DROP CONSTRAINT FK_FC34B44D4D7B7542');
        $this->addSql('ALTER TABLE zebrafish_batch DROP CONSTRAINT FK_FC34B44D7B3406DF');
        $this->addSql('ALTER TABLE zebrafish_batch DROP CONSTRAINT FK_FC34B44D9B6B5FBA');
        $this->addSql('ALTER TABLE zebrafish_line DROP CONSTRAINT FK_35D438709B6B5FBA');
        $this->addSql('DROP TABLE plateau');
        $this->addSql('DROP TABLE pool');
        $this->addSql('DROP TABLE rack');
        $this->addSql('DROP TABLE room');
        $this->addSql('DROP TABLE zebrafish_batch');
        $this->addSql('DROP TABLE zebrafish_line');
    }
}
