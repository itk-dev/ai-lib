<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: `user` and `assistant` tables.
 *
 * Uses Doctrine's Schema tool API (no raw `addSql`) so the migration
 * stays portable across any database Doctrine supports.
 */
final class Version20260617080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the initial user and assistant tables.';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->createTable('user');
        $user->addColumn('id', Types::INTEGER, ['notnull' => true, 'autoincrement' => true]);
        $user->addColumn('email', Types::STRING, ['length' => 180, 'notnull' => true]);
        $user->addColumn('roles', Types::JSON, ['notnull' => true]);
        $user->addColumn('password', Types::STRING, ['length' => 255, 'notnull' => true]);
        $user->setPrimaryKey(['id']);
        $user->addUniqueIndex(['email'], 'UNIQ_IDENTIFIER_EMAIL');
        $user->addOption('charset', 'utf8mb4');

        $assistant = $schema->createTable('assistant');
        $assistant->addColumn('id', Types::INTEGER, ['notnull' => true, 'autoincrement' => true]);
        $assistant->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => true]);
        $assistant->addColumn('description', Types::TEXT, ['notnull' => true]);
        $assistant->addColumn('language_model', Types::STRING, ['length' => 255, 'notnull' => true]);
        $assistant->addColumn('framework', Types::STRING, ['length' => 255, 'notnull' => true]);
        $assistant->addColumn('tags', Types::JSON, ['notnull' => true]);
        $assistant->setPrimaryKey(['id']);
        $assistant->addOption('charset', 'utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('assistant');
        $schema->dropTable('user');
    }
}
