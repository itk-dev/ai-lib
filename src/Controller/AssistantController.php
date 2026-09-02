<?php

declare(strict_types=1);

namespace App\Controller;

use App\Assistant\AssistantExporter;
use App\Assistant\Format\FormatAdapterRegistry;
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
    /**
     * Whitelisted tab ids for the detail page. The `?tab=` query
     * parameter is validated against this list; anything else falls
     * back to {@see self::DEFAULT_TAB} silently.
     */
    private const array DETAIL_TABS = ['beskrivelse', 'viden', 'json'];
    private const string DEFAULT_TAB = 'beskrivelse';

    #[Route(path: '/assistant/{id}', name: 'app_assistant_show', requirements: ['id' => Requirement::ULID], methods: ['GET'])]
    public function show(Assistant $assistant, Request $request, AssistantExporter $exporter, FormatAdapterRegistry $formats): Response
    {
        $tab = (string) $request->query->get('tab', self::DEFAULT_TAB);
        if (!\in_array($tab, self::DETAIL_TABS, true)) {
            $tab = self::DEFAULT_TAB;
        }

        return $this->render('assistant/show.html.twig', [
            'assistant' => $assistant,
            'tab' => $tab,
            'tabs' => self::DETAIL_TABS,
            'exportFormats' => $formats->all(),
            'exportWarnings' => $exporter->warningsByFormat($assistant),
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
        if ([] !== $exported->warnings) {
            $response->headers->set('X-Export-Warning', implode(' ', $exported->warnings));
        }

        return $response;
    }
}
