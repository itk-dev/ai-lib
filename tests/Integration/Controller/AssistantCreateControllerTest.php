<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Repository\AssistantRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the assistant create form and its AJAX
 * validation endpoint.
 *
 * Submit-path tests trigger the validator's `exampleDelay()` scaffold
 * (which sleeps 2 s) so each such test adds ~2 s to the suite. That
 * cost is acknowledged in the plan as the price of keeping the
 * scaffold inline; remove the delay (and these long-running tests'
 * note in their docblocks) when a real slow validation lands.
 */
final class AssistantCreateControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
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
        self::assertSelectorExists('input[name="openwebui_config"]');
        self::assertSelectorExists('input[name="_token"]');
        self::assertSelectorExists('input[type="file"]');
    }

    // Verifies the AJAX validation endpoint returns valid=true for parseable JSON.
    public function testValidateConfigEndpointAcceptsValidJson(): void
    {
        $this->client->request(
            'POST',
            '/assistant/new/validate-config',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['json' => '{"name":"demo"}'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), associative: true);
        self::assertIsArray($payload);
        self::assertTrue($payload['valid']);
        self::assertSame([], $payload['errors']);
    }

    // Ensures the AJAX validation endpoint returns valid=false and an error list for malformed JSON.
    public function testValidateConfigEndpointRejectsMalformedJson(): void
    {
        $this->client->request(
            'POST',
            '/assistant/new/validate-config',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['json' => '{not json'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
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
        self::assertMatchesRegularExpression('#^/assistant/\d+$#', $location);

        $repository = self::getContainer()->get(AssistantRepository::class);
        $created = $repository->findOneBy(['title' => 'Test assistant']);
        self::assertNotNull($created);
        self::assertSame(['alpha', 'beta'], $created->getTags());
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
