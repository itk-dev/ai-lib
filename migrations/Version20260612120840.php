<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial `assistant` table for the catalogue (#14).
 */
final class Version20260612120840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the assistant table with base fields (title, description, languageModel, framework, tags).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE assistant (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, language_model VARCHAR(255) NOT NULL, framework VARCHAR(255) NOT NULL, tags JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE assistant');
    }
}
