<?php

namespace App\Controller;

use App\Repository\FeatureRepository;
use App\Repository\LanguageRepository;
use App\Service\FeatureTranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/{_locale}', name: 'frontend_', requirements: ['_locale' => '[a-z]{2}'])]
class FrontendFeatureController extends AbstractController
{
    public function __construct(
        private FeatureRepository $featureRepository,
        private LanguageRepository $languageRepository,
        private FeatureTranslationService $featureTranslationService
    ) {}

    #[Route('/features', name: 'features_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        
        try {
            // Use optimized query with joins
            $features = $this->featureRepository->findWithTranslations();
            $languages = $this->languageRepository->findActiveLanguages();
            $currentLanguage = $this->languageRepository->findByCode($locale);

            if (!$currentLanguage) {
                throw new NotFoundHttpException("Language '{$locale}' not available");
            }

            // Filter features that have translations in the current language
            $featuresWithTranslations = [];
            foreach ($features as $feature) {
                $translation = $feature->getTranslationWithFallback($locale);
                if ($translation && $translation->isPartial()) {
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
                'locale' => $locale,
                'totalFeatures' => count($featuresWithTranslations)
            ]);
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors du chargement des fonctionnalités.');
            return $this->render('frontend/feature/index.html.twig', [
                'features' => [],
                'languages' => [],
                'currentLanguage' => null,
                'locale' => $locale,
                'totalFeatures' => 0
            ]);
        }
    }

    #[Route('/feature/{slug}', name: 'feature_show', methods: ['GET'])]
    public function show(Request $request, string $slug): Response
    {
        $locale = $request->getLocale();
        
        try {
            $feature = $this->featureRepository->findBySlug($slug);
            
            if (!$feature || !$feature->isActive()) {
                throw new NotFoundHttpException('Fonctionnalité non trouvée ou inactive.');
            }

            $translation = $feature->getTranslationWithFallback($locale);
            
            if (!$translation || !$translation->isPartial()) {
                throw new NotFoundHttpException('Traduction non disponible pour cette fonctionnalité.');
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
                'locale' => $locale,
                'metaTitle' => $translation->getMetaTitle() ?: $translation->getTitle(),
                'metaDescription' => $translation->getMetaDescription() ?: substr($translation->getDescription() ?? '', 0, 160)
            ]);
            
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors du chargement de la fonctionnalité.');
            return $this->redirectToRoute('frontend_features_index', ['_locale' => $locale]);
        }
    }

    #[Route('/api/features', name: 'api_features', methods: ['GET'])]
    public function apiFeatures(Request $request): JsonResponse
    {
        $locale = $request->getLocale();
        $featured = $request->query->getBoolean('featured', false);
        $search = $request->query->get('search', '');
        $limit = min((int) $request->query->get('limit', 20), 100); // Max 100 items
        
        try {
            if (!empty($search)) {
                $features = $this->featureTranslationService->searchFeatures($search, $locale, $limit);
            } else if ($featured) {
                $features = $this->featureRepository->findFeaturedFeatures($limit);
            } else {
                $features = $this->featureRepository->findActiveFeatures();
                $features = array_slice($features, 0, $limit);
            }
            
            $data = [];
            foreach ($features as $feature) {
                $translation = $feature->getTranslationWithFallback($locale);
                if ($translation && $translation->isPartial()) {
                    $imageData = null;
                    if ($feature->getImage()) {
                        $imageData = [
                            'url' => $this->getParameter('app.upload_path') . '/' . $feature->getImage()->getFileName(),
                            'alt' => $feature->getImage()->getAlt() ?: $translation->getTitle()
                        ];
                    }

                    $data[] = [
                        'id' => $feature->getId(),
                        'slug' => $feature->getSlug(),
                        'title' => $translation->getTitle(),
                        'description' => $translation->getDescription(),
                        'icon' => $feature->getIcon(),
                        'image' => $imageData,
                        'isFeatured' => $feature->isFeatured(),
                        'sortOrder' => $feature->getSortOrder(),
                        'url' => $this->generateUrl('frontend_feature_show', [
                            '_locale' => $locale,
                            'slug' => $feature->getSlug()
                        ]),
                        'completionPercentage' => $translation->getCompletionPercentage()
                    ];
                }
            }

            return $this->json([
                'data' => $data,
                'total' => count($data),
                'locale' => $locale,
                'featured' => $featured
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Une erreur est survenue lors de la récupération des données',
                'data' => [],
                'total' => 0
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/features/{slug}', name: 'api_feature_detail', methods: ['GET'])]
    public function apiFeatureDetail(Request $request, string $slug): JsonResponse
    {
        $locale = $request->getLocale();
        
        try {
            $feature = $this->featureRepository->findBySlug($slug);
            
            if (!$feature || !$feature->isActive()) {
                return $this->json(['error' => 'Fonctionnalité non trouvée'], Response::HTTP_NOT_FOUND);
            }

            $translation = $feature->getTranslationWithFallback($locale);
            
            if (!$translation) {
                return $this->json(['error' => 'Traduction non disponible'], Response::HTTP_NOT_FOUND);
            }

            $imageData = null;
            if ($feature->getImage()) {
                $imageData = [
                    'url' => $this->getParameter('app.upload_path') . '/' . $feature->getImage()->getFileName(),
                    'alt' => $feature->getImage()->getAlt() ?: $translation->getTitle()
                ];
            }

            return $this->json([
                'id' => $feature->getId(),
                'slug' => $feature->getSlug(),
                'title' => $translation->getTitle(),
                'description' => $translation->getDescription(),
                'metaTitle' => $translation->getMetaTitle(),
                'metaDescription' => $translation->getMetaDescription(),
                'icon' => $feature->getIcon(),
                'image' => $imageData,
                'isFeatured' => $feature->isFeatured(),
                'isActive' => $feature->isActive(),
                'sortOrder' => $feature->getSortOrder(),
                'completionPercentage' => $translation->getCompletionPercentage(),
                'createdAt' => $feature->getCreatedAt()->format('c'),
                'updatedAt' => $feature->getUpdatedAt()->format('c')
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Une erreur est survenue lors de la récupération des données'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}