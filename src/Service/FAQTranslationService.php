<?php

namespace App\Service;

use App\Entity\FAQ;
use App\Entity\FAQTranslation;
use App\Entity\Language;
use App\Repository\LanguageRepository;
use App\Repository\FAQRepository;
use App\Repository\FAQTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;

class FAQTranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FAQRepository $faqRepository,
        private FAQTranslationRepository $faqTranslationRepository,
        private LanguageRepository $languageRepository
    ) {}

    /**
     * Create or update a FAQ with translations
     */
    public function createOrUpdateFAQ(FAQ $faq, array $translationsData): FAQ
    {
        $faq->setUpdatedAt();
        $this->entityManager->persist($faq);
        
        // For new FAQs, we need to flush first to get an ID before handling translations
        $isNewFAQ = $faq->getId() === null;
        if ($isNewFAQ) {
            $this->entityManager->flush();
        }

        // Handle translations
        foreach ($translationsData as $languageCode => $data) {
            $language = $this->languageRepository->findByCode($languageCode);
            if (!$language || !$language->isActive()) {
                continue;
            }

            $translation = null;
            if (!$isNewFAQ) {
                $translation = $this->faqTranslationRepository->findByFAQAndLanguage($faq, $language);
            }
            
            if (!$translation) {
                $translation = new FAQTranslation();
                $translation->setFaq($faq);
                $translation->setLanguage($language);
                $faq->addTranslation($translation);
            }

            $translation->setQuestion($data['question'] ?? '');
            $translation->setAnswer($data['answer'] ?? '');
            $translation->setUpdatedAt();

            $this->entityManager->persist($translation);
        }

        $this->entityManager->flush();
        return $faq;
    }

    /**
     * Duplicate translation to another language
     */
    public function duplicateTranslation(FAQ $faq, string $sourceLanguageCode, string $targetLanguageCode): ?FAQTranslation
    {
        $sourceLanguage = $this->languageRepository->findByCode($sourceLanguageCode);
        $targetLanguage = $this->languageRepository->findByCode($targetLanguageCode);

        if (!$sourceLanguage || !$targetLanguage) {
            return null;
        }

        $sourceTranslation = $faq->getTranslation($sourceLanguageCode);
        if (!$sourceTranslation) {
            return null;
        }

        // Check if target translation already exists
        $existingTranslation = $faq->getTranslation($targetLanguageCode);
        if ($existingTranslation) {
            return $existingTranslation;
        }

        $newTranslation = $this->faqTranslationRepository->duplicateTranslation($sourceTranslation, $targetLanguage);
        $faq->addTranslation($newTranslation);
        
        $this->entityManager->persist($newTranslation);
        $this->entityManager->flush();

        return $newTranslation;
    }

    /**
     * Get FAQs with translation status for admin
     */
    public function getFAQsWithTranslationStatus(): array
    {
        $faqs = $this->faqRepository->findActiveFAQs();
        $languages = $this->languageRepository->findActiveLanguages();
        $result = [];

        foreach ($faqs as $faq) {
            $faqData = [
                'faq' => $faq,
                'translations' => [],
                'completionPercentage' => 0
            ];

            $totalFields = 0;
            $completedFields = 0;

            foreach ($languages as $language) {
                $translation = $faq->getTranslation($language->getCode());
                $status = [
                    'language' => $language,
                    'translation' => $translation,
                    'complete' => false,
                    'partial' => false,
                    'missing' => true
                ];

                if ($translation) {
                    $status['missing'] = false;
                    $status['complete'] = $translation->isComplete();
                    $status['partial'] = $translation->isPartial();
                    
                    // Count fields for completion percentage
                    $totalFields += 2; // question, answer
                    $completedFields += array_sum([
                        !empty($translation->getQuestion()),
                        !empty($translation->getAnswer())
                    ]);
                } else {
                    $totalFields += 2;
                }

                $faqData['translations'][$language->getCode()] = $status;
            }

            $faqData['completionPercentage'] = $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;
            $result[] = $faqData;
        }

        return $result;
    }

    /**
     * Get global translation statistics
     */
    public function getGlobalTranslationStatistics(): array
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $totalFAQs = count($this->faqRepository->findActiveFAQs());
        $statistics = [];

        foreach ($languages as $language) {
            $stats = $this->faqTranslationRepository->getTranslationStatistics($language->getCode());
            $missing = $totalFAQs - $stats['total'];
            
            $statistics[$language->getCode()] = [
                'language' => $language,
                'total_faqs' => $totalFAQs,
                'translated' => $stats['total'],
                'complete' => $stats['complete'],
                'incomplete' => $stats['incomplete'],
                'missing' => $missing,
                'completion_percentage' => $stats['percentage']
            ];
        }

        return $statistics;
    }

    /**
     * Create missing translations for all FAQs in a language
     */
    public function createMissingTranslations(string $languageCode, ?string $sourceLanguageCode = null): int
    {
        $language = $this->languageRepository->findByCode($languageCode);
        if (!$language) {
            return 0;
        }

        $sourceLanguage = null;
        if ($sourceLanguageCode) {
            $sourceLanguage = $this->languageRepository->findByCode($sourceLanguageCode);
        }
        
        if (!$sourceLanguage) {
            $sourceLanguage = $this->languageRepository->findDefaultLanguage();
        }

        $faqs = $this->faqRepository->findActiveFAQs();
        $created = 0;

        foreach ($faqs as $faq) {
            // Skip if translation already exists
            if ($faq->hasTranslation($languageCode)) {
                continue;
            }

            $translation = new FAQTranslation();
            $translation->setFaq($faq);
            $translation->setLanguage($language);

            // Copy from source language if available
            if ($sourceLanguage) {
                $sourceTranslation = $faq->getTranslation($sourceLanguage->getCode());
                if ($sourceTranslation) {
                    $translation->setQuestion($sourceTranslation->getQuestion());
                    $translation->setAnswer($sourceTranslation->getAnswer());
                }
            }

            $this->entityManager->persist($translation);
            $faq->addTranslation($translation);
            $created++;
        }

        $this->entityManager->flush();
        return $created;
    }

    /**
     * Remove all translations for a language
     */
    public function removeTranslationsForLanguage(string $languageCode): int
    {
        $translations = $this->faqTranslationRepository->findByLanguageCode($languageCode);
        $count = count($translations);

        foreach ($translations as $translation) {
            $this->entityManager->remove($translation);
        }

        $this->entityManager->flush();
        return $count;
    }

    /**
     * Search FAQs in a specific language
     */
    public function searchFAQs(string $query, string $languageCode): array
    {
        return $this->faqTranslationRepository->searchInTranslations($query, $languageCode);
    }
}