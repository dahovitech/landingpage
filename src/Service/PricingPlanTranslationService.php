<?php

namespace App\Service;

use App\Entity\PricingPlan;
use App\Entity\PricingPlanTranslation;
use App\Entity\Language;
use App\Repository\LanguageRepository;
use App\Repository\PricingPlanRepository;
use App\Repository\PricingPlanTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class PricingPlanTranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PricingPlanRepository $pricingPlanRepository,
        private PricingPlanTranslationRepository $pricingPlanTranslationRepository,
        private LanguageRepository $languageRepository,
        private SluggerInterface $slugger
    ) {}

    /**
     * Create or update a pricing plan with translations
     */
    public function createOrUpdatePricingPlan(PricingPlan $pricingPlan, array $translationsData): PricingPlan
    {
        // Generate slug if not set
        if (empty($pricingPlan->getSlug()) && !empty($translationsData)) {
            $defaultLang = $this->languageRepository->findDefaultLanguage();
            $defaultTranslation = $translationsData[$defaultLang->getCode()] ?? reset($translationsData);
            if (!empty($defaultTranslation['name'])) {
                $pricingPlan->setSlug($this->generateUniqueSlug($defaultTranslation['name']));
            }
        }

        $pricingPlan->setUpdatedAt();
        $this->entityManager->persist($pricingPlan);
        
        // For new pricing plans, we need to flush first to get an ID before handling translations
        $isNewPricingPlan = $pricingPlan->getId() === null;
        if ($isNewPricingPlan) {
            $this->entityManager->flush();
        }

        // Handle translations
        foreach ($translationsData as $languageCode => $data) {
            $language = $this->languageRepository->findByCode($languageCode);
            if (!$language || !$language->isActive()) {
                continue;
            }

            $translation = null;
            if (!$isNewPricingPlan) {
                $translation = $this->pricingPlanTranslationRepository->findByPricingPlanAndLanguage($pricingPlan, $language);
            }
            
            if (!$translation) {
                $translation = new PricingPlanTranslation();
                $translation->setPricingPlan($pricingPlan);
                $translation->setLanguage($language);
                $pricingPlan->addTranslation($translation);
            }

            $translation->setName($data['name'] ?? '');
            $translation->setDescription($data['description'] ?? '');
            $translation->setFeatures($data['features'] ?? []);
            $translation->setCtaText($data['ctaText'] ?? null);
            $translation->setUpdatedAt();

            $this->entityManager->persist($translation);
        }

        $this->entityManager->flush();
        return $pricingPlan;
    }

    /**
     * Generate unique slug
     */
    public function generateUniqueSlug(string $name): string
    {
        $baseSlug = $this->slugger->slug($name)->lower();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->pricingPlanRepository->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Duplicate translation to another language
     */
    public function duplicateTranslation(PricingPlan $pricingPlan, string $sourceLanguageCode, string $targetLanguageCode): ?PricingPlanTranslation
    {
        $sourceLanguage = $this->languageRepository->findByCode($sourceLanguageCode);
        $targetLanguage = $this->languageRepository->findByCode($targetLanguageCode);

        if (!$sourceLanguage || !$targetLanguage) {
            return null;
        }

        $sourceTranslation = $pricingPlan->getTranslation($sourceLanguageCode);
        if (!$sourceTranslation) {
            return null;
        }

        // Check if target translation already exists
        $existingTranslation = $pricingPlan->getTranslation($targetLanguageCode);
        if ($existingTranslation) {
            return $existingTranslation;
        }

        $newTranslation = $this->pricingPlanTranslationRepository->duplicateTranslation($sourceTranslation, $targetLanguage);
        $pricingPlan->addTranslation($newTranslation);
        
        $this->entityManager->persist($newTranslation);
        $this->entityManager->flush();

        return $newTranslation;
    }

    /**
     * Get pricing plans with translation status for admin
     */
    public function getPricingPlansWithTranslationStatus(): array
    {
        $pricingPlans = $this->pricingPlanRepository->findActivePlans();
        $languages = $this->languageRepository->findActiveLanguages();
        $result = [];

        foreach ($pricingPlans as $pricingPlan) {
            $pricingPlanData = [
                'pricingPlan' => $pricingPlan,
                'translations' => [],
                'completionPercentage' => 0
            ];

            $totalFields = 0;
            $completedFields = 0;

            foreach ($languages as $language) {
                $translation = $pricingPlan->getTranslation($language->getCode());
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
                    $totalFields += 4; // name, description, features, ctaText
                    $completedFields += array_sum([
                        !empty($translation->getName()),
                        !empty($translation->getDescription()),
                        !empty($translation->getFeatures()),
                        !empty($translation->getCtaText())
                    ]);
                } else {
                    $totalFields += 4;
                }

                $pricingPlanData['translations'][$language->getCode()] = $status;
            }

            $pricingPlanData['completionPercentage'] = $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;
            $result[] = $pricingPlanData;
        }

        return $result;
    }

    /**
     * Get global translation statistics
     */
    public function getGlobalTranslationStatistics(): array
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $totalPricingPlans = count($this->pricingPlanRepository->findActivePlans());
        $statistics = [];

        foreach ($languages as $language) {
            $stats = $this->pricingPlanTranslationRepository->getTranslationStatistics($language->getCode());
            $missing = $totalPricingPlans - $stats['total'];
            
            $statistics[$language->getCode()] = [
                'language' => $language,
                'total_pricing_plans' => $totalPricingPlans,
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
     * Create missing translations for all pricing plans in a language
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

        $pricingPlans = $this->pricingPlanRepository->findActivePlans();
        $created = 0;

        foreach ($pricingPlans as $pricingPlan) {
            // Skip if translation already exists
            if ($pricingPlan->hasTranslation($languageCode)) {
                continue;
            }

            $translation = new PricingPlanTranslation();
            $translation->setPricingPlan($pricingPlan);
            $translation->setLanguage($language);

            // Copy from source language if available
            if ($sourceLanguage) {
                $sourceTranslation = $pricingPlan->getTranslation($sourceLanguage->getCode());
                if ($sourceTranslation) {
                    $translation->setName($sourceTranslation->getName());
                    $translation->setDescription($sourceTranslation->getDescription());
                    $translation->setFeatures($sourceTranslation->getFeatures());
                    $translation->setCtaText($sourceTranslation->getCtaText());
                }
            }

            $this->entityManager->persist($translation);
            $pricingPlan->addTranslation($translation);
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
        $translations = $this->pricingPlanTranslationRepository->findByLanguageCode($languageCode);
        $count = count($translations);

        foreach ($translations as $translation) {
            $this->entityManager->remove($translation);
        }

        $this->entityManager->flush();
        return $count;
    }
}