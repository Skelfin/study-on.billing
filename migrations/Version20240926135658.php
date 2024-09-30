<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240926135658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Allow nulls temporarily
        $this->addSql('ALTER TABLE course ADD name VARCHAR(255) DEFAULT NULL');

        // Update existing rows with a valid name
        $this->addSql("UPDATE course SET name = 'Unnamed' WHERE name IS NULL");

        // Make the column NOT NULL after updating
        $this->addSql('ALTER TABLE course ALTER COLUMN name SET NOT NULL');
    }


    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE course DROP name');
    }
}