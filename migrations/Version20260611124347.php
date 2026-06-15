<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial `user` table for application authentication (#2).
 *
 * Uses Doctrine's Schema tool API (no raw `addSql`) so the migration
 * stays portable across any database Doctrine supports.
 */
final class Version20260611124347 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the user table backing Symfony Security (email, roles, hashed password).';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('user');
        $table->addColumn('id', Types::INTEGER, ['notnull' => true, 'autoincrement' => true]);
        $table->addColumn('email', Types::STRING, ['length' => 180, 'notnull' => true]);
        $table->addColumn('roles', Types::JSON, ['notnull' => true]);
        $table->addColumn('password', Types::STRING, ['length' => 255, 'notnull' => true]);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['email'], 'UNIQ_IDENTIFIER_EMAIL');
        $table->addOption('charset', 'utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('user');
    }
}
