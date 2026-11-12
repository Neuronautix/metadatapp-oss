<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user-scoped LLM provider credentials.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE llm_provider_credential (id UUID NOT NULL, account_id UUID NOT NULL, user_id UUID NOT NULL, provider VARCHAR(32) NOT NULL, model VARCHAR(255) NOT NULL, encrypted_api_key TEXT DEFAULT NULL, api_key_hint VARCHAR(64) DEFAULT NULL, active BOOLEAN DEFAULT true NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_llm_provider_user ON llm_provider_credential (provider, user_id)');
        $this->addSql('CREATE INDEX IDX_LLM_PROVIDER_CREDENTIAL_ACCOUNT ON llm_provider_credential (account_id)');
        $this->addSql('CREATE INDEX IDX_LLM_PROVIDER_CREDENTIAL_USER ON llm_provider_credential (user_id)');
        $this->addSql('COMMENT ON COLUMN llm_provider_credential.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN llm_provider_credential.account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN llm_provider_credential.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE llm_provider_credential ADD CONSTRAINT FK_LLM_PROVIDER_CREDENTIAL_ACCOUNT FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE llm_provider_credential ADD CONSTRAINT FK_LLM_PROVIDER_CREDENTIAL_USER FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE llm_provider_credential DROP CONSTRAINT FK_LLM_PROVIDER_CREDENTIAL_ACCOUNT');
        $this->addSql('ALTER TABLE llm_provider_credential DROP CONSTRAINT FK_LLM_PROVIDER_CREDENTIAL_USER');
        $this->addSql('DROP TABLE llm_provider_credential');
    }
}
