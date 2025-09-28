<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250923004300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add additional fields to User entity for user management';
    }

    public function up(Schema $schema): void
    {
        // Add new fields to the user table
        $this->addSql('ALTER TABLE user ADD first_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE user ADD last_name VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE user ADD is_active TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE user ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user ADD updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user ADD last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // Remove the added fields
        $this->addSql('ALTER TABLE user DROP first_name');
        $this->addSql('ALTER TABLE user DROP last_name');
        $this->addSql('ALTER TABLE user DROP is_active');
        $this->addSql('ALTER TABLE user DROP created_at');
        $this->addSql('ALTER TABLE user DROP updated_at');
        $this->addSql('ALTER TABLE user DROP last_login_at');
    }
}