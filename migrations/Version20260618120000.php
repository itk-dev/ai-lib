<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the `organization` table introduced by ADR 005.
 *
 * Carries the org-level fields decided in the ADR: a display `name`,
 * a JSON list of `emails` owned by the organisation, and the
 * `default_framework` value used to pre-fill new assistants. The
 * `User → Organization` reference and the registration / allow-list
 * switchover are out of scope and land in follow-up migrations.
 */
final class Version20260618120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the organization table.';
    }

    public function up(Schema $schema): void
    {
        $organization = $schema->createTable('organization');
        $organization->addColumn('id', Types::INTEGER, ['notnull' => true, 'autoincrement' => true]);
        $organization->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
        $organization->addColumn('emails', Types::JSON, ['notnull' => true]);
        $organization->addColumn('default_framework', Types::STRING, ['length' => 255, 'notnull' => true]);
        $organization->setPrimaryKey(['id']);
        $organization->addOption('charset', 'utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('organization');
    }
}
