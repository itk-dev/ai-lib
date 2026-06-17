<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Assistant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seed twenty-one assistants for local development.
 *
 * Six are hand-written catalogue entries — five are authentic rows
 * drawn from the AI Bibliotek prototype, and the sixth is a tagless
 * row used to cover the detail page's empty-tags branch in tests.
 * The remaining fifteen are generated deterministically from a fixed
 * set of topics, kommuner and language models — same input on every
 * run, no randomness — so test assertions and design previews stay
 * reproducible.
 */
final class AssistantFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $this->loadDetailed($manager);
        $this->loadGenerated($manager);
        $manager->flush();
    }

    private function loadDetailed(ObjectManager $manager): void
    {
        $entries = [
            new Assistant(
                title: 'Borgerservice-vejviser',
                description: 'Hjælper sagsbehandlere i borgerservice med at finde den rigtige paragraf i lov om social service og lov om aktiv socialpolitik. Tager udgangspunkt i en kort beskrivelse af borgerens situation og foreslår relevante lovhjemler, sagskategorier og næste skridt. Indeholder kommunens egne vejledninger og praksisnotater som baggrundsviden. Delt af Aarhus Kommune.',
                languageModel: 'gpt-4o',
                framework: 'openwebui',
                tags: ['borgerservice', 'social', 'jura'],
            ),
            new Assistant(
                title: 'Mødereferent',
                description: 'Tager udgangspunkt i et indtalt eller transskriberet mødeoptag og leverer et struktureret referat med beslutninger, ansvarsfordeling og deadlines. Identificerer automatisk handlepunkter og foreslår opfølgningstidspunkter. Bruges på direktionsmøder, projektmøder og udvalgsmøder. Delt af Københavns Kommune.',
                languageModel: 'claude-3.5-sonnet',
                framework: 'openwebui',
                tags: ['mødeledelse', 'dokumentation', 'produktivitet'],
            ),
            new Assistant(
                title: 'Journaliseringsassistent',
                description: 'Foreslår journalplan-numre og overskrifter ud fra dokumentets indhold, så fagmedarbejdere kan godkende i ét klik. Tager højde for kommunens egen klassifikationsstruktur og henter forslag fra historiske, lignende sager. Reducerer den tid medarbejdere bruger på korrekt arkivering markant. Delt af Odense Kommune.',
                languageModel: 'llama-3.1-70b',
                framework: 'openwebui',
                tags: ['dokumentation', 'journalisering', 'arkiv'],
            ),
            new Assistant(
                title: 'Skole- og dagtilbudssvar',
                description: 'Drafter svar til forældrehenvendelser på skole- og dagtilbudsområdet. Bygger svaret på kommunens egen vejledningssamling, gældende lovgivning på området og det specifikke dagtilbuds praksis. Vedhæfter kildehenvisninger så medarbejderen kan tjekke baggrunden inden afsendelse. Delt af Vejle Kommune.',
                languageModel: 'gpt-4o-mini',
                framework: 'openwebui',
                tags: ['skole', 'dagtilbud', 'kommunikation'],
            ),
            new Assistant(
                title: 'Tilsynsrapport-assistent',
                description: 'Læser plejehjemstilsynsrapporter og fremhæver afvigelser, opfølgningspunkter og udvikling over tid. Sammenligner det enkelte plejehjems resultater med kommune- og landsgennemsnit og foreslår fokusområder til det næste tilsyn. Bygger på Styrelsen for Patientsikkerheds tilsynsdata. Delt af Aalborg Kommune.',
                languageModel: 'mistral-large',
                framework: 'openwebui',
                tags: ['sundhed', 'tilsyn', 'plejehjem'],
            ),
            new Assistant(
                title: 'Uden kategorier',
                description: 'Pladsholder uden tags — bruges til at vise hvordan detaljevisningen håndterer en helt umarkeret post.',
                languageModel: 'gpt-4o',
                framework: 'openwebui',
            ),
        ];

        foreach ($entries as $assistant) {
            $manager->persist($assistant);
        }
    }

    private function loadGenerated(ObjectManager $manager): void
    {
        $topics = [
            [
                'title' => 'HR-håndbog assistent',
                'description' => 'Slår op i kommunens personalehåndbog og besvarer spørgsmål om ferie, sygdomsregler og overenskomster med citater fra kilden.',
                'tags' => ['hr', 'personale'],
            ],
            [
                'title' => 'Indkøbsguide',
                'description' => 'Hjælper indkøbsansvarlige med at finde gældende rammeaftaler, foreslå relevante leverandører og generere udkast til rekvisitioner.',
                'tags' => ['indkøb', 'udbud'],
            ],
            [
                'title' => 'Politisk dagsorden-resumé',
                'description' => 'Læser udvalgs- og byrådsdagsordener og leverer letlæste resuméer med beslutningspunkter, høringssvar og baggrundsmateriale.',
                'tags' => ['politik', 'dagsorden'],
            ],
            [
                'title' => 'Forvaltningsret-vejviser',
                'description' => 'Vejleder sagsbehandlere i forvaltningsrettens grundprincipper med praksisnotater og henvisninger til relevante lovparagraffer.',
                'tags' => ['jura', 'sagsbehandling'],
            ],
            [
                'title' => 'Sundhedsfaglig sparring',
                'description' => 'Faglig sparringspartner for hjemmeplejen — kvalitetssikrer plejeplaner og foreslår dokumentationsforbedringer ud fra Sundhedsstyrelsens retningslinjer.',
                'tags' => ['sundhed', 'hjemmepleje'],
            ],
            [
                'title' => 'Borgerhenvendelse-svarudkast',
                'description' => 'Drafter udkast til svar på borgermails ud fra kommunens egne vejledninger og gældende lovgivning, så medarbejderen kan rette til og godkende.',
                'tags' => ['borgerservice', 'kommunikation'],
            ],
            [
                'title' => 'Statistikfortolker',
                'description' => 'Læser kommunens KPI-rapporter og foreslår tekstuelle forklaringer på udsving samt sammenligninger med foregående perioder og kommunegennemsnit.',
                'tags' => ['statistik', 'rapportering'],
            ],
        ];

        $kommunes = [
            'Aarhus Kommune',
            'Københavns Kommune',
            'Odense Kommune',
            'Vejle Kommune',
            'Aalborg Kommune',
            'Esbjerg Kommune',
            'Frederiksberg Kommune',
            'Randers Kommune',
            'Kolding Kommune',
            'Horsens Kommune',
        ];

        $languageModels = [
            'gpt-4o',
            'gpt-4o-mini',
            'claude-3.5-sonnet',
            'llama-3.1-70b',
            'mistral-large',
        ];

        $topicCount = count($topics);
        $kommuneCount = count($kommunes);
        $modelCount = count($languageModels);

        for ($i = 0; $i < 15; ++$i) {
            $topic = $topics[$i % $topicCount];
            $kommune = $kommunes[$i % $kommuneCount];
            $languageModel = $languageModels[$i % $modelCount];

            $manager->persist(new Assistant(
                title: $topic['title'].' – '.$kommune,
                description: $topic['description'].' Delt af '.$kommune.'.',
                languageModel: $languageModel,
                framework: 'openwebui',
                tags: $topic['tags'],
            ));
        }
    }
}
