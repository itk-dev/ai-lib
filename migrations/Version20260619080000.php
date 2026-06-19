<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add `name` and `status` columns to `user` per ADR 006.
 *
 * `name` is the display name introduced by #45; `status` is the
 * `UserStatus` enum (`pending | approved | blocked`) introduced by
 * #83 that gates login via the `UserCheckerInterface` landing in #63.
 *
 * Backfill any existing rows in `up()` so the new not-null constraints
 * hold in environments that already have user data (default `name = ''`,
 * default `status = 'approved'` — assume historic rows were trusted).
 */
final class Version20260619080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name and status columns to user.';
    }

    public function up(Schema $schema): void
    {
        $user = $schema->getTable('user');
        $user->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true, 'default' => '']);
        $user->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'approved']);
    }

    public function down(Schema $schema): void
    {
        $user = $schema->getTable('user');
        $user->dropColumn('status');
        $user->dropColumn('name');
    }
}
