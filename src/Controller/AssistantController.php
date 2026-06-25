<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Assistant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class AssistantController extends AbstractController
{
    #[Route(path: '/assistant/{id}', name: 'app_assistant_show', requirements: ['id' => Requirement::ULID], methods: ['GET'])]
    public function show(Assistant $assistant): Response
    {
        return $this->render('assistant/show.html.twig', [
            'assistant' => $assistant,
        ]);
    }
}
