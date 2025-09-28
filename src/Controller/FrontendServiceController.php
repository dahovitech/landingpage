<?php

namespace App\Controller;

use App\Repository\LanguageRepository;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', name: 'frontend_', requirements: ['_locale' => '[a-z]{2}'])]
class FrontendServiceController extends AbstractController
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/services', name: 'services_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $services = $this->serviceRepository->findActiveServices();
        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        // Filter services that have translations in the current language
        $servicesWithTranslations = [];
        foreach ($services as $service) {
            $translation = $service->getTranslationWithFallback($locale);
            if ($translation) {
                $servicesWithTranslations[] = [
                    'service' => $service,
                    'translation' => $translation
                ];
            }
        }

        return $this->render('frontend/service/index.html.twig', [
            'services' => $servicesWithTranslations,
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'locale' => $locale
        ]);
    }

    #[Route('/service/{slug}', name: 'service_show', methods: ['GET'])]
    public function show(Request $request, string $slug): Response
    {
        $locale = $request->getLocale();
        $service = $this->serviceRepository->findBySlug($slug);
        
        if (!$service) {
            throw $this->createNotFoundException('Service non trouvé.');
        }

        $translation = $service->getTranslationWithFallback($locale);
        
        if (!$translation) {
            throw $this->createNotFoundException('Traduction non disponible pour ce service.');
        }

        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        // Get available translations for language switcher
        $availableTranslations = [];
        foreach ($languages as $language) {
            $langTranslation = $service->getTranslation($language->getCode());
            if ($langTranslation && $langTranslation->isPartial()) {
                $availableTranslations[] = [
                    'language' => $language,
                    'url' => $this->generateUrl('frontend_service_show', [
                        '_locale' => $language->getCode(),
                        'slug' => $slug
                    ])
                ];
            }
        }

        return $this->render('frontend/service/show.html.twig', [
            'service' => $service,
            'translation' => $translation,
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'availableTranslations' => $availableTranslations,
            'locale' => $locale
        ]);
    }

    #[Route('/api/services', name: 'api_services', methods: ['GET'])]
    public function apiServices(Request $request): Response
    {
        $locale = $request->getLocale();
        $services = $this->serviceRepository->findActiveServices();
        
        $data = [];
        foreach ($services as $service) {
            $translation = $service->getTranslationWithFallback($locale);
            if ($translation) {
                $data[] = [
                    'id' => $service->getId(),
                    'slug' => $service->getSlug(),
                    'title' => $translation->getTitle(),
                    'description' => $translation->getDescription(),
                    'image' => $service->getImage() ? [
                        'url' => '/upload/media/' . $service->getImage()->getFileName(),
                        'alt' => $service->getImage()->getAlt()
                    ] : null,
                    'url' => $this->generateUrl('frontend_service_show', [
                        '_locale' => $locale,
                        'slug' => $service->getSlug()
                    ])
                ];
            }
        }

        return $this->json($data);
    }
}
