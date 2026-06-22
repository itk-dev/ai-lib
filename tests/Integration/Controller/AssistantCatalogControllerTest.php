<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Repository\AssistantRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * End-to-end coverage of the assistant catalogue page.
 *
 * Drives `GET /search` through the real controller + repository +
 * Twig render path. Uses the baseline catalogue loaded by
 * `tests/bootstrap_integration.php` (see `AssistantFixtures`,
 * 21 entries). DAMA rolls back per-test mutations — none of these
 * tests mutate the baseline so isolation is incidental.
 */
final class AssistantCatalogControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    // Tests that GET /search renders the page heading and at least one fixture-backed assistant card.
    public function testIndexRendersResultsHeadingAndCards(): void
    {
        $crawler = $this->client->request('GET', '/search');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Find en assistent');
        self::assertGreaterThanOrEqual(
            1,
            $crawler->filter('a[href^="/assistant/"]')->count(),
            'catalogue must surface at least one fixture entry on the first page',
        );
    }

    // Tests that ?language_model[]=… narrows the card list to the matching facet count and renders the active-filter chip.
    public function testLanguageModelFilterNarrowsResults(): void
    {
        $expected = self::getContainer()->get(AssistantRepository::class)->languageModelFacetCounts()['gpt-4o'] ?? 0;
        self::assertGreaterThan(0, $expected, 'fixture baseline must include gpt-4o rows');

        $crawler = $this->client->request('GET', '/search?language_model%5B%5D=gpt-4o');

        self::assertResponseIsSuccessful();
        self::assertSame(
            $expected,
            $crawler->filter('a[href^="/assistant/"]')->count(),
            'card count must match the gpt-4o fixture facet count',
        );
        self::assertSelectorTextContains('[aria-label="Aktive filtre"]', 'gpt-4o');
    }

    // Ensures a filter value that matches nothing renders the empty-state copy and zero card links.
    public function testEmptyStateRendersWhenNoResults(): void
    {
        $crawler = $this->client->request('GET', '/search?language_model%5B%5D=does-not-exist');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ingen assistenter matcher');
        self::assertCount(0, $crawler->filter('a[href^="/assistant/"]'));
    }

    // Verifies pagination renders a current-page badge and a working next link when results span multiple pages.
    public function testPaginationShowsForMultiplePages(): void
    {
        // 21 fixture rows / PER_PAGE 12 → 2 pages.
        $crawler = $this->client->request('GET', '/search');

        self::assertResponseIsSuccessful();

        $next = $crawler->filter('a[rel="next"]');
        self::assertCount(1, $next, 'page 1 must offer a "next" link to page 2');
        self::assertStringContainsString('page=2', (string) $next->attr('href'));

        $current = $crawler->filter('[aria-current="page"]');
        self::assertSame('1', trim($current->text()), 'page 1 badge marks the current page');

        $page2Link = $crawler->filter('a[aria-label="Side 2"]');
        self::assertCount(1, $page2Link, 'numbered page-2 link is present');
    }

    // Ensures an active-filter chip's href drops only its targeted value while preserving the other filters.
    public function testChipRemoveLinkDropsTheFilteredValue(): void
    {
        $crawler = $this->client->request(
            'GET',
            '/search?language_model%5B%5D=gpt-4o&framework%5B%5D=openwebui',
        );

        self::assertResponseIsSuccessful();

        $chip = $crawler->filter('[aria-label="Aktive filtre"] a')->reduce(static function ($node) {
            return str_contains((string) $node->attr('aria-label'), 'gpt-4o');
        });
        self::assertCount(1, $chip, 'a removal chip for gpt-4o must be rendered');

        $params = [];
        parse_str(parse_url((string) $chip->attr('href'), \PHP_URL_QUERY) ?? '', $params);

        self::assertArrayNotHasKey('language_model', $params, 'chip removes the language_model filter');
        self::assertSame(['openwebui'], $params['framework'] ?? null, 'chip preserves the framework filter');
    }
}
