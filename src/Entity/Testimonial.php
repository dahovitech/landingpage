<?php

namespace App\Entity;

use App\Interface\TranslatableInterface;
use App\Repository\TestimonialRepository;
use App\Trait\StatusableOrderableTrait;
use App\Trait\TimestampableTrait;
use App\Trait\TranslatableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TestimonialRepository::class)]
#[ORM\Table(name: 'testimonials')]
#[ORM\Index(columns: ['is_active'], name: 'idx_testimonial_active')]
#[ORM\Index(columns: ['is_featured'], name: 'idx_testimonial_featured')]
#[ORM\Index(columns: ['sort_order'], name: 'idx_testimonial_sort')]
#[ORM\Index(columns: ['rating'], name: 'idx_testimonial_rating')]
#[ORM\HasLifecycleCallbacks]
class Testimonial implements TranslatableInterface
{
    use TimestampableTrait;
    use StatusableOrderableTrait;
    use TranslatableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $clientName = '';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $clientPosition = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $clientCompany = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $clientEmail = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Media $clientAvatar = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $rating = null;

    #[ORM\OneToMany(mappedBy: 'testimonial', targetEntity: TestimonialTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
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

    public function getClientName(): string
    {
        return $this->clientName;
    }

    public function setClientName(string $clientName): static
    {
        $this->clientName = $clientName;
        return $this;
    }

    public function getClientPosition(): ?string
    {
        return $this->clientPosition;
    }

    public function setClientPosition(?string $clientPosition): static
    {
        $this->clientPosition = $clientPosition;
        return $this;
    }

    public function getClientCompany(): ?string
    {
        return $this->clientCompany;
    }

    public function setClientCompany(?string $clientCompany): static
    {
        $this->clientCompany = $clientCompany;
        return $this;
    }

    public function getClientEmail(): ?string
    {
        return $this->clientEmail;
    }

    public function setClientEmail(?string $clientEmail): static
    {
        $this->clientEmail = $clientEmail;
        return $this;
    }

    public function getClientAvatar(): ?Media
    {
        return $this->clientAvatar;
    }

    public function setClientAvatar(?Media $clientAvatar): static
    {
        $this->clientAvatar = $clientAvatar;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;
        return $this;
    }

    /**
     * @return Collection<int, TestimonialTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(TestimonialTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setTestimonial($this);
        }

        return $this;
    }

    public function removeTranslation(TestimonialTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getTestimonial() === $this) {
                $translation->setTestimonial(null);
            }
        }

        return $this;
    }

    /**
     * Get content for a specific language with fallback
     */
    public function getContent(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getContent() : '';
    }

    /**
     * Get completion status for all translations
     */
    public function getTranslationStatus(): array
    {
        $status = [];
        foreach ($this->translations as $translation) {
            $status[$translation->getLanguage()->getCode()] = [
                'complete' => $translation->isComplete(),
                'partial' => $translation->isPartial(),
                'translation' => $translation
            ];
        }
        return $status;
    }

    /**
     * Get client full info
     */
    public function getClientFullInfo(): string
    {
        $info = $this->clientName;
        
        if ($this->clientPosition) {
            $info .= ', ' . $this->clientPosition;
        }
        
        if ($this->clientCompany) {
            $info .= ' chez ' . $this->clientCompany;
        }
        
        return $info;
    }

    /**
     * Get rating as stars string
     */
    public function getRatingStars(): string
    {
        if ($this->rating === null) {
            return '';
        }
        
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Check if testimonial has avatar
     */
    public function hasAvatar(): bool
    {
        return $this->clientAvatar !== null;
    }

    public function __toString(): string
    {
        return $this->clientName;
    }
}