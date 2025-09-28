<?php

namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;

/**
 * Trait for entities with status and ordering capabilities
 */
trait StatusableOrderableTrait
{
    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isFeatured = false;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * Check if entity is active and can be displayed
     */
    public function isDisplayable(): bool
    {
        return $this->isActive;
    }

    /**
     * Check if entity should be highlighted
     */
    public function shouldBeHighlighted(): bool
    {
        return $this->isActive && $this->isFeatured;
    }
}