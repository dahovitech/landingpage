<?php

namespace App\Controller\Admin;

use App\Entity\Service;
use App\Entity\ServiceTranslation;
use App\Repository\LanguageRepository;
use App\Repository\MediaRepository;
use App\Repository\ServiceRepository;
use App\Service\ServiceTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/service', name: 'admin_service_')]
//#[IsGranted('ROLE_ADMIN')]
class ServiceController extends AbstractController
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private LanguageRepository $languageRepository,
        private MediaRepository $mediaRepository,
        private ServiceTranslationService $translationService,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $services = $this->translationService->getServicesWithTranslationStatus();
        $languages = $this->languageRepository->findActiveLanguages();
        $statistics = $this->translationService->getGlobalTranslationStatistics();

        return $this->render('admin/service/index.html.twig', [
            'services' => $services,
            'languages' => $languages,
            'statistics' => $statistics
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $service = new Service();
        $languages = $this->languageRepository->findActiveLanguages();
        $defaultLanguage = $this->languageRepository->findDefaultLanguage();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            if (!empty($data['slug'])) {
                $service->setSlug($data['slug']);
            }
            
            $service->setIsActive($data['isActive'] ?? true);
            $service->setSortOrder((int)($data['sortOrder'] ?? 0));

            // Gestion de l'image
            if (!empty($data['imageId'])) {
                $media = $this->mediaRepository->find($data['imageId']);
                if ($media) {
                    $service->setImage($media);
                }
            }

            $translationsData = [];
            foreach ($languages as $language) {
                $langCode = $language->getCode();
                if (!empty($data['translations'][$langCode])) {
                    $translationsData[$langCode] = $data['translations'][$langCode];
                }
            }

            try {
                $this->translationService->createOrUpdateService($service, $translationsData);
                $this->addFlash('success', 'Service créé avec succès.');
                return $this->redirectToRoute('admin_service_edit', ['id' => $service->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du service: ' . $e->getMessage());
            }
        }

        return $this->render('admin/service/new.html.twig', [
            'service' => $service,
            'languages' => $languages,
            'defaultLanguage' => $defaultLanguage
        ]);
    }

    #[Route('/bulk-actions', name: 'bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): Response
    {
        $action = $request->request->get('action');
        $serviceIds = $request->request->all()['service_ids'] ?? [];

        if (empty($serviceIds)) {
            $this->addFlash('error', 'Aucun service sélectionné.');
            return $this->redirectToRoute('admin_service_index');
        }

        $services = $this->serviceRepository->findBy(['id' => $serviceIds]);

        switch ($action) {
            case 'activate':
                foreach ($services as $service) {
                    $service->setIsActive(true);
                }
                $this->entityManager->flush();
                $this->addFlash('success', count($services) . ' service(s) activé(s).');
                break;

            case 'deactivate':
                foreach ($services as $service) {
                    $service->setIsActive(false);
                }
                $this->entityManager->flush();
                $this->addFlash('success', count($services) . ' service(s) désactivé(s).');
                break;

            case 'delete':
                foreach ($services as $service) {
                    $this->entityManager->remove($service);
                }
                $this->entityManager->flush();
                $this->addFlash('success', count($services) . ' service(s) supprimé(s).');
                break;

            default:
                $this->addFlash('error', 'Action inconnue.');
        }

        return $this->redirectToRoute('admin_service_index');
    }

    #[Route('/translation-tools', name: 'translation_tools', methods: ['GET', 'POST'])]
    public function translationTools(Request $request): Response
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $statistics = $this->translationService->getGlobalTranslationStatistics();

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            $targetLanguage = $request->request->get('target_language');
            $sourceLanguage = $request->request->get('source_language');

            try {
                switch ($action) {
                    case 'create_missing':
                        $created = $this->translationService->createMissingTranslations($targetLanguage, $sourceLanguage);
                        $this->addFlash('success', "{$created} traductions créées pour la langue {$targetLanguage}.");
                        break;

                    case 'remove_all':
                        $removed = $this->translationService->removeTranslationsForLanguage($targetLanguage);
                        $this->addFlash('success', "{$removed} traductions supprimées pour la langue {$targetLanguage}.");
                        break;

                    default:
                        $this->addFlash('error', 'Action inconnue.');
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur: ' . $e->getMessage());
            }

            return $this->redirectToRoute('admin_service_translation_tools');
        }

        return $this->render('admin/service/translation_tools.html.twig', [
            'languages' => $languages,
            'statistics' => $statistics
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service): Response
    {
        $languages = $this->languageRepository->findActiveLanguages();
        $defaultLanguage = $this->languageRepository->findDefaultLanguage();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            if (!empty($data['slug'])) {
                $service->setSlug($data['slug']);
            }
            
            $service->setIsActive($data['isActive'] ?? true);
            $service->setSortOrder((int)($data['sortOrder'] ?? 0));

            // Gestion de l'image
            if (isset($data['imageId'])) {
                if (empty($data['imageId'])) {
                    $service->setImage(null);
                } else {
                    $media = $this->mediaRepository->find($data['imageId']);
                    if ($media) {
                        $service->setImage($media);
                    }
                }
            }

            $translationsData = [];
            foreach ($languages as $language) {
                $langCode = $language->getCode();
                if (!empty($data['translations'][$langCode])) {
                    $translationsData[$langCode] = $data['translations'][$langCode];
                }
            }

            try {
                $this->translationService->createOrUpdateService($service, $translationsData);
                $this->addFlash('success', 'Service mis à jour avec succès.');
                return $this->redirectToRoute('admin_service_edit', ['id' => $service->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour du service: ' . $e->getMessage());
            }
        }

        return $this->render('admin/service/edit.html.twig', [
            'service' => $service,
            'languages' => $languages,
            'defaultLanguage' => $defaultLanguage
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Service $service): Response
    {
        $languages = $this->languageRepository->findActiveLanguages();
        
        return $this->render('admin/service/show.html.twig', [
            'service' => $service,
            'languages' => $languages
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Service $service): Response
    {
        if ($this->isCsrfTokenValid('delete' . $service->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($service);
            $this->entityManager->flush();
            $this->addFlash('success', 'Service supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_service_index');
    }

    #[Route('/{id}/duplicate-translation', name: 'duplicate_translation', methods: ['POST'])]
    public function duplicateTranslation(Request $request, Service $service): JsonResponse
    {
        $sourceLanguage = $request->request->get('source_language');
        $targetLanguage = $request->request->get('target_language');

        if (!$sourceLanguage || !$targetLanguage) {
            return new JsonResponse(['success' => false, 'message' => 'Langues manquantes']);
        }

        try {
            $newTranslation = $this->translationService->duplicateTranslation($service, $sourceLanguage, $targetLanguage);
            
            if ($newTranslation) {
                return new JsonResponse(['success' => true, 'message' => 'Traduction dupliquée avec succès']);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Impossible de dupliquer la traduction']);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
