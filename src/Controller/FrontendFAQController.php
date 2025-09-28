<?php

namespace App\Controller;

use App\Repository\FAQRepository;
use App\Repository\LanguageRepository;
use App\Service\FAQTranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', name: 'frontend_', requirements: ['_locale' => '[a-z]{2}'])]
class FrontendFAQController extends AbstractController
{
    public function __construct(
        private FAQRepository $faqRepository,
        private LanguageRepository $languageRepository,
        private FAQTranslationService $faqTranslationService
    ) {}

    #[Route('/faq', name: 'faq_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $category = $request->query->get('category');
        $search = $request->query->get('search');

        if ($search) {
            // Search in FAQ translations
            $faqTranslations = $this->faqTranslationService->searchFAQs($search, $locale);
            $faqsWithTranslations = [];
            foreach ($faqTranslations as $translation) {
                $faqsWithTranslations[] = [
                    'faq' => $translation->getFaq(),
                    'translation' => $translation
                ];
            }
        } else if ($category) {
            // Filter by category
            $faqs = $this->faqRepository->findByCategory($category);
            $faqsWithTranslations = [];
            foreach ($faqs as $faq) {
                $translation = $faq->getTranslationWithFallback($locale);
                if ($translation) {
                    $faqsWithTranslations[] = [
                        'faq' => $faq,
                        'translation' => $translation
                    ];
                }
            }
        } else {
            // Get all FAQs grouped by category
            $faqsGrouped = $this->faqRepository->findGroupedByCategory();
            $faqsWithTranslations = [];
            
            foreach ($faqsGrouped as $categoryName => $faqs) {
                $categoryFaqs = [];
                foreach ($faqs as $faq) {
                    $translation = $faq->getTranslationWithFallback($locale);
                    if ($translation) {
                        $categoryFaqs[] = [
                            'faq' => $faq,
                            'translation' => $translation
                        ];
                    }
                }
                if (!empty($categoryFaqs)) {
                    $faqsWithTranslations[$categoryName] = $categoryFaqs;
                }
            }
        }

        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);
        $categories = $this->faqRepository->findAllCategories();

        return $this->render('frontend/faq/index.html.twig', [
            'faqs' => $faqsWithTranslations,
            'categories' => $categories,
            'currentCategory' => $category,
            'searchQuery' => $search,
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'locale' => $locale
        ]);
    }

    #[Route('/api/faq', name: 'api_faq', methods: ['GET'])]
    public function apiFAQ(Request $request): Response
    {
        $locale = $request->getLocale();
        $category = $request->query->get('category');
        $featured = $request->query->getBoolean('featured', false);
        $limit = $request->query->getInt('limit', null);
        
        if ($featured) {
            $faqs = $this->faqRepository->findFeaturedFAQs($limit);
        } else if ($category) {
            $faqs = $this->faqRepository->findByCategory($category);
        } else {
            $faqs = $this->faqRepository->findActiveFAQs();
        }

        if ($limit && !$featured) {
            $faqs = array_slice($faqs, 0, $limit);
        }
        
        $data = [];
        foreach ($faqs as $faq) {
            $translation = $faq->getTranslationWithFallback($locale);
            if ($translation) {
                $data[] = [
                    'id' => $faq->getId(),
                    'category' => $faq->getCategory(),
                    'question' => $translation->getQuestion(),
                    'answer' => $translation->getAnswer(),
                    'isFeatured' => $faq->isFeatured(),
                    'sortOrder' => $faq->getSortOrder()
                ];
            }
        }

        return $this->json($data);
    }

    #[Route('/api/faq/search', name: 'api_faq_search', methods: ['GET'])]
    public function searchFAQ(Request $request): Response
    {
        $locale = $request->getLocale();
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 3) {
            return $this->json([]);
        }

        $faqTranslations = $this->faqTranslationService->searchFAQs($query, $locale);
        
        $data = [];
        foreach ($faqTranslations as $translation) {
            $faq = $translation->getFaq();
            $data[] = [
                'id' => $faq->getId(),
                'category' => $faq->getCategory(),
                'question' => $translation->getQuestion(),
                'answer' => $translation->getAnswer(),
                'questionExcerpt' => $translation->getQuestionExcerpt(80),
                'answerExcerpt' => $translation->getAnswerExcerpt(120)
            ];
        }

        return $this->json($data);
    }
}