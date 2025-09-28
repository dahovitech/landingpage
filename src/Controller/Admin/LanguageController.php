<?php

namespace App\Controller\Admin;

use App\Entity\Language;
use App\Form\LanguageType;
use App\Repository\LanguageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/languages', name: 'admin_language_')]
class LanguageController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(LanguageRepository $languageRepository): Response
    {
        $languages = $languageRepository->getAllOrderedBySortOrder();

        return $this->render('admin/language/index.html.twig', [
            'languages' => $languages,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $language = new Language();
        $form = $this->createForm(LanguageType::class, $language);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // If this is set as default, unset all others
            if ($language->isDefault()) {
                $this->unsetAllDefaultLanguages($entityManager);
            }

            $entityManager->persist($language);
            $entityManager->flush();

            $this->addFlash('success', 'Langue créée avec succès.');

            return $this->redirectToRoute('admin_language_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/language/new.html.twig', [
            'language' => $language,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Language $language): Response
    {
        return $this->render('admin/language/show.html.twig', [
            'language' => $language,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Language $language, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LanguageType::class, $language);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // If this is set as default, unset all others
            if ($language->isDefault()) {
                $this->unsetAllDefaultLanguages($entityManager);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Langue modifiée avec succès.');

            return $this->redirectToRoute('admin_language_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/language/edit.html.twig', [
            'language' => $language,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Language $language, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$language->getId(), $request->getPayload()->get('_token'))) {
            // Check if this is the default language
            if ($language->isDefault()) {
                $this->addFlash('error', 'Impossible de supprimer la langue par défaut.');
            } else {
                $entityManager->remove($language);
                $entityManager->flush();
                $this->addFlash('success', 'Langue supprimée avec succès.');
            }
        }

        return $this->redirectToRoute('admin_language_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(Language $language, EntityManagerInterface $entityManager): Response
    {
        // Don't allow deactivating the default language
        if ($language->isDefault() && $language->isActive()) {
            $this->addFlash('error', 'Impossible de désactiver la langue par défaut.');
        } else {
            $language->setIsActive(!$language->isActive());
            $entityManager->flush();
            
            $status = $language->isActive() ? 'activée' : 'désactivée';
            $this->addFlash('success', "Langue {$status} avec succès.");
        }

        return $this->redirectToRoute('admin_language_index');
    }

    #[Route('/{id}/set-default', name: 'set_default', methods: ['POST'])]
    public function setDefault(Language $language, EntityManagerInterface $entityManager, LanguageRepository $languageRepository): Response
    {
        if (!$language->isActive()) {
            $this->addFlash('error', 'Impossible de définir comme défaut une langue désactivée.');
        } else {
            $languageRepository->setAsDefault($language);
            $this->addFlash('success', 'Langue définie comme langue par défaut.');
        }

        return $this->redirectToRoute('admin_language_index');
    }

    private function unsetAllDefaultLanguages(EntityManagerInterface $entityManager): void
    {
        $entityManager->createQuery('UPDATE App\Entity\Language l SET l.isDefault = false WHERE l.isDefault = true')
            ->execute();
    }
}
