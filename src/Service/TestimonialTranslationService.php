<?php

namespace App\Service;

use App\Entity\Testimonial;
use App\Entity\TestimonialTranslation;
use App\Entity\Language;
use App\Repository\LanguageRepository;
use App\Repository\TestimonialRepository;
use App\Repository\TestimonialTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;

class TestimonialTranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TestimonialRepository $testimonialRepository,
        private TestimonialTranslationRepository $testimonialTranslationRepository,
        private LanguageRepository $languageRepository
    ) {}

    /**
     * Create or update a testimonial with translations
     */
    public function createOrUpdateTestimonial(Testimonial $testimonial, array $translationsData): Testimonial
    {
        $testimonial->setUpdatedAt();
        $this->entityManager->persist($testimonial);
        
        // For new testimonials, we need to flush first to get an ID before handling translations
        $isNewTestimonial = $testimonial->getId() === null;
        if ($isNewTestimonial) {
            $this->entityManager->flush();
        }

        // Handle translations
        foreach ($translationsData as $languageCode => $data) {
            $language = $this->languageRepository->findByCode($languageCode);
            if (!$language || !$language->isActive()) {
                continue;
            }

            $translation = null;
            if (!$isNewTestimonial) {
                $translation = $this->testimonialTranslationRepository->findByTestimonialAndLanguage($testimonial, $language);
            }
            
            if (!$translation) {
                $translation = new TestimonialTranslation();
                $translation->setTestimonial($testimonial);
                $translation->setLanguage($language);
                $testimonial->addTranslation($translation);
            }

            $translation->setContent($data['content'] ?? '');
            $translation->setUpdatedAt();

            $this->entityManager->persist($translation);
        }

        $this->entityManager->flush();
        return $testimonial;
    }

    /**
     * Duplicate translation to another language
     */
    public function duplicateTranslation(Testimonial $testimonial, string $sourceLanguageCode, string $targetLanguageCode): ?TestimonialTranslation
    {
        $sourceLanguage = $this->languageRepository->findByCode($sourceLanguageCode);
        $targetLanguage = $this->languageRepository->findByCode($targetLanguageCode);

        if (!$sourceLanguage || !$targetLanguage) {
            return null;
        }

        $sourceTranslation = $testimonial->getTranslation($sourceLanguageCode);
        if (!$sourceTranslation) {
            return null;
        }

        // Check if target translation already exists
        $existingTranslation = $testimonial->getTranslation($targetLanguageCode);
        if ($existingTranslation) {
            return $existingTranslation;
        }

        $newTranslation = $this->testimonialTranslationRepository->duplicateTranslation($sourceTranslation, $targetLanguage);
        $testimonial->addTranslation($newTranslation);
        
        $this->entityManager->persist($newTranslation);
        $this->entityManager->flush();

        return $newTranslation;
    }

    /**
     * Get testimonials with translation status for admin
     */
    public function getTestimonialsWithTranslationStatus(): array
    {
        $testimonials = $this->testimonialRepository->findActiveTestimonials();
        $languages = $this->languageRepository->findActiveLanguages();
        $result = [];

        foreach ($testimonials as $testimonial) {
            $testimonialData = [
                'testimonial' => $testimonial,
                'translations' => [],
                'completionPercentage' => 0
            ];

            $totalFields = 0;
            $completedFields = 0;

            foreach ($languages as $language) {
                $translation = $testimonial->getTranslation($language->getCode());
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
                    $totalFields += 1; // content
                    $completedFields += !empty($translation->getContent()) ? 1 : 0;
                } else {
                    $totalFields += 1;
                }

                $testimonialData['translations'][$language->getCode()] = $status;
            }

            $testimonialData['completionPercentage'] = $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;
            $result[] = $testimonialData;
        }

        return $result;
    }

    /**
     * Get global translation statistics
     */
    public function getGlobalTranslationStatistics(): array
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $totalTestimonials = count($this->testimonialRepository->findActiveTestimonials());
        $statistics = [];

        foreach ($languages as $language) {
            $stats = $this->testimonialTranslationRepository->getTranslationStatistics($language->getCode());
            $missing = $totalTestimonials - $stats['total'];
            
            $statistics[$language->getCode()] = [
                'language' => $language,
                'total_testimonials' => $totalTestimonials,
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
     * Create missing translations for all testimonials in a language
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

        $testimonials = $this->testimonialRepository->findActiveTestimonials();
        $created = 0;

        foreach ($testimonials as $testimonial) {
            // Skip if translation already exists
            if ($testimonial->hasTranslation($languageCode)) {
                continue;
            }

            $translation = new TestimonialTranslation();
            $translation->setTestimonial($testimonial);
            $translation->setLanguage($language);

            // Copy from source language if available
            if ($sourceLanguage) {
                $sourceTranslation = $testimonial->getTranslation($sourceLanguage->getCode());
                if ($sourceTranslation) {
                    $translation->setContent($sourceTranslation->getContent());
                }
            }

            $this->entityManager->persist($translation);
            $testimonial->addTranslation($translation);
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
        $translations = $this->testimonialTranslationRepository->findByLanguageCode($languageCode);
        $count = count($translations);

        foreach ($translations as $translation) {
            $this->entityManager->remove($translation);
        }

        $this->entityManager->flush();
        return $count;
    }
}