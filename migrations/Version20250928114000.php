<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for new SaaS entities: Features, Testimonials, PricingPlans, FAQ
 */
final class Version20250928114000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add new SaaS entities: Features, Testimonials, PricingPlans, FAQ with their translations';
    }

    public function up(Schema $schema): void
    {
        // Features table
        $this->addSql('CREATE TABLE features (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            image_id INTEGER DEFAULT NULL, 
            slug VARCHAR(255) NOT NULL, 
            icon VARCHAR(100) DEFAULT NULL, 
            is_active BOOLEAN NOT NULL, 
            is_featured BOOLEAN NOT NULL, 
            sort_order INTEGER NOT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            CONSTRAINT FK_BFC0DC133DA5256D FOREIGN KEY (image_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BFC0DC13989D9B62 ON features (slug)');
        $this->addSql('CREATE INDEX IDX_BFC0DC133DA5256D ON features (image_id)');

        // Feature translations table
        $this->addSql('CREATE TABLE feature_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            feature_id INTEGER NOT NULL, 
            language_id INTEGER NOT NULL, 
            title VARCHAR(255) NOT NULL, 
            description CLOB DEFAULT NULL, 
            meta_title VARCHAR(500) DEFAULT NULL, 
            meta_description CLOB DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            CONSTRAINT FK_7ED26C1460E4B879 FOREIGN KEY (feature_id) REFERENCES features (id) NOT DEFERRABLE INITIALLY IMMEDIATE, 
            CONSTRAINT FK_7ED26C1482F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX IDX_7ED26C1460E4B879 ON feature_translations (feature_id)');
        $this->addSql('CREATE INDEX IDX_7ED26C1482F1BAF4 ON feature_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FEATURE_LANGUAGE ON feature_translations (feature_id, language_id)');

        // Testimonials table
        $this->addSql('CREATE TABLE testimonials (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            client_avatar_id INTEGER DEFAULT NULL, 
            client_name VARCHAR(255) NOT NULL, 
            client_position VARCHAR(255) DEFAULT NULL, 
            client_company VARCHAR(255) DEFAULT NULL, 
            client_email VARCHAR(255) DEFAULT NULL, 
            rating INTEGER DEFAULT NULL, 
            is_active BOOLEAN NOT NULL, 
            is_featured BOOLEAN NOT NULL, 
            sort_order INTEGER NOT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            CONSTRAINT FK_383115796D18EB1F FOREIGN KEY (client_avatar_id) REFERENCES media (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX IDX_383115796D18EB1F ON testimonials (client_avatar_id)');

        // Testimonial translations table
        $this->addSql('CREATE TABLE testimonial_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            testimonial_id INTEGER NOT NULL, 
            language_id INTEGER NOT NULL, 
            content CLOB NOT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            CONSTRAINT FK_9BE970A91D4EC6B1 FOREIGN KEY (testimonial_id) REFERENCES testimonials (id) NOT DEFERRABLE INITIALLY IMMEDIATE, 
            CONSTRAINT FK_9BE970A982F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX IDX_9BE970A91D4EC6B1 ON testimonial_translations (testimonial_id)');
        $this->addSql('CREATE INDEX IDX_9BE970A982F1BAF4 ON testimonial_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TESTIMONIAL_LANGUAGE ON testimonial_translations (testimonial_id, language_id)');

        // Pricing plans table
        $this->addSql('CREATE TABLE pricing_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            slug VARCHAR(255) NOT NULL, 
            price NUMERIC(10, 2) NOT NULL, 
            billing_period VARCHAR(10) NOT NULL, 
            currency VARCHAR(10) NOT NULL, 
            features CLOB DEFAULT NULL, 
            max_users INTEGER DEFAULT NULL, 
            max_projects INTEGER DEFAULT NULL, 
            storage_limit INTEGER DEFAULT NULL, 
            is_active BOOLEAN NOT NULL, 
            is_popular BOOLEAN NOT NULL, 
            is_free BOOLEAN NOT NULL, 
            sort_order INTEGER NOT NULL, 
            stripe_product_id VARCHAR(255) DEFAULT NULL, 
            stripe_price_id VARCHAR(255) DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_19951050989D9B62 ON pricing_plans (slug)');

        // Pricing plan translations table
        $this->addSql('CREATE TABLE pricing_plan_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            pricing_plan_id INTEGER NOT NULL, 
            language_id INTEGER NOT NULL, 
            name VARCHAR(255) NOT NULL, 
            description CLOB DEFAULT NULL, 
            features CLOB DEFAULT NULL, 
            cta_text VARCHAR(255) DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            CONSTRAINT FK_5A1F01C929628C71 FOREIGN KEY (pricing_plan_id) REFERENCES pricing_plans (id) NOT DEFERRABLE INITIALLY IMMEDIATE, 
            CONSTRAINT FK_5A1F01C982F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX IDX_5A1F01C929628C71 ON pricing_plan_translations (pricing_plan_id)');
        $this->addSql('CREATE INDEX IDX_5A1F01C982F1BAF4 ON pricing_plan_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_PRICING_PLAN_LANGUAGE ON pricing_plan_translations (pricing_plan_id, language_id)');

        // FAQs table
        $this->addSql('CREATE TABLE faqs (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            category VARCHAR(255) DEFAULT NULL, 
            is_active BOOLEAN NOT NULL, 
            is_featured BOOLEAN NOT NULL, 
            sort_order INTEGER NOT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL
        )');

        // FAQ translations table
        $this->addSql('CREATE TABLE faq_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            faq_id INTEGER NOT NULL, 
            language_id INTEGER NOT NULL, 
            question VARCHAR(500) NOT NULL, 
            answer CLOB NOT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            CONSTRAINT FK_99569DA281BEC8C2 FOREIGN KEY (faq_id) REFERENCES faqs (id) NOT DEFERRABLE INITIALLY IMMEDIATE, 
            CONSTRAINT FK_99569DA282F1BAF4 FOREIGN KEY (language_id) REFERENCES languages (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX IDX_99569DA281BEC8C2 ON faq_translations (faq_id)');
        $this->addSql('CREATE INDEX IDX_99569DA282F1BAF4 ON faq_translations (language_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FAQ_LANGUAGE ON faq_translations (faq_id, language_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE faq_translations');
        $this->addSql('DROP TABLE faqs');
        $this->addSql('DROP TABLE feature_translations');
        $this->addSql('DROP TABLE features');
        $this->addSql('DROP TABLE pricing_plan_translations');
        $this->addSql('DROP TABLE pricing_plans');
        $this->addSql('DROP TABLE testimonial_translations');
        $this->addSql('DROP TABLE testimonials');
    }
}