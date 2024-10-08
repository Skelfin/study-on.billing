<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241004135607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SEQUENCE "billing_user_id_seq" INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE course_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE refresh_tokens_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE transaction_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE "billing_user" (id INT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, balance DOUBLE PRECISION DEFAULT \'0\' NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "billing_user" (email)');
        $this->addSql('CREATE TABLE course (id INT NOT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, type SMALLINT NOT NULL, price DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_COURSE_CODE ON course (code)');
        $this->addSql('CREATE TABLE refresh_tokens (id INT NOT NULL, refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E1C74F2195 ON refresh_tokens (refresh_token)');
        $this->addSql('CREATE TABLE transaction (id INT NOT NULL, user_id INT NOT NULL, course_id INT DEFAULT NULL, type SMALLINT NOT NULL, amount DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_723705D1A76ED395 ON transaction (user_id)');
        $this->addSql('CREATE INDEX IDX_723705D1591CC992 ON transaction (course_id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1A76ED395 FOREIGN KEY (user_id) REFERENCES "billing_user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1591CC992 FOREIGN KEY (course_id) REFERENCES course (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP SEQUENCE "billing_user_id_seq" CASCADE');
        $this->addSql('DROP SEQUENCE course_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE refresh_tokens_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE transaction_id_seq CASCADE');
        $this->addSql('ALTER TABLE transaction DROP CONSTRAINT FK_723705D1A76ED395');
        $this->addSql('ALTER TABLE transaction DROP CONSTRAINT FK_723705D1591CC992');
        $this->addSql('DROP TABLE "billing_user"');
        $this->addSql('DROP TABLE course');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE transaction');
    }
}
