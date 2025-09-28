<?php

namespace App\Service;

use App\Entity\Feature;
use App\Entity\FeatureTranslation;
use App\Entity\Language;
use App\Repository\LanguageRepository;
use App\Repository\FeatureRepository;
use App\Repository\FeatureTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class FeatureTranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FeatureRepository $featureRepository,
        private FeatureTranslationRepository $featureTranslationRepository,
        private LanguageRepository $languageRepository,
        private SluggerInterface $slugger
    ) {}

    /**
     * Create or update a feature with translations
     */
    public function createOrUpdateFeature(Feature $feature, array $translationsData): Feature
    {
        // Generate slug if not set
        if (empty($feature->getSlug()) && !empty($translationsData)) {
            $defaultLang = $this->languageRepository->findDefaultLanguage();
            $defaultTranslation = $translationsData[$defaultLang->getCode()] ?? reset($translationsData);
            if (!empty($defaultTranslation['title'])) {
                $feature->setSlug($this->generateUniqueSlug($defaultTranslation['title']));
            }
        }

        $feature->setUpdatedAt();
        $this->entityManager->persist($feature);
        
        // For new features, we need to flush first to get an ID before handling translations
        $isNewFeature = $feature->getId() === null;
        if ($isNewFeature) {
            $this->entityManager->flush();
        }

        // Handle translations
        foreach ($translationsData as $languageCode => $data) {
            $language = $this->languageRepository->findByCode($languageCode);
            if (!$language || !$language->isActive()) {
                continue;
            }

            $translation = null;
            if (!$isNewFeature) {
                $translation = $this->featureTranslationRepository->findByFeatureAndLanguage($feature, $language);
            }
            
            if (!$translation) {
                $translation = new FeatureTranslation();
                $translation->setFeature($feature);
                $translation->setLanguage($language);
                $feature->addTranslation($translation);
            }

            $translation->setTitle($data['title'] ?? '');
            $translation->setDescription($data['description'] ?? '');
            $translation->setMetaTitle($data['metaTitle'] ?? null);
            $translation->setMetaDescription($data['metaDescription'] ?? null);
            $translation->setUpdatedAt();

            $this->entityManager->persist($translation);
        }

        $this->entityManager->flush();
        return $feature;
    }

    /**
     * Generate unique slug
     */
    public function generateUniqueSlug(string $title): string
    {
        $baseSlug = $this->slugger->slug($title)->lower();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->featureRepository->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Duplicate translation to another language
     */
    public function duplicateTranslation(Feature $feature, string $sourceLanguageCode, string $targetLanguageCode): ?FeatureTranslation
    {
        $sourceLanguage = $this->languageRepository->findByCode($sourceLanguageCode);
        $targetLanguage = $this->languageRepository->findByCode($targetLanguageCode);

        if (!$sourceLanguage || !$targetLanguage) {
            return null;
        }

        $sourceTranslation = $feature->getTranslation($sourceLanguageCode);
        if (!$sourceTranslation) {
            return null;
        }

        // Check if target translation already exists
        $existingTranslation = $feature->getTranslation($targetLanguageCode);
        if ($existingTranslation) {
            return $existingTranslation;
        }

        $newTranslation = $this->featureTranslationRepository->duplicateTranslation($sourceTranslation, $targetLanguage);
        $feature->addTranslation($newTranslation);
        
        $this->entityManager->persist($newTranslation);
        $this->entityManager->flush();

        return $newTranslation;
    }

    /**
     * Get features with translation status for admin
     */
    public function getFeaturesWithTranslationStatus(): array
    {
        $features = $this->featureRepository->findActiveFeatures();
        $languages = $this->languageRepository->findActiveLanguages();
        $result = [];

        foreach ($features as $feature) {
            $featureData = [
                'feature' => $feature,
                'translations' => [],
                'completionPercentage' => 0
            ];

            $totalFields = 0;
            $completedFields = 0;

            foreach ($languages as $language) {
                $translation = $feature->getTranslation($language->getCode());
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
                    $totalFields += 4; // title, description, metaTitle, metaDescription
                    $completedFields += array_sum([
                        !empty($translation->getTitle()),
                        !empty($translation->getDescription()),
                        !empty($translation->getMetaTitle()),
                        !empty($translation->getMetaDescription())
                    ]);
                } else {
                    $totalFields += 4;
                }

                $featureData['translations'][$language->getCode()] = $status;
            }

            $featureData['completionPercentage'] = $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;
            $result[] = $featureData;
        }

        return $result;
    }

    /**
     * Get global translation statistics
     */
    public function getGlobalTranslationStatistics(): array
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $totalFeatures = count($this->featureRepository->findActiveFeatures());
        $statistics = [];

        foreach ($languages as $language) {
            $stats = $this->featureTranslationRepository->getTranslationStatistics($language->getCode());
            $missing = $totalFeatures - $stats['total'];
            
            $statistics[$language->getCode()] = [
                'language' => $language,
                'total_features' => $totalFeatures,
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
     * Create missing translations for all features in a language
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

        $features = $this->featureRepository->findActiveFeatures();
        $created = 0;

        foreach ($features as $feature) {
            // Skip if translation already exists
            if ($feature->hasTranslation($languageCode)) {
                continue;
            }

            $translation = new FeatureTranslation();
            $translation->setFeature($feature);
            $translation->setLanguage($language);

            // Copy from source language if available
            if ($sourceLanguage) {
                $sourceTranslation = $feature->getTranslation($sourceLanguage->getCode());
                if ($sourceTranslation) {
                    $translation->setTitle($sourceTranslation->getTitle());
                    $translation->setDescription($sourceTranslation->getDescription());
                    $translation->setMetaTitle($sourceTranslation->getMetaTitle());
                    $translation->setMetaDescription($sourceTranslation->getMetaDescription());
                }
            }

            $this->entityManager->persist($translation);
            $feature->addTranslation($translation);
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
        $translations = $this->featureTranslationRepository->findByLanguageCode($languageCode);
        $count = count($translations);

        foreach ($translations as $translation) {
            $this->entityManager->remove($translation);
        }

        $this->entityManager->flush();
        return $count;
    }
}