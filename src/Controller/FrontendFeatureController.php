<?php

namespace App\Controller;

use App\Repository\FeatureRepository;
use App\Repository\LanguageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', name: 'frontend_', requirements: ['_locale' => '[a-z]{2}'])]
class FrontendFeatureController extends AbstractController
{
    public function __construct(
        private FeatureRepository $featureRepository,
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/features', name: 'features_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $features = $this->featureRepository->findActiveFeatures();
        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        // Filter features that have translations in the current language
        $featuresWithTranslations = [];
        foreach ($features as $feature) {
            $translation = $feature->getTranslationWithFallback($locale);
            if ($translation) {
                $featuresWithTranslations[] = [
                    'feature' => $feature,
                    'translation' => $translation
                ];
            }
        }

        return $this->render('frontend/feature/index.html.twig', [
            'features' => $featuresWithTranslations,
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'locale' => $locale
        ]);
    }

    #[Route('/feature/{slug}', name: 'feature_show', methods: ['GET'])]
    public function show(Request $request, string $slug): Response
    {
        $locale = $request->getLocale();
        $feature = $this->featureRepository->findBySlug($slug);
        
        if (!$feature) {
            throw $this->createNotFoundException('Feature non trouvée.');
        }

        $translation = $feature->getTranslationWithFallback($locale);
        
        if (!$translation) {
            throw $this->createNotFoundException('Traduction non disponible pour cette feature.');
        }

        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        // Get available translations for language switcher
        $availableTranslations = [];
        foreach ($languages as $language) {
            $langTranslation = $feature->getTranslation($language->getCode());
            if ($langTranslation && $langTranslation->isPartial()) {
                $availableTranslations[] = [
                    'language' => $language,
                    'url' => $this->generateUrl('frontend_feature_show', [
                        '_locale' => $language->getCode(),
                        'slug' => $slug
                    ])
                ];
            }
        }

        return $this->render('frontend/feature/show.html.twig', [
            'feature' => $feature,
            'translation' => $translation,
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'availableTranslations' => $availableTranslations,
            'locale' => $locale
        ]);
    }

    #[Route('/api/features', name: 'api_features', methods: ['GET'])]
    public function apiFeatures(Request $request): Response
    {
        $locale = $request->getLocale();
        $featured = $request->query->getBoolean('featured', false);
        
        if ($featured) {
            $features = $this->featureRepository->findFeaturedFeatures();
        } else {
            $features = $this->featureRepository->findActiveFeatures();
        }
        
        $data = [];
        foreach ($features as $feature) {
            $translation = $feature->getTranslationWithFallback($locale);
            if ($translation) {
                $data[] = [
                    'id' => $feature->getId(),
                    'slug' => $feature->getSlug(),
                    'title' => $translation->getTitle(),
                    'description' => $translation->getDescription(),
                    'icon' => $feature->getIcon(),
                    'image' => $feature->getImage() ? [
                        'url' => '/upload/media/' . $feature->getImage()->getFileName(),
                        'alt' => $feature->getImage()->getAlt()
                    ] : null,
                    'isFeatured' => $feature->isFeatured(),
                    'url' => $this->generateUrl('frontend_feature_show', [
                        '_locale' => $locale,
                        'slug' => $feature->getSlug()
                    ])
                ];
            }
        }

        return $this->json($data);
    }
}