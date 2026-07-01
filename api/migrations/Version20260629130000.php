<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add AI standardization (Compare & Link) enrichment columns to imported_reference.
 */
final class Version20260629130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add canonical ontology term + cluster enrichment columns to imported_reference.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE imported_reference ADD canonical_term_iri VARCHAR(1024) DEFAULT NULL');
        $this->addSql('ALTER TABLE imported_reference ADD canonical_term_label VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE imported_reference ADD canonical_term_ontology VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE imported_reference ADD standardization_confidence DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE imported_reference ADD cluster_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE imported_reference ADD standardization_evidence JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE imported_reference ADD standardized_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE imported_reference DROP canonical_term_iri');
        $this->addSql('ALTER TABLE imported_reference DROP canonical_term_label');
        $this->addSql('ALTER TABLE imported_reference DROP canonical_term_ontology');
        $this->addSql('ALTER TABLE imported_reference DROP standardization_confidence');
        $this->addSql('ALTER TABLE imported_reference DROP cluster_id');
        $this->addSql('ALTER TABLE imported_reference DROP standardization_evidence');
        $this->addSql('ALTER TABLE imported_reference DROP standardized_at');
    }
}
