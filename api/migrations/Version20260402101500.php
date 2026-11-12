<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260402101500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cryo_record table for Zefix cryo conservation records.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cryo_record (id UUID NOT NULL, line_id UUID NOT NULL, storage_location VARCHAR(255) NOT NULL, protocol_used VARCHAR(255) NOT NULL, storage_date DATE NOT NULL, notes TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, account_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8DFD88E54D7B7542 ON cryo_record (line_id)');
        $this->addSql('CREATE INDEX IDX_8DFD88E59B6B5FBA ON cryo_record (account_id)');
        $this->addSql('ALTER TABLE cryo_record ADD CONSTRAINT FK_8DFD88E54D7B7542 FOREIGN KEY (line_id) REFERENCES zebrafish_line (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE cryo_record ADD CONSTRAINT FK_8DFD88E59B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cryo_record DROP CONSTRAINT FK_8DFD88E54D7B7542');
        $this->addSql('ALTER TABLE cryo_record DROP CONSTRAINT FK_8DFD88E59B6B5FBA');
        $this->addSql('DROP TABLE cryo_record');
    }
}
