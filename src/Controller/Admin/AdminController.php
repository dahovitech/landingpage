<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\LanguageRepository;

#[Route('', name: 'admin_')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'dashboard')]
    public function dashboard(
        LanguageRepository $languageRepository
    ): Response {
        $languages = $languageRepository->getAllOrderedBySortOrder();

        return $this->render('admin/dashboard.html.twig', [
            'languages' => $languages,
            'admin_languages' => $languageRepository->findActiveLanguages(), // Ensure admin_languages is available
        ]);
    }
}
