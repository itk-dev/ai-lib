<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Repository\AssistantRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the assistant detail page.
 *
 * Drives the controller through the real `MapEntity` param converter
 * and Twig render path so the controller + template + translation
 * keys are exercised together. Uses the baseline catalogue loaded by
 * `tests/bootstrap_integration.php` (see `AssistantFixtures`); each
 * test's mutations are rolled back by DAMA at tearDown.
 *
 * The detail page renders four tabs (Beskrivelse / Modelkort /
 * Readme / JSON) driven by the `?tab=` query parameter. The tests
 * below walk each one so the tab-partial include paths are
 * covered.
 */
final class AssistantControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // The detail page is gated, so log in a baseline fixture user
        // before each test so the assertions below see actual content
        // rather than an unauthorised response.
        $alice = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'alice@example.test']);
        \assert(null !== $alice, 'UserFixtures must seed alice@example.test.');
        $this->client->loginUser($alice);
    }

    // Tests that GET /assistant/{id} renders the title, description flow, meta aside, and tab bar.
    public function testRendersDefaultTab(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $assistant = $repository->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant, 'fixture baseline must include the Borgerservice-vejviser entry');

        $crawler = $this->client->request('GET', '/assistant/'.$assistant->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Borgerservice-vejviser');

        // Runtime + tags moved into the `meta` / `actions` slots of
        // `<twig:Layout:ContentWithAsides>`, so they are siblings of
        // `<article>` rather than children. Scope to the layout
        // container instead.
        $runtime = $crawler->filter('.layout-content-with-asides dl')->text();
        self::assertStringContainsString('openwebui', $runtime);
        self::assertStringContainsString('Mistral 24b', $runtime);

        // Default tab (beskrivelse) shows the description + tag chips.
        $article = $crawler->filter('article')->text();
        self::assertStringContainsString('Hjælper sagsbehandlere', $article);
        self::assertStringContainsString('borgerservice', $article);
        self::assertStringContainsString('social', $article);

        // Meta aside carries the real values we do have on the entity.
        $meta = $crawler->filter('.layout-content-with-asides dl')->text();
        self::assertStringContainsString('gpt-4o', $meta);

        // Tabs render as anchors with ?tab= query strings and mark the current one.
        self::assertSelectorExists('nav[aria-label="Assistentdetaljer"] a[aria-current="page"]');
        self::assertSelectorTextContains('nav[aria-label="Assistentdetaljer"] a[aria-current="page"]', 'Beskrivelse');
    }

    // Verifies each whitelisted ?tab= value renders the matching partial heading.
    public function testEachTabRendersItsPartial(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $assistant = $repository->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant);
        $base = '/assistant/'.$assistant->getId();

        $cases = [
            'modelkort' => ['heading' => 'Modelkort', 'tabLabel' => 'Modelkort'],
            'readme' => ['heading' => 'Readme', 'tabLabel' => 'Readme'],
            'json' => ['heading' => 'Eksportér konfiguration', 'tabLabel' => 'JSON'],
        ];

        foreach ($cases as $tab => $expected) {
            $this->client->request('GET', $base.'?tab='.$tab);

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('article h2', $expected['heading'], "tab={$tab} must render its own H2 heading");
            self::assertSelectorTextContains('nav[aria-label="Assistentdetaljer"] a[aria-current="page"]', $expected['tabLabel']);
        }
    }

    // Ensures an unknown ?tab= value falls back to the default (beskrivelse) tab silently, no 4xx.
    public function testUnknownTabFallsBackToDefault(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $assistant = $repository->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant);

        $crawler = $this->client->request('GET', '/assistant/'.$assistant->getId().'?tab=no-such-tab');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('nav[aria-label="Assistentdetaljer"] a[aria-current="page"]', 'Beskrivelse');
    }

    // Ensures the tags <ul> is omitted entirely when the assistant has no tags.
    public function testOmitsTagsSectionWhenAssistantHasNone(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $tagless = $repository->findOneBy(['title' => 'Uden kategorier']);
        self::assertNotNull($tagless, 'fixture baseline must include the tagless edge-case entry');

        $crawler = $this->client->request('GET', '/assistant/'.$tagless->getId());

        self::assertResponseIsSuccessful();
        // The tab bar is a <nav>, so `article ul` catches only the
        // content <ul> — tags-heading + list are omitted when empty.
        self::assertCount(0, $crawler->filter('article ul'), 'tags <ul> must be absent when the list is empty');
    }

    // Verifies GET /assistant/{id}/export.json returns the OpenWebUI config as a JSON download.
    public function testExportReturnsJsonDownload(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $assistant = $repository->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant);

        $this->client->request('GET', '/assistant/'.$assistant->getId().'/export.json');

        $response = $this->client->getResponse();
        self::assertResponseIsSuccessful();
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('assistant-'.$assistant->getId().'.json', (string) $response->headers->get('Content-Disposition'));

        $payload = json_decode((string) $response->getContent(), associative: true);
        self::assertIsArray($payload);
    }

    // Ensures the export route returns an empty JSON object when the assistant has no stored config.
    public function testExportReturnsEmptyObjectForConfiglessAssistant(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $tagless = $repository->findOneBy(['title' => 'Uden kategorier']);
        self::assertNotNull($tagless);

        $this->client->request('GET', '/assistant/'.$tagless->getId().'/export.json');

        self::assertResponseIsSuccessful();
        $body = (string) $this->client->getResponse()->getContent();
        self::assertContains(trim($body), ['[]', '{}'], 'empty config must serialise to an empty JSON literal');
    }

    // Verifies that a non-existent assistant id returns a 404 response.
    public function testUnknownAssistantReturns404(): void
    {
        $this->client->request('GET', '/assistant/999999');

        self::assertResponseStatusCodeSame(404);
    }
}
