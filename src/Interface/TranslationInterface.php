<?php

namespace App\Interface;

use App\Entity\Language;

/**
 * Interface for translation entities
 */
interface TranslationInterface
{
    /**
     * Get the associated language
     */
    public function getLanguage(): ?Language;

    /**
     * Set the associated language
     */
    public function setLanguage(?Language $language): static;

    /**
     * Check if translation is complete (all required fields filled)
     */
    public function isComplete(): bool;

    /**
     * Check if translation is partial (some fields filled)
     */
    public function isPartial(): bool;

    /**
     * Get completion percentage (0-100)
     */
    public function getCompletionPercentage(): int;
}