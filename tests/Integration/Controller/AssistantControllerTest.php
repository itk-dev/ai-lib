<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Repository\AssistantRepository;
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
 */
final class AssistantControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    // Tests that GET /assistant/{id} renders the title, description, runtime box, and tag list for a fixture row.
    public function testRendersAssistantDetail(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $assistant = $repository->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant, 'fixture baseline must include the Borgerservice-vejviser entry');

        $crawler = $this->client->request('GET', '/assistant/'.$assistant->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Borgerservice-vejviser');
        self::assertSelectorTextContains('article', 'Hjælper sagsbehandlere');

        $runtime = $crawler->filter('article dl')->text();
        self::assertStringContainsString('openwebui', $runtime);
        self::assertStringContainsString('gpt-4o', $runtime);

        $tagsText = $crawler->filter('article ul')->text();
        self::assertStringContainsString('borgerservice', $tagsText);
        self::assertStringContainsString('social', $tagsText);
        self::assertStringContainsString('jura', $tagsText);
    }

    // Ensures the tags `<ul>` is omitted entirely when the assistant has no tags.
    public function testOmitsTagsSectionWhenAssistantHasNone(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $tagless = $repository->findOneBy(['title' => 'Uden kategorier']);
        self::assertNotNull($tagless, 'fixture baseline must include the tagless edge-case entry');

        $crawler = $this->client->request('GET', '/assistant/'.$tagless->getId());

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('article ul'), 'tags <ul> must be absent when the list is empty');
    }

    // Verifies that a non-existent assistant id returns a 404 response.
    public function testUnknownAssistantReturns404(): void
    {
        $this->client->request('GET', '/assistant/999999');

        self::assertResponseStatusCodeSame(404);
    }
}
