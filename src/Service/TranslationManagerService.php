<?php

namespace App\Service;

use App\Repository\LanguageRepository;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpKernel\KernelInterface;

class TranslationManagerService
{
    private array $translationCache = [];

    public function __construct(
        private KernelInterface $kernel,
        private LanguageRepository $languageRepository
    ) {}

    /**
     * Get all translation files grouped by domain and locale
     */
    public function getTranslationFiles(): array
    {
        $translationsDir = $this->kernel->getProjectDir() . '/translations';
        $files = [];
        
        if (!is_dir($translationsDir)) {
            return $files;
        }

        $finder = new Finder();
        $finder->files()->in($translationsDir)->name('*.yaml')->name('*.yml');

        foreach ($finder as $file) {
            $filename = $file->getBasename('.yaml');
            $filename = str_replace('.yml', '', $filename);
            
            // Parse filename: domain.locale (e.g., admin.fr)
            $parts = explode('.', $filename);
            if (count($parts) >= 2) {
                $locale = array_pop($parts);
                $domain = implode('.', $parts);
                
                $files[$domain][$locale] = [
                    'path' => $file->getRealPath(),
                    'filename' => $file->getFilename(),
                    'domain' => $domain,
                    'locale' => $locale,
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime()
                ];
            }
        }

        return $files;
    }

    /**
     * Get translations for a specific domain and locale
     */
    public function getTranslations(string $domain, string $locale): array
    {
        $cacheKey = "{$domain}.{$locale}";
        
        if (isset($this->translationCache[$cacheKey])) {
            return $this->translationCache[$cacheKey];
        }

        $filePath = $this->getTranslationFilePath($domain, $locale);
        
        if (!file_exists($filePath)) {
            return [];
        }

        $content = Yaml::parseFile($filePath);
        $this->translationCache[$cacheKey] = $content ?? [];
        
        return $this->translationCache[$cacheKey];
    }

    /**
     * Save translations for a specific domain and locale
     */
    public function saveTranslations(string $domain, string $locale, array $translations): bool
    {
        $filePath = $this->getTranslationFilePath($domain, $locale);
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        try {
            $yamlContent = Yaml::dump($translations, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            file_put_contents($filePath, $yamlContent);
            
            // Clear cache
            $cacheKey = "{$domain}.{$locale}";
            unset($this->translationCache[$cacheKey]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all available locales from Language entities
     */
    public function getAvailableLocales(): array
    {
        return $this->languageRepository->findActiveLanguageCodes();
    }

    /**
     * Flatten nested array to dot notation
     */
    public function flattenTranslations(array $translations, string $prefix = ''): array
    {
        $flattened = [];
        
        foreach ($translations as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $flattened = array_merge($flattened, $this->flattenTranslations($value, $newKey));
            } else {
                $flattened[$newKey] = $value;
            }
        }
        
        return $flattened;
    }

    /**
     * Unflatten dot notation to nested array
     */
    public function unflattenTranslations(array $flattened): array
    {
        $nested = [];
        
        foreach ($flattened as $key => $value) {
            $keys = explode('.', $key);
            $temp = &$nested;
            
            foreach ($keys as $k) {
                if (!isset($temp[$k])) {
                    $temp[$k] = [];
                }
                $temp = &$temp[$k];
            }
            
            $temp = $value;
        }
        
        return $nested;
    }

    /**
     * Synchronize translation files with available locales
     */
    public function synchronizeWithLanguages(string $domain = 'admin'): void
    {
        $locales = $this->getAvailableLocales();
        $defaultLocale = $this->languageRepository->findDefaultLanguage()?->getCode() ?? 'fr';
        
        // Get base translations from default locale
        $baseTranslations = $this->getTranslations($domain, $defaultLocale);
        
        foreach ($locales as $locale) {
            if ($locale === $defaultLocale) {
                continue;
            }
            
            $existingTranslations = $this->getTranslations($domain, $locale);
            $mergedTranslations = $this->mergeTranslations($baseTranslations, $existingTranslations);
            
            if ($mergedTranslations !== $existingTranslations) {
                $this->saveTranslations($domain, $locale, $mergedTranslations);
            }
        }
    }

    /**
     * Merge base translations with existing ones, keeping existing values
     */
    private function mergeTranslations(array $base, array $existing): array
    {
        foreach ($base as $key => $value) {
            if (is_array($value)) {
                $existing[$key] = $this->mergeTranslations($value, $existing[$key] ?? []);
            } elseif (!isset($existing[$key])) {
                $existing[$key] = $value;
            }
        }
        
        return $existing;
    }

    /**
     * Get translation file path
     */
    private function getTranslationFilePath(string $domain, string $locale): string
    {
        return $this->kernel->getProjectDir() . "/translations/{$domain}.{$locale}.yaml";
    }

    /**
     * Get missing translations count
     */
    public function getMissingTranslationsCount(string $domain, string $locale): int
    {
        $defaultLocale = $this->languageRepository->findDefaultLanguage()?->getCode() ?? 'fr';
        
        if ($locale === $defaultLocale) {
            return 0;
        }
        
        $baseTranslations = $this->flattenTranslations($this->getTranslations($domain, $defaultLocale));
        $translations = $this->flattenTranslations($this->getTranslations($domain, $locale));
        
        $missing = 0;
        foreach ($baseTranslations as $key => $value) {
            if (!isset($translations[$key]) || empty(trim($translations[$key]))) {
                $missing++;
            }
        }
        
        return $missing;
    }

    /**
     * Get translation statistics
     */
    public function getTranslationStats(string $domain): array
    {
        $locales = $this->getAvailableLocales();
        $defaultLocale = $this->languageRepository->findDefaultLanguage()?->getCode() ?? 'fr';
        $baseTranslations = $this->flattenTranslations($this->getTranslations($domain, $defaultLocale));
        $totalKeys = count($baseTranslations);
        
        $stats = [];
        foreach ($locales as $locale) {
            $missing = $this->getMissingTranslationsCount($domain, $locale);
            $completed = $totalKeys - $missing;
            $percentage = $totalKeys > 0 ? round(($completed / $totalKeys) * 100, 1) : 100;
            
            $stats[$locale] = [
                'total' => $totalKeys,
                'completed' => $completed,
                'missing' => $missing,
                'percentage' => $percentage
            ];
        }
        
        return $stats;
    }
}
