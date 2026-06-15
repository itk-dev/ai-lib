<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Assistant;
use App\Tests\Support\ResetsDatabaseSchemaTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FrontpageControllerTest extends WebTestCase
{
    use ResetsDatabaseSchemaTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        self::resetSchema($this->em);
    }

    public function testFrontpageRendersWithoutAssistants(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'AI Bibliotek');
        self::assertSelectorTextContains('h1', 'kommunale');
        // No CardRail entries persisted yet — the rail wrapper still
        // renders, but no card links are emitted.
        self::assertCount(0, $crawler->filter('a[href^="/assistant/"]'));
    }

    public function testCardRailLinksToPersistedAssistants(): void
    {
        $first = new Assistant(
            title: 'Borgerservice-vejviser',
            description: 'Hjælper sagsbehandlere.',
            languageModel: 'gpt-4o',
            framework: 'openwebui',
            tags: ['borgerservice'],
        );
        $second = new Assistant(
            title: 'Mødereferent',
            description: 'Leverer struktureret referat.',
            languageModel: 'claude-3.5-sonnet',
            framework: 'openwebui',
            tags: ['referat'],
        );
        $this->em->persist($first);
        $this->em->persist($second);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $cardLinks = $crawler->filter('a[href^="/assistant/"]');
        self::assertCount(2, $cardLinks);

        $hrefs = $cardLinks->each(static fn ($node) => $node->attr('href'));
        self::assertContains('/assistant/'.$first->getId(), $hrefs);
        self::assertContains('/assistant/'.$second->getId(), $hrefs);

        // Newest first (the controller orders by id DESC).
        self::assertSame('/assistant/'.$second->getId(), $hrefs[0]);

        // Card content surfaces the entity's actual fields.
        $railText = $crawler->filter('[aria-label="Eksempler på assistenter"]')->text();
        self::assertStringContainsString('Borgerservice-vejviser', $railText);
        self::assertStringContainsString('Mødereferent', $railText);
        self::assertStringContainsString('gpt-4o', $railText);
        self::assertStringContainsString('claude-3.5-sonnet', $railText);
    }

    public function testStatsReflectCatalogueCounts(): void
    {
        $this->em->persist(new Assistant('A', 'd', 'gpt-4o', 'openwebui'));
        $this->em->persist(new Assistant('B', 'd', 'gpt-4o', 'openwebui')); // same LM
        $this->em->persist(new Assistant('C', 'd', 'claude-3.5-sonnet', 'openwebui'));
        $this->em->flush();

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        $statsText = $crawler->filter('dl')->text();
        self::assertStringContainsString('3', $statsText, 'Assistanter count = 3');
        self::assertStringContainsString('2', $statsText, 'Sprogmodeller count = 2 (gpt-4o + claude)');
    }
}
