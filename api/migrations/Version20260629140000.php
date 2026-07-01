<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Semantic discovery for the Reference Hub.
 *
 * Adds TWO embedding representations on imported_reference, kept deliberately
 * independent so similarity never *requires* pgvector:
 *
 *   1. `embedding` TEXT — a portable JSON array of floats. This is the column the
 *      default, pgvector-free code path reads/writes ({@see ReferenceEmbeddingService}
 *      ranks candidates with pure-PHP cosine). It works on any Postgres and is what
 *      the unit tests exercise (they don't touch the DB at all).
 *
 *   2. `reference_embedding` vector(256) — the OPTIONAL pgvector scale path. We
 *      `CREATE EXTENSION IF NOT EXISTS vector` and, only if that succeeds, add the
 *      typed column + an ivfflat cosine index for in-database nearest-neighbour
 *      (`ORDER BY reference_embedding <=> :q`). If the extension is unavailable (no
 *      superuser / not installed), we SKIP this column and the app degrades to the
 *      JSON + pure-PHP path with no loss of correctness — only of scale.
 *
 * The vector width (256) must match EmbeddingProviderInterface::DIMENSIONS.
 */
final class Version20260629140000 extends AbstractMigration
{
    private const int EMBEDDING_DIMENSIONS = 256;

    public function getDescription(): string
    {
        return 'Add semantic-discovery embeddings to imported_reference (portable JSON column always; optional pgvector column when the extension is available).';
    }

    public function up(Schema $schema): void
    {
        // Always-on, portable column used by the default (pgvector-free) path.
        $this->addSql('ALTER TABLE imported_reference ADD embedding TEXT DEFAULT NULL');

        // Optional pgvector scale path. We probe availability with a harmless SELECT
        // FIRST and only queue the extension/vector statements when we know they will
        // succeed. Crucially we must NOT *execute* a failing `CREATE EXTENSION` here:
        // inside the migration transaction a failed statement aborts the whole
        // transaction (SQLSTATE 25P02), which would then fail every later statement —
        // including the portable column above.
        if (!$this->isPgvectorAvailable()) {
            $this->write('  pgvector extension unavailable — skipping the typed vector column; the JSON embedding + pure-PHP cosine path remains fully functional.');

            return;
        }

        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');
        $this->addSql(\sprintf('ALTER TABLE imported_reference ADD reference_embedding vector(%d) DEFAULT NULL', self::EMBEDDING_DIMENSIONS));
        // ivfflat cosine index for approximate nearest-neighbour at scale.
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_imported_reference_embedding ON imported_reference USING ivfflat (reference_embedding vector_cosine_ops) WITH (lists = 100)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_imported_reference_embedding');
        // DROP COLUMN IF EXISTS so down() is safe whether or not the vector column
        // was created in up() (it is conditional on pgvector being available).
        $this->addSql('ALTER TABLE imported_reference DROP COLUMN IF EXISTS reference_embedding');
        $this->addSql('ALTER TABLE imported_reference DROP COLUMN IF EXISTS embedding');
    }

    /**
     * Whether the pgvector extension can be created — i.e. its control file is
     * installed on the server (`pg_available_extensions`). This is a read-only
     * probe: it never issues a statement that could abort the transaction, so a
     * server without pgvector still migrates cleanly via the portable JSON path.
     */
    private function isPgvectorAvailable(): bool
    {
        try {
            return (bool) $this->connection->fetchOne("SELECT 1 FROM pg_available_extensions WHERE name = 'vector'");
        } catch (\Throwable) {
            return false;
        }
    }
}
