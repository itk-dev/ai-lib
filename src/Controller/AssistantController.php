<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Assistant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class AssistantController extends AbstractController
{
    /**
     * Whitelisted tab ids for the detail page. The `?tab=` query
     * parameter is validated against this list; anything else falls
     * back to {@see self::DEFAULT_TAB} silently.
     */
    private const array DETAIL_TABS = ['beskrivelse', 'modelkort', 'readme', 'json'];
    private const string DEFAULT_TAB = 'beskrivelse';

    #[Route(path: '/assistant/{id}', name: 'app_assistant_show', requirements: ['id' => Requirement::ULID], methods: ['GET'])]
    public function show(Assistant $assistant, Request $request): Response
    {
        $tab = (string) $request->query->get('tab', self::DEFAULT_TAB);
        if (!\in_array($tab, self::DETAIL_TABS, true)) {
            $tab = self::DEFAULT_TAB;
        }

        return $this->render('assistant/show.html.twig', [
            'assistant' => $assistant,
            'tab' => $tab,
            'tabs' => self::DETAIL_TABS,
        ]);
    }

    #[Route(
        path: '/assistant/{id}/export.json',
        name: 'app_assistant_export',
        requirements: ['id' => Requirement::ULID],
        methods: ['GET'],
    )]
    public function export(Assistant $assistant): Response
    {
        $config = $assistant->getOpenwebuiConfig() ?? [];
        $payload = json_encode(
            $config,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );
        $response = new JsonResponse($payload, json: true);
        $filename = \sprintf('assistant-%s.json', $assistant->getId());
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }
}
