<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the `openwebui_config` JSON column to `assistant`.
 *
 * Stores the OpenWebUI export JSON verbatim for assistants created
 * via the new upload form. Nullable so existing rows pre-dating the
 * upload flow stay valid.
 */
final class Version20260622080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add openwebui_config JSON column to assistant.';
    }

    public function up(Schema $schema): void
    {
        $assistant = $schema->getTable('assistant');
        $assistant->addColumn('openwebui_config', Types::JSON, ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $assistant = $schema->getTable('assistant');
        $assistant->dropColumn('openwebui_config');
    }
}
