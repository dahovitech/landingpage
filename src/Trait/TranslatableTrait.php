<?php

namespace App\Trait;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Trait for entities that support translations
 * Provides common translation methods to avoid code duplication
 */
trait TranslatableTrait
{
    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): mixed
    {
        if ($languageCode === null) {
            return $this->translations->first() ?: null;
        }

        foreach ($this->translations as $translation) {
            if ($translation->getLanguage()->getCode() === $languageCode) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Get translation with fallback
     */
    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): mixed
    {
        $translation = $this->getTranslation($languageCode);
        
        if (!$translation) {
            $translation = $this->getTranslation($fallbackLanguageCode);
        }

        return $translation;
    }

    /**
     * Check if entity has translation for a specific language
     */
    public function hasTranslation(string $languageCode): bool
    {
        return $this->getTranslation($languageCode) !== null;
    }

    /**
     * Get available language codes for this entity
     */
    public function getAvailableLanguages(): array
    {
        $languages = [];
        foreach ($this->translations as $translation) {
            $languages[] = $translation->getLanguage()->getCode();
        }
        return array_unique($languages);
    }

    /**
     * Count total translations
     */
    public function getTranslationsCount(): int
    {
        return $this->translations->count();
    }

    /**
     * Check if all translations are complete
     */
    public function areAllTranslationsComplete(): bool
    {
        foreach ($this->translations as $translation) {
            if (!$translation->isComplete()) {
                return false;
            }
        }
        return $this->translations->count() > 0;
    }

    /**
     * Get completion percentage across all translations
     */
    public function getGlobalCompletionPercentage(): float
    {
        if ($this->translations->isEmpty()) {
            return 0.0;
        }

        $totalPercentage = 0;
        foreach ($this->translations as $translation) {
            $totalPercentage += $translation->getCompletionPercentage();
        }

        return round($totalPercentage / $this->translations->count(), 1);
    }
}