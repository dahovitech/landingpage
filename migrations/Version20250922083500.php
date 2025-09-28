<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250922083500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create services and service_translations tables for multilingual support';
    }

    public function up(Schema $schema): void
    {
        // Create services table
        $this->addSql('CREATE TABLE services (
            id INT AUTO_INCREMENT NOT NULL,
            image_id INT DEFAULT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_7332E1693DA5256D (image_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Create service_translations table
        $this->addSql('CREATE TABLE service_translations (
            id INT AUTO_INCREMENT NOT NULL,
            service_id INT NOT NULL,
            language_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            meta_title VARCHAR(500) DEFAULT NULL,
            meta_description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_A38C96A2ED5CA9E6 (service_id),
            INDEX IDX_A38C96A282F1BAF4 (language_id),
            UNIQUE INDEX UNIQ_SERVICE_LANGUAGE (service_id, language_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        // Add foreign key constraints
        $this->addSql('ALTER TABLE services ADD CONSTRAINT FK_7332E1693DA5256D FOREIGN KEY (image_id) REFERENCES media (id)');
        $this->addSql('ALTER TABLE service_translations ADD CONSTRAINT FK_A38C96A2ED5CA9E6 FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_translations ADD CONSTRAINT FK_A38C96A282F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id)');
    }

    public function down(Schema $schema): void
    {
        // Drop tables in reverse order
        $this->addSql('ALTER TABLE service_translations DROP FOREIGN KEY FK_A38C96A2ED5CA9E6');
        $this->addSql('ALTER TABLE service_translations DROP FOREIGN KEY FK_A38C96A282F1BAF4');
        $this->addSql('ALTER TABLE services DROP FOREIGN KEY FK_7332E1693DA5256D');
        $this->addSql('DROP TABLE service_translations');
        $this->addSql('DROP TABLE services');
    }
}
