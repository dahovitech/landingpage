<?php

namespace App\Entity;

use App\Repository\TestimonialTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TestimonialTranslationRepository::class)]
#[ORM\Table(name: 'testimonial_translations')]
#[ORM\UniqueConstraint(name: 'UNIQ_TESTIMONIAL_LANGUAGE', columns: ['testimonial_id', 'language_id'])]
class TestimonialTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Testimonial::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Testimonial $testimonial = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Language $language = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $content = '';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTestimonial(): ?Testimonial
    {
        return $this->testimonial;
    }

    public function setTestimonial(?Testimonial $testimonial): static
    {
        $this->testimonial = $testimonial;
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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
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
        return !empty($this->content);
    }

    /**
     * Check if translation is partial (has some content)
     */
    public function isPartial(): bool
    {
        return !empty($this->content);
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentage(): int
    {
        return !empty($this->content) ? 100 : 0;
    }

    /**
     * Get content excerpt
     */
    public function getContentExcerpt(int $length = 100): string
    {
        if (empty($this->content)) {
            return '';
        }

        if (strlen($this->content) <= $length) {
            return $this->content;
        }

        return substr($this->content, 0, $length) . '...';
    }

    public function __toString(): string
    {
        return $this->getContentExcerpt(50) . ' (' . $this->language?->getCode() . ')';
    }
}