<?php

namespace App\Service;

use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Entity\Language;
use App\Repository\LanguageRepository;
use App\Repository\ServiceRepository;
use App\Repository\ServiceTranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ServiceTranslationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRepository $serviceRepository,
        private ServiceTranslationRepository $serviceTranslationRepository,
        private LanguageRepository $languageRepository,
        private SluggerInterface $slugger
    ) {}

    /**
     * Create or update a service with translations
     */
    public function createOrUpdateService(Service $service, array $translationsData): Service
    {
        // Generate slug if not set
        if (empty($service->getSlug()) && !empty($translationsData)) {
            $defaultLang = $this->languageRepository->findDefaultLanguage();
            $defaultTranslation = $translationsData[$defaultLang->getCode()] ?? reset($translationsData);
            if (!empty($defaultTranslation['title'])) {
                $service->setSlug($this->generateUniqueSlug($defaultTranslation['title']));
            }
        }

        $service->setUpdatedAt();
        $this->entityManager->persist($service);
        
        // For new services, we need to flush first to get an ID before handling translations
        $isNewService = $service->getId() === null;
        if ($isNewService) {
            $this->entityManager->flush();
        }

        // Handle translations
        foreach ($translationsData as $languageCode => $data) {
            $language = $this->languageRepository->findByCode($languageCode);
            if (!$language || !$language->isActive()) {
                continue;
            }

            $translation = null;
            if (!$isNewService) {
                $translation = $this->serviceTranslationRepository->findByServiceAndLanguage($service, $language);
            }
            
            if (!$translation) {
                $translation = new ServiceTranslation();
                $translation->setService($service);
                $translation->setLanguage($language);
                $service->addTranslation($translation);
            }

            $translation->setTitle($data['title'] ?? '');
            $translation->setDescription($data['description'] ?? '');
            $translation->setMetaTitle($data['metaTitle'] ?? null);
            $translation->setMetaDescription($data['metaDescription'] ?? null);
            $translation->setUpdatedAt();

            $this->entityManager->persist($translation);
        }

        $this->entityManager->flush();
        return $service;
    }

    /**
     * Generate unique slug
     */
    public function generateUniqueSlug(string $title): string
    {
        $baseSlug = $this->slugger->slug($title)->lower();
        $slug = $baseSlug;
        $counter = 1;

        while ($this->serviceRepository->findBySlug($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Duplicate translation to another language
     */
    public function duplicateTranslation(Service $service, string $sourceLanguageCode, string $targetLanguageCode): ?ServiceTranslation
    {
        $sourceLanguage = $this->languageRepository->findByCode($sourceLanguageCode);
        $targetLanguage = $this->languageRepository->findByCode($targetLanguageCode);

        if (!$sourceLanguage || !$targetLanguage) {
            return null;
        }

        $sourceTranslation = $service->getTranslation($sourceLanguageCode);
        if (!$sourceTranslation) {
            return null;
        }

        // Check if target translation already exists
        $existingTranslation = $service->getTranslation($targetLanguageCode);
        if ($existingTranslation) {
            return $existingTranslation;
        }

        $newTranslation = $this->serviceTranslationRepository->duplicateTranslation($sourceTranslation, $targetLanguage);
        $service->addTranslation($newTranslation);
        
        $this->entityManager->persist($newTranslation);
        $this->entityManager->flush();

        return $newTranslation;
    }

    /**
     * Get services with translation status for admin
     */
    public function getServicesWithTranslationStatus(): array
    {
        $services = $this->serviceRepository->findActiveServices();
        $languages = $this->languageRepository->findActiveLanguages();
        $result = [];

        foreach ($services as $service) {
            $serviceData = [
                'service' => $service,
                'translations' => [],
                'completionPercentage' => 0
            ];

            $totalFields = 0;
            $completedFields = 0;

            foreach ($languages as $language) {
                $translation = $service->getTranslation($language->getCode());
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

                $serviceData['translations'][$language->getCode()] = $status;
            }

            $serviceData['completionPercentage'] = $totalFields > 0 ? round(($completedFields / $totalFields) * 100) : 0;
            $result[] = $serviceData;
        }

        return $result;
    }

    /**
     * Get global translation statistics
     */
    public function getGlobalTranslationStatistics(): array
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $totalServices = count($this->serviceRepository->findActiveServices());
        $statistics = [];

        foreach ($languages as $language) {
            $stats = $this->serviceTranslationRepository->getTranslationStatistics($language->getCode());
            $missing = $totalServices - $stats['total'];
            
            $statistics[$language->getCode()] = [
                'language' => $language,
                'total_services' => $totalServices,
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
     * Create missing translations for all services in a language
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

        $services = $this->serviceRepository->findActiveServices();
        $created = 0;

        foreach ($services as $service) {
            // Skip if translation already exists
            if ($service->hasTranslation($languageCode)) {
                continue;
            }

            $translation = new ServiceTranslation();
            $translation->setService($service);
            $translation->setLanguage($language);

            // Copy from source language if available
            if ($sourceLanguage) {
                $sourceTranslation = $service->getTranslation($sourceLanguage->getCode());
                if ($sourceTranslation) {
                    $translation->setTitle($sourceTranslation->getTitle());
                    $translation->setDescription($sourceTranslation->getDescription());
                    $translation->setMetaTitle($sourceTranslation->getMetaTitle());
                    $translation->setMetaDescription($sourceTranslation->getMetaDescription());
                }
            }

            $this->entityManager->persist($translation);
            $service->addTranslation($translation);
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
        $translations = $this->serviceTranslationRepository->findByLanguageCode($languageCode);
        $count = count($translations);

        foreach ($translations as $translation) {
            $this->entityManager->remove($translation);
        }

        $this->entityManager->flush();
        return $count;
    }
}
