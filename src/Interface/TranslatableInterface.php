<?php

namespace App\Interface;

use Doctrine\Common\Collections\Collection;

/**
 * Interface for entities that support translations
 */
interface TranslatableInterface
{
    /**
     * Get all translations
     */
    public function getTranslations(): Collection;

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): mixed;

    /**
     * Get translation with fallback
     */
    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): mixed;

    /**
     * Check if entity has translation for a specific language
     */
    public function hasTranslation(string $languageCode): bool;

    /**
     * Get available language codes
     */
    public function getAvailableLanguages(): array;

    /**
     * Get the entity's slug for URL generation
     */
    public function getSlug(): ?string;
}