<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260401143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persisted Zefix alerts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE alert (id UUID NOT NULL, type VARCHAR(255) NOT NULL, severity VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, affected_entity_type VARCHAR(255) NOT NULL, recommended_action TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, acknowledged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, system_id UUID DEFAULT NULL, room_id UUID DEFAULT NULL, batch_id UUID DEFAULT NULL, account_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_C53DF10D0952FA5 ON alert (system_id)');
        $this->addSql('CREATE INDEX IDX_C53DF1054177093 ON alert (room_id)');
        $this->addSql('CREATE INDEX IDX_C53DF10F1751A23 ON alert (batch_id)');
        $this->addSql('CREATE INDEX IDX_C53DF109B6B5FBA ON alert (account_id)');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_C53DF10D0952FA5 FOREIGN KEY (system_id) REFERENCES system (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_C53DF1054177093 FOREIGN KEY (room_id) REFERENCES room (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_C53DF10F1751A23 FOREIGN KEY (batch_id) REFERENCES zebrafish_batch (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE alert ADD CONSTRAINT FK_C53DF109B6B5FBA FOREIGN KEY (account_id) REFERENCES account (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_C53DF10D0952FA5');
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_C53DF1054177093');
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_C53DF10F1751A23');
        $this->addSql('ALTER TABLE alert DROP CONSTRAINT FK_C53DF109B6B5FBA');
        $this->addSql('DROP TABLE alert');
    }
}
