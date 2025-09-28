<?php

namespace App\Controller;

use App\Repository\PricingPlanRepository;
use App\Repository\LanguageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', name: 'frontend_', requirements: ['_locale' => '[a-z]{2}'])]
class FrontendPricingController extends AbstractController
{
    public function __construct(
        private PricingPlanRepository $pricingPlanRepository,
        private LanguageRepository $languageRepository
    ) {}

    #[Route('/pricing', name: 'pricing_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $billingPeriod = $request->query->get('period', 'monthly');
        
        $pricingPlans = $this->pricingPlanRepository->findByBillingPeriod($billingPeriod);
        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        // Filter pricing plans that have translations in the current language
        $pricingPlansWithTranslations = [];
        foreach ($pricingPlans as $pricingPlan) {
            $translation = $pricingPlan->getTranslationWithFallback($locale);
            if ($translation) {
                $pricingPlansWithTranslations[] = [
                    'pricingPlan' => $pricingPlan,
                    'translation' => $translation
                ];
            }
        }

        // Get price range for display
        $priceRange = $this->pricingPlanRepository->getPriceRange();

        return $this->render('frontend/pricing/index.html.twig', [
            'pricingPlans' => $pricingPlansWithTranslations,
            'billingPeriod' => $billingPeriod,
            'priceRange' => $priceRange,
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'locale' => $locale
        ]);
    }

    #[Route('/pricing/{slug}', name: 'pricing_show', methods: ['GET'])]
    public function show(Request $request, string $slug): Response
    {
        $locale = $request->getLocale();
        $pricingPlan = $this->pricingPlanRepository->findBySlug($slug);
        
        if (!$pricingPlan) {
            throw $this->createNotFoundException('Plan tarifaire non trouvé.');
        }

        $translation = $pricingPlan->getTranslationWithFallback($locale);
        
        if (!$translation) {
            throw $this->createNotFoundException('Traduction non disponible pour ce plan.');
        }

        $languages = $this->languageRepository->findActiveLanguages();
        $currentLanguage = $this->languageRepository->findByCode($locale);

        // Get other plans for comparison
        $otherPlans = $this->pricingPlanRepository->findByBillingPeriod($pricingPlan->getBillingPeriod());
        $otherPlansWithTranslations = [];
        foreach ($otherPlans as $plan) {
            if ($plan->getId() === $pricingPlan->getId()) {
                continue;
            }
            $planTranslation = $plan->getTranslationWithFallback($locale);
            if ($planTranslation) {
                $otherPlansWithTranslations[] = [
                    'pricingPlan' => $plan,
                    'translation' => $planTranslation
                ];
            }
        }

        return $this->render('frontend/pricing/show.html.twig', [
            'pricingPlan' => $pricingPlan,
            'translation' => $translation,
            'otherPlans' => $otherPlansWithTranslations,
            'languages' => $languages,
            'currentLanguage' => $currentLanguage,
            'locale' => $locale
        ]);
    }

    #[Route('/api/pricing', name: 'api_pricing', methods: ['GET'])]
    public function apiPricing(Request $request): Response
    {
        $locale = $request->getLocale();
        $billingPeriod = $request->query->get('period', 'monthly');
        $popular = $request->query->getBoolean('popular', false);
        
        if ($popular) {
            $pricingPlans = $this->pricingPlanRepository->findPopularPlans();
        } else {
            $pricingPlans = $this->pricingPlanRepository->findByBillingPeriod($billingPeriod);
        }
        
        $data = [];
        foreach ($pricingPlans as $pricingPlan) {
            $translation = $pricingPlan->getTranslationWithFallback($locale);
            if ($translation) {
                $data[] = [
                    'id' => $pricingPlan->getId(),
                    'slug' => $pricingPlan->getSlug(),
                    'name' => $translation->getName(),
                    'description' => $translation->getDescription(),
                    'features' => $translation->getFeaturesAsList(),
                    'ctaText' => $translation->getCtaText(),
                    'price' => $pricingPlan->getPrice(),
                    'formattedPrice' => $pricingPlan->getFormattedPrice(),
                    'monthlyPrice' => $pricingPlan->getMonthlyPrice(),
                    'billingPeriod' => $pricingPlan->getBillingPeriod(),
                    'currency' => $pricingPlan->getCurrency(),
                    'isPopular' => $pricingPlan->isPopular(),
                    'isFree' => $pricingPlan->isFree(),
                    'maxUsers' => $pricingPlan->getMaxUsers(),
                    'maxProjects' => $pricingPlan->getMaxProjects(),
                    'storageLimit' => $pricingPlan->getStorageLimit(),
                    'url' => $this->generateUrl('frontend_pricing_show', [
                        '_locale' => $locale,
                        'slug' => $pricingPlan->getSlug()
                    ])
                ];
            }
        }

        return $this->json($data);
    }
}