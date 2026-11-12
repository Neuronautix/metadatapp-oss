<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401065118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist Zefix mortality records and support batch history aggregation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mortality_record (id UUID NOT NULL, event_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, count INT NOT NULL, reason VARCHAR(255) NOT NULL, notes TEXT DEFAULT NULL, batch_id UUID NOT NULL, user_id UUID NOT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_9EBAD723F39EBE7A ON mortality_record (batch_id)');
        $this->addSql('CREATE INDEX IDX_9EBAD723A76ED395 ON mortality_record (user_id)');
        $this->addSql('CREATE INDEX IDX_9EBAD7239B6B5FBA ON mortality_record (account_id)');
        $this->addSql('ALTER TABLE mortality_record ADD CONSTRAINT FK_9EBAD723F39EBE7A FOREIGN KEY (batch_id) REFERENCES zebrafish_batch (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE mortality_record ADD CONSTRAINT FK_9EBAD723A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE mortality_record ADD CONSTRAINT FK_9EBAD7239B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mortality_record DROP CONSTRAINT FK_9EBAD723F39EBE7A');
        $this->addSql('ALTER TABLE mortality_record DROP CONSTRAINT FK_9EBAD723A76ED395');
        $this->addSql('ALTER TABLE mortality_record DROP CONSTRAINT FK_9EBAD7239B6B5FBA');
        $this->addSql('DROP TABLE mortality_record');
    }
}
