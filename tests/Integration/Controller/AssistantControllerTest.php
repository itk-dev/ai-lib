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

        // Runtime + tags moved into the `meta` / `actions` slots of
        // `<twig:Layout:ContentWithAsides>`, so they are siblings of
        // `<article>` rather than children. Scope to the layout
        // container instead.
        $runtime = $crawler->filter('.layout-content-with-asides dl')->text();
        self::assertStringContainsString('openwebui', $runtime);
        self::assertStringContainsString('gpt-4o', $runtime);

        $tagsText = $crawler->filter('.layout-content-with-asides ul')->text();
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
        self::assertCount(0, $crawler->filter('.layout-content-with-asides ul'), 'tags <ul> must be absent when the list is empty');
    }

    // Verifies that a non-existent assistant id returns a 404 response.
    public function testUnknownAssistantReturns404(): void
    {
        $this->client->request('GET', '/assistant/999999');

        self::assertResponseStatusCodeSame(404);
    }

    // Tests that the detail page offers a download link to the OpenWebUI export route.
    public function testDetailPageLinksToExport(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $assistant = $repository->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant);

        $this->client->request('GET', '/assistant/'.$assistant->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/assistant/'.$assistant->getId().'/export"][download]');
    }

    // Tests that GET /assistant/{id}/export returns a downloadable array-of-one OpenWebUI model reflecting the entity.
    public function testExportReturnsDownloadableArrayOfOneModel(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $assistant = $repository->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant);

        $this->client->request('GET', '/assistant/'.$assistant->getId().'/export');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertStringContainsString(
            'attachment',
            (string) $this->client->getResponse()->headers->get('Content-Disposition'),
        );

        $payload = json_decode((string) $this->client->getResponse()->getContent(), associative: true);
        self::assertIsArray($payload);
        self::assertCount(1, $payload);
        self::assertSame('Borgerservice-vejviser', $payload[0]['name']);
        self::assertSame($assistant->getLanguageModel(), $payload[0]['base_model_id']);
        self::assertSame($assistant->getDescription(), $payload[0]['meta']['description']);
    }

    // Verifies a non-existent assistant id returns 404 for the export route as well.
    public function testExportUnknownAssistantReturns404(): void
    {
        $this->client->request('GET', '/assistant/999999/export');

        self::assertResponseStatusCodeSame(404);
    }

    // Verifies an explicit ?format= for a registered format exports successfully.
    public function testExportAcceptsRegisteredFormatQueryParam(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $assistant = $repository->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant);

        $this->client->request('GET', '/assistant/'.$assistant->getId().'/export?format=openwebui');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
    }

    // Verifies an unknown ?format= returns 404.
    public function testExportUnknownFormatReturns404(): void
    {
        $repository = self::getContainer()->get(AssistantRepository::class);
        $assistant = $repository->findOneBy(['title' => 'Borgerservice-vejviser']);
        self::assertNotNull($assistant);

        $this->client->request('GET', '/assistant/'.$assistant->getId().'/export?format=bogus');

        self::assertResponseStatusCodeSame(404);
    }
}
