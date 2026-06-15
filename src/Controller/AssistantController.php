<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Assistant;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Routes for the assistant catalogue surface.
 *
 * For now only the detail page exists; the catalogue listing (#15)
 * and the import / export entry points (#22, #23) will hang off the
 * same controller (or sibling controllers under `App\Controller\`)
 * as they land.
 */
final class AssistantController extends AbstractController
{
    /**
     * Render the assistant detail page.
     *
     * Symfony's `MapEntity` param-conversion resolves the `{id}` URL
     * parameter to an `Assistant` row; a missing row 404s before this
     * method runs.
     *
     * @param Assistant $assistant the assistant resolved from the route id
     *
     * @return Response the rendered `assistant/show.html.twig` template
     */
    #[Route(path: '/assistant/{id}', name: 'app_assistant_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Assistant $assistant): Response
    {
        return $this->render('assistant/show.html.twig', [
            'assistant' => $assistant,
        ]);
    }
}
