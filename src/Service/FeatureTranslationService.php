<?php

namespace App\Service;

use App\Entity\Feature;
use App\Entity\FeatureTranslation;
use App\Entity\Language;
use App\Repository\LanguageRepository;
use App\Repository\FeatureRepository;
use App\Repository\FeatureTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\SlugGeneratorService;

class FeatureTranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FeatureRepository $featureRepository,
        private FeatureTranslationRepository $featureTranslationRepository,
        private LanguageRepository $languageRepository,
        private SlugGeneratorService $slugGenerator
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
                $slug = $this->slugGenerator->generateUniqueSlug(
                    $defaultTranslation['title'], 
                    Feature::class, 
                    $feature->getId()
                );
                $feature->setSlug($slug);
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

            // Sanitize and validate data
            $translation->setTitle($this->sanitizeText($data['title'] ?? ''));
            $translation->setDescription($this->sanitizeText($data['description'] ?? ''));
            $translation->setMetaTitle($this->sanitizeText($data['metaTitle'] ?? null));
            $translation->setMetaDescription($this->sanitizeText($data['metaDescription'] ?? null));
            $translation->setUpdatedAt();

            $this->entityManager->persist($translation);
        }

        $this->entityManager->flush();
        return $feature;
    }

    /**
     * Sanitize text input to prevent XSS
     */
    private function sanitizeText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        
        return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Duplicate translation to another language
     */
    public function duplicateTranslation(Feature $feature, string $sourceLanguageCode, string $targetLanguageCode): ?FeatureTranslation
    {
        $sourceLanguage = $this->languageRepository->findByCode($sourceLanguageCode);
        $targetLanguage = $this->languageRepository->findByCode($targetLanguageCode);

        if (!$sourceLanguage || !$targetLanguage) {
            throw new \InvalidArgumentException('Source or target language not found');
        }

        $sourceTranslation = $feature->getTranslation($sourceLanguageCode);
        if (!$sourceTranslation) {
            throw new \InvalidArgumentException('Source translation not found');
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
        // Use optimized query with joins to avoid N+1 problem
        $features = $this->featureRepository->findWithTranslations();
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
        $totalFeatures = $this->featureRepository->countActive();
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
        if (!$language || !$language->isActive()) {
            throw new \InvalidArgumentException("Language {$languageCode} not found or inactive");
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

        if ($created > 0) {
            $this->entityManager->flush();
        }
        
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

        if ($count > 0) {
            $this->entityManager->flush();
        }
        
        return $count;
    }

    /**
     * Search features by text in translations
     */
    public function searchFeatures(string $query, ?string $languageCode = null, int $limit = 20): array
    {
        return $this->featureRepository->searchByTranslationText($query, $languageCode, $limit);
    }

    /**
     * Validate feature data before processing
     */
    public function validateFeatureData(array $data): array
    {
        $errors = [];

        // Validate required fields
        if (empty($data['translations']) || !is_array($data['translations'])) {
            $errors[] = 'At least one translation is required';
        }

        // Validate each translation
        foreach ($data['translations'] ?? [] as $langCode => $translation) {
            if (empty($translation['title'])) {
                $errors[] = "Title is required for language {$langCode}";
            }
            
            if (!empty($translation['title']) && strlen($translation['title']) > 255) {
                $errors[] = "Title too long for language {$langCode} (max 255 characters)";
            }
        }

        return $errors;
    }
}