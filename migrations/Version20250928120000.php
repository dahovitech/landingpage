<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing slug columns and indexes for SEO optimization
 */
final class Version20250928120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add slug columns to FAQ and Testimonial entities, add performance indexes';
    }

    public function up(Schema $schema): void
    {
        // Add slug column to FAQs table
        $this->addSql('ALTER TABLE faqs ADD COLUMN slug VARCHAR(255) DEFAULT NULL');
        
        // Add slug column to testimonials table
        $this->addSql('ALTER TABLE testimonials ADD COLUMN slug VARCHAR(255) DEFAULT NULL');
        
        // Create unique indexes for slugs (we'll make them unique after data migration)
        $this->addSql('CREATE INDEX IDX_FAQ_SLUG ON faqs (slug)');
        $this->addSql('CREATE INDEX IDX_TESTIMONIAL_SLUG ON testimonials (slug)');
        
        // Add performance indexes
        $this->addSql('CREATE INDEX idx_faq_active ON faqs (is_active)');
        $this->addSql('CREATE INDEX idx_faq_featured ON faqs (is_featured)');
        $this->addSql('CREATE INDEX idx_faq_sort ON faqs (sort_order)');
        $this->addSql('CREATE INDEX idx_faq_category ON faqs (category)');
        
        $this->addSql('CREATE INDEX idx_testimonial_active ON testimonials (is_active)');
        $this->addSql('CREATE INDEX idx_testimonial_featured ON testimonials (is_featured)');
        $this->addSql('CREATE INDEX idx_testimonial_sort ON testimonials (sort_order)');
        $this->addSql('CREATE INDEX idx_testimonial_rating ON testimonials (rating)');
        
        $this->addSql('CREATE INDEX idx_feature_active ON features (is_active)');
        $this->addSql('CREATE INDEX idx_feature_featured ON features (is_featured)');
        $this->addSql('CREATE INDEX idx_feature_sort ON features (sort_order)');
        
        $this->addSql('CREATE INDEX idx_pricing_active ON pricing_plans (is_active)');
        $this->addSql('CREATE INDEX idx_pricing_featured ON pricing_plans (is_popular)');
        $this->addSql('CREATE INDEX idx_pricing_sort ON pricing_plans (sort_order)');
        
        // Add indexes on translation tables for better performance
        $this->addSql('CREATE INDEX idx_faq_translation_language ON faq_translations (language_id)');
        $this->addSql('CREATE INDEX idx_feature_translation_language ON feature_translations (language_id)');
        $this->addSql('CREATE INDEX idx_testimonial_translation_language ON testimonial_translations (language_id)');
        $this->addSql('CREATE INDEX idx_pricing_translation_language ON pricing_plan_translations (language_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove performance indexes
        $this->addSql('DROP INDEX IF EXISTS idx_faq_active');
        $this->addSql('DROP INDEX IF EXISTS idx_faq_featured');
        $this->addSql('DROP INDEX IF EXISTS idx_faq_sort');
        $this->addSql('DROP INDEX IF EXISTS idx_faq_category');
        
        $this->addSql('DROP INDEX IF EXISTS idx_testimonial_active');
        $this->addSql('DROP INDEX IF EXISTS idx_testimonial_featured');
        $this->addSql('DROP INDEX IF EXISTS idx_testimonial_sort');
        $this->addSql('DROP INDEX IF EXISTS idx_testimonial_rating');
        
        $this->addSql('DROP INDEX IF EXISTS idx_feature_active');
        $this->addSql('DROP INDEX IF EXISTS idx_feature_featured');
        $this->addSql('DROP INDEX IF EXISTS idx_feature_sort');
        
        $this->addSql('DROP INDEX IF EXISTS idx_pricing_active');
        $this->addSql('DROP INDEX IF EXISTS idx_pricing_featured');
        $this->addSql('DROP INDEX IF EXISTS idx_pricing_sort');
        
        // Remove translation table indexes
        $this->addSql('DROP INDEX IF EXISTS idx_faq_translation_language');
        $this->addSql('DROP INDEX IF EXISTS idx_feature_translation_language');
        $this->addSql('DROP INDEX IF EXISTS idx_testimonial_translation_language');
        $this->addSql('DROP INDEX IF EXISTS idx_pricing_translation_language');
        
        // Remove slug indexes and columns
        $this->addSql('DROP INDEX IF EXISTS IDX_FAQ_SLUG');
        $this->addSql('DROP INDEX IF EXISTS IDX_TESTIMONIAL_SLUG');
        
        // Note: SQLite doesn't support DROP COLUMN, so we keep the columns
        // In production with PostgreSQL/MySQL, you would uncomment:
        // $this->addSql('ALTER TABLE faqs DROP COLUMN slug');
        // $this->addSql('ALTER TABLE testimonials DROP COLUMN slug');
    }
}