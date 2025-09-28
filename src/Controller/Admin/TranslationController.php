<?php

namespace App\Controller\Admin;

use App\Repository\LanguageRepository;
use App\Service\TranslationManagerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/translations', name: 'admin_translation_')]
class TranslationController extends AbstractController
{
    public function __construct(
        private TranslationManagerService $translationManager,
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $translationFiles = $this->translationManager->getTranslationFiles();
        $availableLocales = $this->translationManager->getAvailableLocales();
        $languages = $this->languageRepository->findActiveLanguages();
        
        // Get statistics for each domain
        $stats = [];
        foreach ($translationFiles as $domain => $locales) {
            $stats[$domain] = $this->translationManager->getTranslationStats($domain);
        }

        return $this->render('admin/translation/index.html.twig', [
            'translationFiles' => $translationFiles,
            'availableLocales' => $availableLocales,
            'languages' => $languages,
            'stats' => $stats
        ]);
    }

    #[Route('/edit/{domain}/{locale}', name: 'edit')]
    public function edit(string $domain, string $locale): Response
    {
        $language = $this->languageRepository->findByCode($locale);
        if (!$language) {
            throw $this->createNotFoundException('Language not found');
        }

        $translations = $this->translationManager->getTranslations($domain, $locale);
        $flattenedTranslations = $this->translationManager->flattenTranslations($translations);
        
        // Get default language translations for reference
        $defaultLanguage = $this->languageRepository->findDefaultLanguage();
        $defaultTranslations = [];
        if ($defaultLanguage && $defaultLanguage->getCode() !== $locale) {
            $defaultTranslations = $this->translationManager->flattenTranslations(
                $this->translationManager->getTranslations($domain, $defaultLanguage->getCode())
            );
        }

        return $this->render('admin/translation/edit.html.twig', [
            'domain' => $domain,
            'locale' => $locale,
            'language' => $language,
            'translations' => $flattenedTranslations,
            'defaultTranslations' => $defaultTranslations,
            'isDefaultLanguage' => $defaultLanguage && $defaultLanguage->getCode() === $locale
        ]);
    }

    #[Route('/update/{domain}/{locale}', name: 'update', methods: ['POST'])]
    public function update(string $domain, string $locale, Request $request): JsonResponse
    {
        $language = $this->languageRepository->findByCode($locale);
        if (!$language) {
            return new JsonResponse(['error' => 'Language not found'], 404);
        }

        $flattenedTranslations = $request->request->all('translations');
        if (!$flattenedTranslations) {
            return new JsonResponse(['error' => 'No translations provided'], 400);
        }

        // Clean empty values
        $flattenedTranslations = array_filter($flattenedTranslations, function($value) {
            return $value !== null && $value !== '';
        });

        $nestedTranslations = $this->translationManager->unflattenTranslations($flattenedTranslations);
        
        $success = $this->translationManager->saveTranslations($domain, $locale, $nestedTranslations);
        
        if ($success) {
            $this->addFlash('success', 'admin.translations.messages.updated');
            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['error' => 'Failed to save translations'], 500);
    }

    #[Route('/synchronize/{domain}', name: 'synchronize', methods: ['POST'])]
    public function synchronize(string $domain): JsonResponse
    {
        try {
            $this->translationManager->synchronizeWithLanguages($domain);
            $this->addFlash('success', 'Translations synchronized successfully');
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to synchronize translations: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/stats/{domain}', name: 'stats')]
    public function stats(string $domain): JsonResponse
    {
        $stats = $this->translationManager->getTranslationStats($domain);
        return new JsonResponse($stats);
    }

    #[Route('/export/{domain}/{locale}', name: 'export')]
    public function export(string $domain, string $locale): Response
    {
        $language = $this->languageRepository->findByCode($locale);
        if (!$language) {
            throw $this->createNotFoundException('Language not found');
        }

        $translations = $this->translationManager->getTranslations($domain, $locale);
        $yaml = \Symfony\Component\Yaml\Yaml::dump($translations, 6, 2, \Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        $response = new Response($yaml);
        $response->headers->set('Content-Type', 'application/x-yaml');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$domain}.{$locale}.yaml\"");

        return $response;
    }
}
