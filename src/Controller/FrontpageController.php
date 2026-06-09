<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontpageController extends AbstractController
{
    /**
     * Hardcoded placeholder assistants used by the design preview.
     *
     * Drawn from the AI Bibliotek prototype's seed data to give an
     * accurate first-glance impression of the catalogue. Replaced by
     * a real repository query once the persistence layer lands.
     */
    private const SAMPLE_ASSISTANTS = [
        [
            'kommune' => 'Aarhus Kommune',
            'model' => 'gpt-4o',
            'name' => 'Borgerservice-vejviser',
            'summary' => 'Hjælper sagsbehandlere med at finde den rigtige paragraf i lov om social service og opsummere borgerens situation.',
        ],
        [
            'kommune' => 'Københavns Kommune',
            'model' => 'claude-3.5-sonnet',
            'name' => 'Mødereferent',
            'summary' => 'Tager udgangspunkt i et indtalt møde og leverer struktureret referat med beslutninger, ansvar og deadlines.',
        ],
        [
            'kommune' => 'Odense Kommune',
            'model' => 'llama-3.1-70b',
            'name' => 'Journaliseringsassistent',
            'summary' => 'Foreslår journalplan-numre og overskrifter ud fra dokumentets indhold, så fagmedarbejdere kan godkende i et klik.',
        ],
        [
            'kommune' => 'Vejle Kommune',
            'model' => 'gpt-4o-mini',
            'name' => 'Skole- og dagtilbudssvar',
            'summary' => 'Drafter svar til forældrehenvendelser på skole- og dagtilbudsområdet med kildehenvisninger til kommunens egen vejledningssamling.',
        ],
        [
            'kommune' => 'Aalborg Kommune',
            'model' => 'mistral-large',
            'name' => 'Tilsynsrapport-assistent',
            'summary' => 'Læser plejehjemstilsynsrapporter og fremhæver afvigelser, opfølgningspunkter og forbedringer over tid.',
        ],
    ];

    /**
     * Render the placeholder frontpage.
     *
     * Anonymous visitors to `/` receive a design-preview landing page
     * that mirrors the AI Bibliotek prototype. Hero, search prompt,
     * sample-assistant rail, and "Sådan virker det" steps are rendered
     * with hardcoded sample data — the point is to convey what the
     * catalogue will feel like before the persistence and search
     * layers land.
     *
     * @return Response the rendered `frontpage/index.html.twig` template
     */
    #[Route('/', name: 'app_frontpage', methods: ['GET'])]
    public function index(): Response
    {
        $kommuner = array_unique(array_column(self::SAMPLE_ASSISTANTS, 'kommune'));
        $models = array_unique(array_column(self::SAMPLE_ASSISTANTS, 'model'));

        return $this->render('frontpage/index.html.twig', [
            'assistants' => self::SAMPLE_ASSISTANTS,
            'stats' => [
                'assistants' => count(self::SAMPLE_ASSISTANTS),
                'kommuner' => count($kommuner),
                'models' => count($models),
            ],
        ]);
    }
}
