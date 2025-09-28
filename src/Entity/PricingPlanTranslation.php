<?php

namespace App\Entity;

use App\Repository\PricingPlanTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PricingPlanTranslationRepository::class)]
#[ORM\Table(name: 'pricing_plan_translations')]
#[ORM\UniqueConstraint(name: 'UNIQ_PRICING_PLAN_LANGUAGE', columns: ['pricing_plan_id', 'language_id'])]
class PricingPlanTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PricingPlan::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PricingPlan $pricingPlan = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Language $language = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $features = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $ctaText = null; // Call-to-action button text

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->features = [];
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPricingPlan(): ?PricingPlan
    {
        return $this->pricingPlan;
    }

    public function setPricingPlan(?PricingPlan $pricingPlan): static
    {
        $this->pricingPlan = $pricingPlan;
        return $this;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
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

    public function getCtaText(): ?string
    {
        return $this->ctaText;
    }

    public function setCtaText(?string $ctaText): static
    {
        $this->ctaText = $ctaText;
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
     * Check if translation is complete
     */
    public function isComplete(): bool
    {
        return !empty($this->name) && !empty($this->description);
    }

    /**
     * Check if translation is partial (has some content)
     */
    public function isPartial(): bool
    {
        return !empty($this->name) || !empty($this->description);
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentage(): int
    {
        $fields = [
            !empty($this->name),
            !empty($this->description),
            !empty($this->features),
            !empty($this->ctaText)
        ];
        
        $completed = array_sum($fields);
        return (int) round(($completed / count($fields)) * 100);
    }

    /**
     * Add a feature to the features list
     */
    public function addFeature(string $feature): static
    {
        if (!in_array($feature, $this->features ?? [])) {
            $this->features[] = $feature;
        }
        return $this;
    }

    /**
     * Remove a feature from the features list
     */
    public function removeFeature(string $feature): static
    {
        $this->features = array_values(array_filter($this->features ?? [], fn($f) => $f !== $feature));
        return $this;
    }

    /**
     * Get features as formatted list
     */
    public function getFeaturesAsList(): array
    {
        return $this->features ?? [];
    }

    public function __toString(): string
    {
        return $this->name . ' (' . $this->language?->getCode() . ')';
    }
}