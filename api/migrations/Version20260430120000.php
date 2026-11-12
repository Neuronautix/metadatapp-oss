<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260430120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add connected_resource_link table for first-class external resource references on experiments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE connected_resource_link (id UUID NOT NULL, experiment_id UUID NOT NULL, account_id UUID NOT NULL, provider VARCHAR(64) NOT NULL, external_id VARCHAR(255) NOT NULL, url VARCHAR(1024) DEFAULT NULL, resource_type VARCHAR(64) NOT NULL, label VARCHAR(255) DEFAULT NULL, subject_external_id VARCHAR(255) DEFAULT NULL, synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_crl_experiment ON connected_resource_link (experiment_id)');
        $this->addSql('CREATE INDEX idx_crl_provider_external ON connected_resource_link (provider, external_id)');
        $this->addSql('CREATE INDEX IDX_CRL_ACCOUNT ON connected_resource_link (account_id)');
        $this->addSql('COMMENT ON COLUMN connected_resource_link.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN connected_resource_link.experiment_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN connected_resource_link.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN connected_resource_link.synced_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN connected_resource_link.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN connected_resource_link.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE connected_resource_link ADD CONSTRAINT FK_CRL_EXPERIMENT FOREIGN KEY (experiment_id) REFERENCES experiment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE connected_resource_link ADD CONSTRAINT FK_CRL_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE connected_resource_link DROP CONSTRAINT FK_CRL_EXPERIMENT');
        $this->addSql('ALTER TABLE connected_resource_link DROP CONSTRAINT FK_CRL_ACCOUNT');
        $this->addSql('DROP TABLE connected_resource_link');
    }
}
