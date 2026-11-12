<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260324123241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Legacy placeholder for removed migration version kept to avoid migration metadata drift across environments.';
    }

    public function up(Schema $schema): void
    {
        // Intentionally left blank.
    }

    public function down(Schema $schema): void
    {
        // Intentionally left blank.
    }
}
