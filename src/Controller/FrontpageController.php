<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontpageController extends AbstractController
{
    /**
     * Render the placeholder frontpage.
     *
     * Anonymous visitors to `/` receive a thin landing page that
     * identifies the project (ai-lib) and signals that the
     * application is under construction. The page exists so that
     * other UI work (auth, catalogue, search) has a stable entry
     * point to link back to while the richer frontpage envisioned
     * in #3 is being designed.
     *
     * @return Response the rendered `frontpage/index.html.twig` template
     */
    #[Route('/', name: 'app_frontpage', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('frontpage/index.html.twig');
    }
}
