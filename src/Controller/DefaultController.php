<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DefaultController
 * @package App\Controller
 *
 */
class DefaultController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function homepage(Request $request): Response
    {
        $locale = $request->getLocale();

        return $this->redirectToRoute('frontend_homepage', [
            '_locale' => $locale
        ]);
    }

    #[Route('/localeswitch/{_locale}', name: 'locale_switch', methods: ['GET'])]
    public function locale($_locale, Request $request,EntityManagerInterface $manager): RedirectResponse
    {
        $user=$this->getUser();
        if($user){
            $manager->flush();
        }
        $request->getSession()->set('_locale', $_locale);
        $url = $request->headers->get('referer');
        $locale = $request->getLocale();
        return $this->redirectToRoute('frontend_homepage', [
            '_locale' => $locale
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout(): void
    {
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall');
    }
}
