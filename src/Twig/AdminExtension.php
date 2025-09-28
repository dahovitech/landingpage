<?php

namespace App\Twig;

use App\Repository\LanguageRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AdminExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private LanguageRepository $languageRepository
    ) {}

    public function getGlobals(): array
    {
        try {
            $adminLanguages = $this->languageRepository->findActiveLanguages();
            return [
                'admin_languages' => $adminLanguages
            ];
        } catch (\Exception $e) {
            // In case of database error or during migration
            return [
                'admin_languages' => []
            ];
        }
    }
}
