<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Assistant;
use App\Tests\Support\ResetsDatabaseSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the assistant detail page.
 *
 * Drives the controller through the real `MapEntity` param converter
 * and Twig render path so the controller + template + translation
 * keys are exercised together.
 */
final class AssistantControllerTest extends WebTestCase
{
    use ResetsDatabaseSchemaTrait;

    private KernelBrowser $client;
    private Assistant $assistant;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        self::resetSchema($em);

        $this->assistant = new Assistant(
            title: 'Borgerservice-vejviser',
            description: 'Hjælper sagsbehandlere med at finde den rigtige paragraf.',
            languageModel: 'gpt-4o',
            framework: 'openwebui',
            tags: ['borgerservice', 'paragraf'],
        );
        $em->persist($this->assistant);
        $em->flush();
    }

    public function testRendersAssistantDetail(): void
    {
        $id = $this->assistant->getId();
        self::assertNotNull($id);

        $crawler = $this->client->request('GET', '/assistant/'.$id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Borgerservice-vejviser');
        self::assertSelectorTextContains('article', 'Hjælper sagsbehandlere');

        $runtime = $crawler->filter('article dl')->text();
        self::assertStringContainsString('openwebui', $runtime);
        self::assertStringContainsString('gpt-4o', $runtime);

        $tagsText = $crawler->filter('article ul')->text();
        self::assertStringContainsString('borgerservice', $tagsText);
        self::assertStringContainsString('paragraf', $tagsText);
    }

    public function testOmitsTagsSectionWhenAssistantHasNone(): void
    {
        $bare = new Assistant('Tagless', 'No tags here.', 'gpt-4o', 'openwebui');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($bare);
        $em->flush();
        $id = $bare->getId();
        self::assertNotNull($id);

        $crawler = $this->client->request('GET', '/assistant/'.$id);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('article ul'), 'tags <ul> must be absent when the list is empty');
    }

    public function testUnknownAssistantReturns404(): void
    {
        $this->client->request('GET', '/assistant/999999');

        self::assertResponseStatusCodeSame(404);
    }
}
