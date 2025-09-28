<?php

namespace App\Entity;

use App\Interface\TranslatableInterface;
use App\Repository\FAQRepository;
use App\Trait\StatusableOrderableTrait;
use App\Trait\TimestampableTrait;
use App\Trait\TranslatableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FAQRepository::class)]
#[ORM\Table(name: 'faqs')]
#[ORM\Index(columns: ['is_active'], name: 'idx_faq_active')]
#[ORM\Index(columns: ['is_featured'], name: 'idx_faq_featured')]
#[ORM\Index(columns: ['sort_order'], name: 'idx_faq_sort')]
#[ORM\Index(columns: ['category'], name: 'idx_faq_category')]
#[ORM\HasLifecycleCallbacks]
class FAQ implements TranslatableInterface
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

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $category = null;

    #[ORM\OneToMany(mappedBy: 'faq', targetEntity: FAQTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Collection<int, FAQTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(FAQTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setFaq($this);
        }

        return $this;
    }

    public function removeTranslation(FAQTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getFaq() === $this) {
                $translation->setFaq(null);
            }
        }

        return $this;
    }

    /**
     * Get question for a specific language with fallback
     */
    public function getQuestion(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getQuestion() : 'Untitled Question';
    }

    /**
     * Get answer for a specific language with fallback
     */
    public function getAnswer(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getAnswer() : '';
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

    public function __toString(): string
    {
        return $this->getQuestion();
    }
}