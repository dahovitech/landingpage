<?php

namespace App\Twig;

use App\Repository\LanguageRepository;
use App\Service\TranslationManagerService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TranslationExtension extends AbstractExtension
{
    public function __construct(
        private LanguageRepository $languageRepository,
        private TranslationManagerService $translationManager,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_admin_languages', [$this, 'getAdminLanguages']),
            new TwigFunction('get_current_admin_language', [$this, 'getCurrentAdminLanguage']),
            new TwigFunction('translation_progress', [$this, 'getTranslationProgress']),
            new TwigFunction('is_translation_complete', [$this, 'isTranslationComplete']),
            // Frontend functions
            new TwigFunction('get_available_languages', [$this, 'getAvailableLanguages']),
            new TwigFunction('get_current_language', [$this, 'getCurrentLanguage']),
            new TwigFunction('language_switcher_urls', [$this, 'getLanguageSwitcherUrls']),
            new TwigFunction('localized_path', [$this, 'getLocalizedPath']),
        ];
    }

    /**
     * Get all active languages for admin interface
     */
    public function getAdminLanguages(): array
    {
        return $this->languageRepository->findActiveLanguages();
    }

    /**
     * Get current admin language based on locale
     */
    public function getCurrentAdminLanguage(string $locale): ?\App\Entity\Language
    {
        return $this->languageRepository->findActiveByCode($locale);
    }

    /**
     * Get translation progress for a domain and locale
     */
    public function getTranslationProgress(string $domain, string $locale): array
    {
        $stats = $this->translationManager->getTranslationStats($domain);
        return $stats[$locale] ?? ['total' => 0, 'completed' => 0, 'missing' => 0, 'percentage' => 0];
    }

    /**
     * Check if translation is complete for a domain and locale
     */
    public function isTranslationComplete(string $domain, string $locale, int $threshold = 100): bool
    {
        $progress = $this->getTranslationProgress($domain, $locale);
        return $progress['percentage'] >= $threshold;
    }

    /**
     * Get all active languages for frontend
     */
    public function getAvailableLanguages(): array
    {
        return $this->languageRepository->findActiveLanguages();
    }

    /**
     * Get current language object
     */
    public function getCurrentLanguage(): ?\App\Entity\Language
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $locale = $request->getLocale();
        return $this->languageRepository->findByCode($locale);
    }

    /**
     * Generate language switcher URLs for the current page
     */
    public function getLanguageSwitcherUrls(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return [];
        }

        $route = $request->get('_route');
        $routeParams = $request->get('_route_params', []);
        $languages = $this->languageRepository->findActiveLanguages();
        $urls = [];

        foreach ($languages as $language) {
            try {
                // Update locale in route parameters
                $params = array_merge($routeParams, ['_locale' => $language->getCode()]);
                
                $url = $this->urlGenerator->generate($route, $params);
                $urls[$language->getCode()] = [
                    'language' => $language,
                    'url' => $url,
                    'is_current' => $language->getCode() === $request->getLocale()
                ];
            } catch (\Exception $e) {
                // Fallback to homepage if route generation fails
                $urls[$language->getCode()] = [
                    'language' => $language,
                    'url' => '/' . $language->getCode(),
                    'is_current' => $language->getCode() === $request->getLocale()
                ];
            }
        }

        return $urls;
    }

    /**
     * Generate a localized path for a specific route
     */
    public function getLocalizedPath(string $route, array $parameters = [], ?string $locale = null): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $currentLocale = $locale ?? ($request ? $request->getLocale() : 'fr');
        
        $params = array_merge($parameters, ['_locale' => $currentLocale]);
        
        try {
            return $this->urlGenerator->generate($route, $params);
        } catch (\Exception $e) {
            return '/' . $currentLocale;
        }
    }
}
