<?php

namespace App\Controller\Admin;

use App\Repository\LanguageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/language', name: 'admin_language_')]
class LanguageSwitcherController extends AbstractController
{
    public function __construct(
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/switch/{locale}', name: 'switch', methods: ['GET', 'POST'])]
    public function switch(string $locale, Request $request)
    {
        try {
            // Validate that the locale exists and is active
            $language = $this->languageRepository->findActiveByCode($locale);
            if (!$language) {
                if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
                    return new JsonResponse([
                        'success' => false,
                        'error' => 'Invalid or inactive language: ' . $locale
                    ], 400);
                }
                
                $this->addFlash('error', 'Langue invalide ou inactive : ' . $locale);
                return $this->redirectToRoute('admin_dashboard');
            }

            // Store the locale in session
            $session = $request->getSession();
            $session->set('_locale', $locale);
            
            // Also set it for the current request
            $request->setLocale($locale);

            // For AJAX requests, return JSON
            if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
                return new JsonResponse([
                    'success' => true,
                    'locale' => $locale,
                    'language' => $language->getNativeName()
                ]);
            }
            
            // For direct links, redirect to the referrer or dashboard
            $referer = $request->headers->get('referer');
            if ($referer && strpos($referer, $request->getSchemeAndHttpHost()) === 0) {
                return $this->redirect($referer);
            }
            
            return $this->redirectToRoute('admin_dashboard');
            
        } catch (\Exception $e) {
            if ($request->isXmlHttpRequest() || $request->getContentTypeFormat() === 'json') {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Error switching language: ' . $e->getMessage()
                ], 500);
            }
            
            $this->addFlash('error', 'Erreur lors du changement de langue : ' . $e->getMessage());
            return $this->redirectToRoute('admin_dashboard');
        }
    }

    #[Route('/current', name: 'current')]
    public function current(Request $request): JsonResponse
    {
        $locale = $request->getLocale();
        $language = $this->languageRepository->findByCode($locale);

        return new JsonResponse([
            'locale' => $locale,
            'language' => $language ? $language->getNativeName() : $locale
        ]);
    }

    #[Route('/available', name: 'available')]
    public function available(): JsonResponse
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $defaultLanguage = $this->languageRepository->findDefaultLanguage();
        
        $result = [];
        foreach ($languages as $language) {
            $result[] = [
                'code' => $language->getCode(),
                'name' => $language->getNativeName(),
                'default' => $defaultLanguage && $defaultLanguage->getId() === $language->getId()
            ];
        }
        
        return new JsonResponse($result);
    }
}
