<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the `setting` table.
 *
 * Generic key/value storage backing
 * {@see \App\Settings\SettingsManager}. `name` is unique; `value` is
 * nullable so "intentionally unset" stays representable.
 */
final class Version20260622080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the setting table.';
    }

    public function up(Schema $schema): void
    {
        $setting = $schema->createTable('setting');
        $setting->addColumn('id', Types::INTEGER, ['notnull' => true, 'autoincrement' => true]);
        $setting->addColumn('name', Types::STRING, ['length' => 64, 'notnull' => true]);
        $setting->addColumn('value', Types::TEXT, ['notnull' => false]);
        $setting->setPrimaryKey(['id']);
        $setting->addUniqueIndex(['name'], 'UNIQ_SETTING_NAME');
        $setting->addOption('charset', 'utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('setting');
    }
}
