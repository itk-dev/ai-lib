<?php

declare(strict_types=1);

namespace App\Controller;

use App\Assistant\AssistantExporter;
use App\Entity\Assistant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\String\Slugger\SluggerInterface;

final class AssistantController extends AbstractController
{
    #[Route(path: '/assistant/{id}', name: 'app_assistant_show', requirements: ['id' => Requirement::ULID], methods: ['GET'])]
    public function show(Assistant $assistant): Response
    {
        return $this->render('assistant/show.html.twig', [
            'assistant' => $assistant,
        ]);
    }

    #[Route(path: '/assistant/{id}/export', name: 'app_assistant_export', requirements: ['id' => Requirement::ULID], methods: ['GET'])]
    public function export(Assistant $assistant, Request $request, AssistantExporter $exporter, SluggerInterface $slugger): Response
    {
        $format = $request->query->getString('format') ?: null;

        try {
            $exported = $exporter->export($assistant, $format);
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException();
        }

        $slug = $slugger->slug($assistant->getTitle())->lower()->toString();
        $filename = ('' === $slug ? 'assistant' : $slug).'.'.$exported->extension;

        $response = new Response($exported->payload, Response::HTTP_OK, ['Content-Type' => $exported->mediaType]);
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }
}
