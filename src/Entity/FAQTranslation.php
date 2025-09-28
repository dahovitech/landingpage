<?php

namespace App\Entity;

use App\Interface\TranslationInterface;
use App\Repository\FAQTranslationRepository;
use App\Trait\TimestampableTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FAQTranslationRepository::class)]
#[ORM\Table(name: 'faq_translations')]
#[ORM\UniqueConstraint(name: 'UNIQ_FAQ_LANGUAGE', columns: ['faq_id', 'language_id'])]
#[ORM\Index(columns: ['language_id'], name: 'idx_faq_translation_language')]
#[ORM\HasLifecycleCallbacks]
class FAQTranslation implements TranslationInterface
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FAQ::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FAQ $faq = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Language $language = null;

    #[ORM\Column(type: 'string', length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $question = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private string $answer = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFaq(): ?FAQ
    {
        return $this->faq;
    }

    public function setFaq(?FAQ $faq): static
    {
        $this->faq = $faq;
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

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function setQuestion(string $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }

    public function setAnswer(string $answer): static
    {
        $this->answer = $answer;
        return $this;
    }

    /**
     * Check if translation is complete
     */
    public function isComplete(): bool
    {
        return !empty($this->question) && !empty($this->answer);
    }

    /**
     * Check if translation is partial (has some content)
     */
    public function isPartial(): bool
    {
        return !empty($this->question) || !empty($this->answer);
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentage(): int
    {
        $fields = [
            !empty($this->question),
            !empty($this->answer)
        ];
        
        $completed = array_sum($fields);
        return (int) round(($completed / count($fields)) * 100);
    }

    /**
     * Get question excerpt
     */
    public function getQuestionExcerpt(int $length = 100): string
    {
        if (empty($this->question)) {
            return '';
        }

        if (strlen($this->question) <= $length) {
            return $this->question;
        }

        return substr($this->question, 0, $length) . '...';
    }

    /**
     * Get answer excerpt
     */
    public function getAnswerExcerpt(int $length = 150): string
    {
        if (empty($this->answer)) {
            return '';
        }

        if (strlen($this->answer) <= $length) {
            return $this->answer;
        }

        return substr($this->answer, 0, $length) . '...';
    }

    public function __toString(): string
    {
        return $this->getQuestionExcerpt(50) . ' (' . $this->language?->getCode() . ')';
    }
}