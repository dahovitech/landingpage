<?php

namespace App\Controller;

use App\Repository\TestimonialRepository;
use App\Repository\LanguageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', name: 'frontend_', requirements: ['_locale' => '[a-z]{2}'])]
class FrontendTestimonialController extends AbstractController
{
    public function __construct(
        private TestimonialRepository $testimonialRepository,
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/testimonials', name: 'testimonials_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $testimonials = $this->testimonialRepository->findActiveTestimonials();
        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        // Filter testimonials that have translations in the current language
        $testimonialsWithTranslations = [];
        foreach ($testimonials as $testimonial) {
            $translation = $testimonial->getTranslationWithFallback($locale);
            if ($translation) {
                $testimonialsWithTranslations[] = [
                    'testimonial' => $testimonial,
                    'translation' => $translation
                ];
            }
        }

        return $this->render('frontend/testimonial/index.html.twig', [
            'testimonials' => $testimonialsWithTranslations,
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'locale' => $locale
        ]);
    }

    #[Route('/api/testimonials', name: 'api_testimonials', methods: ['GET'])]
    public function apiTestimonials(Request $request): Response
    {
        $locale = $request->getLocale();
        $featured = $request->query->getBoolean('featured', false);
        $limit = $request->query->getInt('limit', null);
        
        if ($featured) {
            $testimonials = $this->testimonialRepository->findFeaturedTestimonials($limit);
        } else {
            $testimonials = $this->testimonialRepository->findActiveTestimonials();
            if ($limit) {
                $testimonials = array_slice($testimonials, 0, $limit);
            }
        }
        
        $data = [];
        foreach ($testimonials as $testimonial) {
            $translation = $testimonial->getTranslationWithFallback($locale);
            if ($translation) {
                $data[] = [
                    'id' => $testimonial->getId(),
                    'clientName' => $testimonial->getClientName(),
                    'clientPosition' => $testimonial->getClientPosition(),
                    'clientCompany' => $testimonial->getClientCompany(),
                    'clientFullInfo' => $testimonial->getClientFullInfo(),
                    'content' => $translation->getContent(),
                    'rating' => $testimonial->getRating(),
                    'avatar' => $testimonial->getClientAvatar() ? [
                        'url' => '/upload/media/' . $testimonial->getClientAvatar()->getFileName(),
                        'alt' => $testimonial->getClientName()
                    ] : null,
                    'isFeatured' => $testimonial->isFeatured()
                ];
            }
        }

        return $this->json($data);
    }
}