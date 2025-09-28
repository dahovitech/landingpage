<?php

namespace App\Entity;

use App\Repository\PricingPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PricingPlanRepository::class)]
#[ORM\Table(name: 'pricing_plans')]
class PricingPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $price = '0.00';

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['monthly', 'yearly', 'one-time', 'free'])]
    private string $billingPeriod = 'monthly';

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 10)]
    private string $currency = 'EUR';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $features = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $maxUsers = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $maxProjects = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $storageLimit = null; // in GB

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isPopular = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isFree = false;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $stripeProductId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $stripePriceId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'pricingPlan', targetEntity: PricingPlanTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->features = [];
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getBillingPeriod(): string
    {
        return $this->billingPeriod;
    }

    public function setBillingPeriod(string $billingPeriod): static
    {
        $this->billingPeriod = $billingPeriod;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getFeatures(): ?array
    {
        return $this->features;
    }

    public function setFeatures(?array $features): static
    {
        $this->features = $features;
        return $this;
    }

    public function getMaxUsers(): ?int
    {
        return $this->maxUsers;
    }

    public function setMaxUsers(?int $maxUsers): static
    {
        $this->maxUsers = $maxUsers;
        return $this;
    }

    public function getMaxProjects(): ?int
    {
        return $this->maxProjects;
    }

    public function setMaxProjects(?int $maxProjects): static
    {
        $this->maxProjects = $maxProjects;
        return $this;
    }

    public function getStorageLimit(): ?int
    {
        return $this->storageLimit;
    }

    public function setStorageLimit(?int $storageLimit): static
    {
        $this->storageLimit = $storageLimit;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isPopular(): bool
    {
        return $this->isPopular;
    }

    public function setIsPopular(bool $isPopular): static
    {
        $this->isPopular = $isPopular;
        return $this;
    }

    public function isFree(): bool
    {
        return $this->isFree;
    }

    public function setIsFree(bool $isFree): static
    {
        $this->isFree = $isFree;
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

    public function getStripeProductId(): ?string
    {
        return $this->stripeProductId;
    }

    public function setStripeProductId(?string $stripeProductId): static
    {
        $this->stripeProductId = $stripeProductId;
        return $this;
    }

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function setStripePriceId(?string $stripePriceId): static
    {
        $this->stripePriceId = $stripePriceId;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * @return Collection<int, PricingPlanTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(PricingPlanTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setPricingPlan($this);
        }

        return $this;
    }

    public function removeTranslation(PricingPlanTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getPricingPlan() === $this) {
                $translation->setPricingPlan(null);
            }
        }

        return $this;
    }

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): ?PricingPlanTranslation
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
    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): ?PricingPlanTranslation
    {
        $translation = $this->getTranslation($languageCode);
        
        if (!$translation) {
            $translation = $this->getTranslation($fallbackLanguageCode);
        }

        return $translation;
    }

    /**
     * Get name for a specific language with fallback
     */
    public function getName(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getName() : 'Untitled Plan';
    }

    /**
     * Get description for a specific language with fallback
     */
    public function getDescription(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getDescription() : '';
    }

    /**
     * Check if plan has translation for a specific language
     */
    public function hasTranslation(string $languageCode): bool
    {
        return $this->getTranslation($languageCode) !== null;
    }

    /**
     * Get completion status for all translations
     */
    public function getTranslationStatus(): array
    {
        $status = [];
        foreach ($this->translations as $translation) {
            $status[$translation->getLanguage()->getCode()] = [
                'complete' => !empty($translation->getName()) && !empty($translation->getDescription()),
                'partial' => !empty($translation->getName()) || !empty($translation->getDescription()),
                'translation' => $translation
            ];
        }
        return $status;
    }

    /**
     * Get formatted price with currency
     */
    public function getFormattedPrice(): string
    {
        $price = number_format((float) $this->price, 2);
        return $price . ' ' . $this->currency;
    }

    /**
     * Get price per month (for yearly plans)
     */
    public function getMonthlyPrice(): string
    {
        if ($this->billingPeriod === 'yearly') {
            return number_format((float) $this->price / 12, 2);
        }
        return $this->price;
    }

    public function __toString(): string
    {
        return $this->getName();
    }
}