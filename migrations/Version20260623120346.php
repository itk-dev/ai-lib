<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adopt itk-dev/entity-bundle: switch entity primary keys to ULID (BINARY(16))
 * and add the shared columns (timestamps, blame relations, archivable,
 * anonymization status) plus the damienharper/auditor `*_audit` tables.
 *
 * Column conversions happen before the foreign keys so `user.id` is already
 * BINARY(16) when the blame relations reference it.
 */
final class Version20260623120346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adopt entity-bundle: ULID ids, timestamp/blame/archivable/anonymization columns, audit tables.';
    }

    public function up(Schema $schema): void
    {
        // Convert primary keys to ULID and add the shared columns. user first so
        // its BINARY(16) id exists before the blame foreign keys point at it.
        $this->addSql('ALTER TABLE `user` ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD archived_at DATETIME DEFAULT NULL, ADD anonymized_at DATETIME DEFAULT NULL, ADD created_by_id BINARY(16) DEFAULT NULL, ADD modified_by_id BINARY(16) DEFAULT NULL, CHANGE id id BINARY(16) NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE assistant ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD archived_at DATETIME DEFAULT NULL, ADD anonymized_at DATETIME DEFAULT NULL, ADD created_by_id BINARY(16) DEFAULT NULL, ADD modified_by_id BINARY(16) DEFAULT NULL, CHANGE id id BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE organization ADD created_at DATETIME NOT NULL, ADD updated_at DATETIME NOT NULL, ADD archived_at DATETIME DEFAULT NULL, ADD anonymized_at DATETIME DEFAULT NULL, ADD created_by_id BINARY(16) DEFAULT NULL, ADD modified_by_id BINARY(16) DEFAULT NULL, CHANGE id id BINARY(16) NOT NULL');

        // Blame foreign keys + indexes (user.id is BINARY(16) by now).
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D649B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_8D93D64999049ECE FOREIGN KEY (modified_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8D93D649B03A8386 ON `user` (created_by_id)');
        $this->addSql('CREATE INDEX IDX_8D93D64999049ECE ON `user` (modified_by_id)');
        $this->addSql('ALTER TABLE assistant ADD CONSTRAINT FK_C2997CD1B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE assistant ADD CONSTRAINT FK_C2997CD199049ECE FOREIGN KEY (modified_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C2997CD1B03A8386 ON assistant (created_by_id)');
        $this->addSql('CREATE INDEX IDX_C2997CD199049ECE ON assistant (modified_by_id)');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637CB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C99049ECE FOREIGN KEY (modified_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C1EE637CB03A8386 ON organization (created_by_id)');
        $this->addSql('CREATE INDEX IDX_C1EE637C99049ECE ON organization (modified_by_id)');

        // Audit log tables (damienharper/auditor doctrine provider, *_audit suffix).
        $this->addSql('CREATE TABLE assistant_audit (id INT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, object_id VARCHAR(255) NOT NULL, discriminator VARCHAR(255) DEFAULT NULL, transaction_hash VARCHAR(40) DEFAULT NULL, diffs JSON DEFAULT NULL, blame_id VARCHAR(255) DEFAULT NULL, blame_user VARCHAR(255) DEFAULT NULL, blame_user_fqdn VARCHAR(255) DEFAULT NULL, blame_user_firewall VARCHAR(100) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX type_4f2bdd877313fca1422a73675cbb17f3_idx (type), INDEX object_id_4f2bdd877313fca1422a73675cbb17f3_idx (object_id), INDEX discriminator_4f2bdd877313fca1422a73675cbb17f3_idx (discriminator), INDEX transaction_hash_4f2bdd877313fca1422a73675cbb17f3_idx (transaction_hash), INDEX blame_id_4f2bdd877313fca1422a73675cbb17f3_idx (blame_id), INDEX created_at_4f2bdd877313fca1422a73675cbb17f3_idx (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization_audit (id INT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, object_id VARCHAR(255) NOT NULL, discriminator VARCHAR(255) DEFAULT NULL, transaction_hash VARCHAR(40) DEFAULT NULL, diffs JSON DEFAULT NULL, blame_id VARCHAR(255) DEFAULT NULL, blame_user VARCHAR(255) DEFAULT NULL, blame_user_fqdn VARCHAR(255) DEFAULT NULL, blame_user_firewall VARCHAR(100) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX type_c50e732e5f1b2c67dbc712e20c7dcfd8_idx (type), INDEX object_id_c50e732e5f1b2c67dbc712e20c7dcfd8_idx (object_id), INDEX discriminator_c50e732e5f1b2c67dbc712e20c7dcfd8_idx (discriminator), INDEX transaction_hash_c50e732e5f1b2c67dbc712e20c7dcfd8_idx (transaction_hash), INDEX blame_id_c50e732e5f1b2c67dbc712e20c7dcfd8_idx (blame_id), INDEX created_at_c50e732e5f1b2c67dbc712e20c7dcfd8_idx (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_audit (id INT UNSIGNED AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, object_id VARCHAR(255) NOT NULL, discriminator VARCHAR(255) DEFAULT NULL, transaction_hash VARCHAR(40) DEFAULT NULL, diffs JSON DEFAULT NULL, blame_id VARCHAR(255) DEFAULT NULL, blame_user VARCHAR(255) DEFAULT NULL, blame_user_fqdn VARCHAR(255) DEFAULT NULL, blame_user_firewall VARCHAR(100) DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX type_e06395edc291d0719bee26fd39a32e8a_idx (type), INDEX object_id_e06395edc291d0719bee26fd39a32e8a_idx (object_id), INDEX discriminator_e06395edc291d0719bee26fd39a32e8a_idx (discriminator), INDEX transaction_hash_e06395edc291d0719bee26fd39a32e8a_idx (transaction_hash), INDEX blame_id_e06395edc291d0719bee26fd39a32e8a_idx (blame_id), INDEX created_at_e06395edc291d0719bee26fd39a32e8a_idx (created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE assistant_audit');
        $this->addSql('DROP TABLE organization_audit');
        $this->addSql('DROP TABLE user_audit');
        $this->addSql('ALTER TABLE assistant DROP FOREIGN KEY FK_C2997CD1B03A8386');
        $this->addSql('ALTER TABLE assistant DROP FOREIGN KEY FK_C2997CD199049ECE');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637CB03A8386');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637C99049ECE');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649B03A8386');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D64999049ECE');
        $this->addSql('DROP INDEX IDX_C2997CD1B03A8386 ON assistant');
        $this->addSql('DROP INDEX IDX_C2997CD199049ECE ON assistant');
        $this->addSql('DROP INDEX IDX_C1EE637CB03A8386 ON organization');
        $this->addSql('DROP INDEX IDX_C1EE637C99049ECE ON organization');
        $this->addSql('DROP INDEX IDX_8D93D649B03A8386 ON `user`');
        $this->addSql('DROP INDEX IDX_8D93D64999049ECE ON `user`');
        $this->addSql('ALTER TABLE assistant DROP created_at, DROP updated_at, DROP archived_at, DROP anonymized_at, DROP created_by_id, DROP modified_by_id, CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE organization DROP created_at, DROP updated_at, DROP archived_at, DROP anonymized_at, DROP created_by_id, DROP modified_by_id, CHANGE id id INT AUTO_INCREMENT NOT NULL');
        $this->addSql('ALTER TABLE `user` DROP created_at, DROP updated_at, DROP archived_at, DROP anonymized_at, DROP created_by_id, DROP modified_by_id, CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE name name VARCHAR(255) DEFAULT \'\' NOT NULL, CHANGE status status VARCHAR(32) DEFAULT \'approved\' NOT NULL');
    }
}
