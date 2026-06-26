<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Tag;
use App\Repository\AssistantRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the assistant create form and its AJAX
 * validation endpoint.
 *
 * The AJAX endpoint accepts a `check` identifier and runs only that
 * check, so the client can step a progress bar forward one notch per
 * completed check. The form-submit path goes through
 * `AssistantCreator` which runs the full pipeline.
 */
final class AssistantCreateControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        // The create form is gated behind authentication (any
        // logged-in user; no role required), so log in a baseline
        // fixture user before each test.
        $alice = self::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'alice@example.test']);
        \assert(null !== $alice, 'UserFixtures must seed alice@example.test.');
        $this->client->loginUser($alice);
    }

    // Tests that GET /assistant/new renders the form with every expected input.
    public function testFormRenders(): void
    {
        $this->client->request('GET', '/assistant/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="title"]');
        self::assertSelectorExists('textarea[name="description"]');
        self::assertSelectorExists('input[name="language_model"]');
        self::assertSelectorExists('input[name="framework"]');
        self::assertSelectorExists('input[name="tags"]');
        self::assertSelectorExists('textarea[name="openwebui_config"]');
        self::assertSelectorExists('input[name="_token"]');
        self::assertSelectorExists('input[type="file"]');
    }

    // Verifies the AJAX validation endpoint returns valid=true for the syntax check on parseable JSON.
    public function testValidateConfigEndpointAcceptsValidJsonForSyntaxCheck(): void
    {
        $this->client->request(
            'POST',
            '/assistant/new/validate-config',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['json' => '{"name":"demo"}', 'check' => 'syntax'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), associative: true);
        self::assertIsArray($payload);
        self::assertTrue($payload['valid']);
        self::assertSame([], $payload['errors']);
    }

    // Ensures the AJAX validation endpoint returns valid=false and an error list for the syntax check on malformed JSON.
    public function testValidateConfigEndpointRejectsMalformedJsonForSyntaxCheck(): void
    {
        $this->client->request(
            'POST',
            '/assistant/new/validate-config',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['json' => '{not json', 'check' => 'syntax'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), associative: true);
        self::assertIsArray($payload);
        self::assertFalse($payload['valid']);
        self::assertNotEmpty($payload['errors']);
    }

    // Ensures the AJAX validation endpoint returns 400 for an unknown check identifier.
    public function testValidateConfigEndpointRejectsUnknownCheck(): void
    {
        $this->client->request(
            'POST',
            '/assistant/new/validate-config',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['json' => '{}', 'check' => 'no-such-check'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        $payload = json_decode((string) $this->client->getResponse()->getContent(), associative: true);
        self::assertIsArray($payload);
        self::assertFalse($payload['valid']);
        self::assertNotEmpty($payload['errors']);
    }

    // Tests the happy path: a valid submit creates an Assistant with the parsed config and redirects to its detail page.
    public function testSubmitPersistsAssistantWithConfig(): void
    {
        $crawler = $this->client->request('GET', '/assistant/new');
        $form = $crawler->filter('form')->form();
        $form['title'] = 'Test assistant';
        $form['description'] = 'Test description';
        $form['language_model'] = 'gpt-4o';
        $form['framework'] = 'openwebui';
        $form['tags'] = 'alpha, beta';
        $form['openwebui_config'] = '{"name":"demo","temperature":0.5}';
        $this->client->submit($form);

        self::assertResponseRedirects();
        $location = (string) $this->client->getResponse()->headers->get('Location');
        // Entity ids are ULIDs after the entity-bundle adoption (26-char Crockford base32).
        self::assertMatchesRegularExpression('#^/assistant/[0-9A-HJKMNP-TV-Z]{26}$#', $location);

        $repository = self::getContainer()->get(AssistantRepository::class);
        $created = $repository->findOneBy(['title' => 'Test assistant']);
        self::assertNotNull($created);
        self::assertSame(
            ['alpha', 'beta'],
            array_map(static fn (Tag $t) => $t->getName(), $created->getTags()->toArray()),
        );
        self::assertSame(['name' => 'demo', 'temperature' => 0.5], $created->getOpenwebuiConfig());
    }

    // Ensures a submit with malformed JSON returns 422 and does not persist an Assistant.
    public function testSubmitRejectsInvalidJson(): void
    {
        $crawler = $this->client->request('GET', '/assistant/new');
        $form = $crawler->filter('form')->form();
        $form['title'] = 'Rejected assistant';
        $form['description'] = 'Should not persist.';
        $form['language_model'] = 'gpt-4o';
        $form['framework'] = 'openwebui';
        $form['openwebui_config'] = '{not json';
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);

        $repository = self::getContainer()->get(AssistantRepository::class);
        self::assertNull($repository->findOneBy(['title' => 'Rejected assistant']));
    }

    // Ensures an invalid CSRF token yields 403 and does not persist an Assistant.
    public function testRejectsInvalidCsrfToken(): void
    {
        $this->client->request('POST', '/assistant/new', [
            'title' => 'Hacker assistant',
            'description' => 'Should not persist.',
            'language_model' => 'gpt-4o',
            'framework' => 'openwebui',
            'openwebui_config' => '{"name":"demo"}',
            '_token' => 'nope',
        ]);

        self::assertResponseStatusCodeSame(403);

        $repository = self::getContainer()->get(AssistantRepository::class);
        self::assertNull($repository->findOneBy(['title' => 'Hacker assistant']));
    }
}
