<?php

namespace App\Controller;

use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class FrontController
 * @package App\Controller
 */
#[Route('/{_locale}', requirements: ['_locale' => '[a-z]{2}'])]
class FrontController extends AbstractController
{
    public function __construct(
        private LanguageRepository $languageRepository
    ) {}

    /**
     * Homepage avec locale
     */
    #[Route('/', name: 'frontend_homepage')]
    public function homepage(Request $request): Response
    {
        $locale = $request->getLocale();
        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        return $this->render('frontend/homepage.html.twig', [
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'locale' => $locale
        ]);
    }

    #[Route('/contact', name: 'frontend_contact')]
    public function contact(Request $request): Response
    {
        $locale = $request->getLocale();
        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        return $this->render('frontend/contact.html.twig', [
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'locale' => $locale
        ]);
    }

    #[Route('/about', name: 'frontend_about')]
    public function about(Request $request): Response
    {
        $locale = $request->getLocale();
        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        return $this->render('frontend/about.html.twig', [
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'locale' => $locale
        ]);
    }
}
