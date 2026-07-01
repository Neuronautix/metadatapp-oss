<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220102152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE impc_gene (id UUID NOT NULL, mgi_id VARCHAR(255) NOT NULL, symbol VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE impc_phenotype_statement (id UUID NOT NULL, phenotype_term VARCHAR(255) NOT NULL, p_value DOUBLE PRECISION NOT NULL, gene_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_4B26083938BEE1C3 ON impc_phenotype_statement (gene_id)');
        $this->addSql('ALTER TABLE impc_phenotype_statement ADD CONSTRAINT FK_4B26083938BEE1C3 FOREIGN KEY (gene_id) REFERENCES impc_gene (id)');
        $this->addSql('ALTER TABLE procedure ADD precliniset_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE variable ADD precliniset_external_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE variable ADD external_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE impc_phenotype_statement DROP CONSTRAINT FK_4B26083938BEE1C3');
        $this->addSql('DROP TABLE impc_gene');
        $this->addSql('DROP TABLE impc_phenotype_statement');
        $this->addSql('ALTER TABLE procedure DROP precliniset_external_id');
        $this->addSql('ALTER TABLE variable DROP precliniset_external_id');
        $this->addSql('ALTER TABLE variable DROP external_id');
    }
}
