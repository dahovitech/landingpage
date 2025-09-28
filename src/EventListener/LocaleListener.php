<?php

namespace App\EventListener;

use App\Repository\LanguageRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class LocaleListener
{
    public function __construct(
        private LanguageRepository $languageRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Skip if not main request
        if (!$event->isMainRequest()) {
            return;
        }

        $route = $request->attributes->get('_route');
        $isAdminRoute = $route && str_starts_with($route, 'admin_');
        $isFrontendRoute = $route && str_starts_with($route, 'frontend_');

        if ($isAdminRoute) {
            $this->handleAdminLocale($request);
        } elseif ($isFrontendRoute) {
            $this->handleFrontendLocale($request, $event);
        }
    }

    private function handleAdminLocale($request): void
    {
        $session = $request->getSession();
        
        // Get locale from session first, then from request, then default
        $locale = $session->get('_admin_locale');
        
        if (!$locale) {
            $locale = $request->getLocale();
        }
        
        // If still no locale, get the default language from database
        if (!$locale) {
            $defaultLanguage = $this->languageRepository->findDefaultLanguage();
            
            if ($defaultLanguage) {
                $locale = $defaultLanguage->getCode();
            } else {
                // Fallback to French if no default language is found
                $locale = 'fr';
            }
        }

        // Validate that the current locale exists in our languages
        $activeLanguage = $this->languageRepository->findActiveByCode($locale);
        if (!$activeLanguage) {
            // If current locale is not active, fall back to default
            $defaultLanguage = $this->languageRepository->findDefaultLanguage();
            if ($defaultLanguage) {
                $locale = $defaultLanguage->getCode();
            }
        }
        
        // Set the locale for the request and store in session
        $request->setLocale($locale);
        $session->set('_admin_locale', $locale);
    }

    private function handleFrontendLocale($request, RequestEvent $event): void
    {
        $pathInfo = $request->getPathInfo();
        $session = $request->getSession();
        
        // Extract locale from URL path (e.g., /fr/services or /en/about)
        $urlLocale = $this->extractLocaleFromPath($pathInfo);
        
        if ($urlLocale) {
            // Check if the locale is valid and active
            $language = $this->languageRepository->findActiveByCode($urlLocale);
            if ($language) {
                $request->setLocale($urlLocale);
                $session->set('_locale', $urlLocale);
                
                // Update request attributes for route matching
                $newPathInfo = substr($pathInfo, strlen('/' . $urlLocale));
                if (empty($newPathInfo)) {
                    $newPathInfo = '/';
                }
                $request->server->set('PATH_INFO', $newPathInfo);
                $request->attributes->set('_locale', $urlLocale);
                return;
            }
        }

        // No valid locale in URL, detect and redirect
        $detectedLocale = $this->detectUserLocale($request, $session);
        
        // Only redirect if we're on the homepage or a route without locale
        if ($this->shouldRedirectToLocalizedUrl($pathInfo)) {
            $localizedPath = '/' . $detectedLocale . $pathInfo;
            $response = new RedirectResponse($localizedPath, 302);
            $event->setResponse($response);
            return;
        }
        
        // Set the detected locale for the request
        $request->setLocale($detectedLocale);
        $session->set('_locale', $detectedLocale);
    }

    private function extractLocaleFromPath(string $pathInfo): ?string
    {
        if (preg_match('#^/([a-z]{2})(/.*)?$#', $pathInfo, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function detectUserLocale($request, $session): string
    {
        // First, check session
        $sessionLocale = $session->get('_locale');
        if ($sessionLocale && $this->languageRepository->findActiveByCode($sessionLocale)) {
            return $sessionLocale;
        }

        // Then, check browser preferences
        $acceptLanguages = $request->getLanguages();
        $activeLanguageCodes = $this->languageRepository->findActiveLanguageCodes();
        
        foreach ($acceptLanguages as $browserLocale) {
            // Try exact match first
            if (in_array($browserLocale, $activeLanguageCodes)) {
                return $browserLocale;
            }
            
            // Try language part only (e.g., 'en' from 'en-US')
            $languagePart = substr($browserLocale, 0, 2);
            if (in_array($languagePart, $activeLanguageCodes)) {
                return $languagePart;
            }
        }

        // Fallback to default language
        $defaultLanguage = $this->languageRepository->findDefaultLanguage();
        return $defaultLanguage ? $defaultLanguage->getCode() : 'fr';
    }

    private function shouldRedirectToLocalizedUrl(string $pathInfo): bool
    {
        // Redirect for homepage and main navigation paths
        $pathsToRedirect = [
            '/',
            '/services',
            '/about',
            '/contact',
            '/news',
            '/portfolio'
        ];
        
        return in_array($pathInfo, $pathsToRedirect) || 
               preg_match('#^/[^/]+$#', $pathInfo); // Single level paths
    }
}
