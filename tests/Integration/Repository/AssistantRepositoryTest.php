<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Catalog\CatalogCriteria;
use App\Entity\Assistant;
use App\Entity\Tag;
use App\Repository\AssistantRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AssistantRepositoryTest extends KernelTestCase
{
    private AssistantRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(AssistantRepository::class);
    }

    // Tests that the repository is wired through the container and finds a known fixture row by title.
    public function testRepositoryIsResolvableAndFindsFixtureRow(): void
    {
        self::assertInstanceOf(AssistantRepository::class, $this->repository);

        $assistant = $this->repository->findOneBy(['title' => 'Borgerservice-vejviser']);

        self::assertInstanceOf(Assistant::class, $assistant);
        self::assertSame('Borgerservice-vejviser', $assistant->getTitle());
        self::assertSame(
            ['borgerservice', 'social', 'jura'],
            array_map(static fn (Tag $t) => $t->getName(), $assistant->getTags()->toArray()),
        );
    }

    // Tests that empty criteria returns every fixture row (no IN clauses applied).
    public function testFindPaginatedReturnsAllRowsForEmptyCriteria(): void
    {
        $paginator = $this->repository->findPaginated(new CatalogCriteria(), page: 1, perPage: 100);

        self::assertCount(21, $paginator, 'fixture baseline seeds 21 assistants');
        self::assertSame(21, iterator_count($paginator->getIterator()));
    }

    // Tests that the languageModels criterion narrows results to rows whose languageModel is in the selected list.
    public function testFindPaginatedFiltersByLanguageModel(): void
    {
        $criteria = new CatalogCriteria(languageModels: ['gpt-4o']);

        $paginator = $this->repository->findPaginated($criteria, page: 1, perPage: 100);

        $models = [];
        foreach ($paginator as $assistant) {
            self::assertInstanceOf(Assistant::class, $assistant);
            $models[] = $assistant->getLanguageModel();
        }
        self::assertNotEmpty($models, 'gpt-4o fixture rows must be reachable');
        self::assertSame(['gpt-4o'], array_values(array_unique($models)));
        self::assertCount(\count($models), $paginator);
    }

    // Tests that the frameworks criterion narrows results by framework value via the IN clause.
    public function testFindPaginatedFiltersByFramework(): void
    {
        $criteria = new CatalogCriteria(frameworks: ['openwebui']);

        $paginator = $this->repository->findPaginated($criteria, page: 1, perPage: 100);

        $frameworks = [];
        foreach ($paginator as $assistant) {
            self::assertInstanceOf(Assistant::class, $assistant);
            $frameworks[] = $assistant->getFramework();
        }
        // Every fixture row uses openwebui, so the IN clause must return all 21.
        self::assertCount(21, $paginator);
        self::assertSame(['openwebui'], array_values(array_unique($frameworks)));
    }

    // Ensures pagination yields disjoint pages in id-ASC order.
    public function testFindPaginatedAppliesOffsetForPaging(): void
    {
        $perPage = 10;

        $firstPage = $this->repository->findPaginated(new CatalogCriteria(), page: 1, perPage: $perPage);
        $secondPage = $this->repository->findPaginated(new CatalogCriteria(), page: 2, perPage: $perPage);

        $firstIds = array_map(static fn (Assistant $a) => $a->getId(), iterator_to_array($firstPage->getIterator()));
        $secondIds = array_map(static fn (Assistant $a) => $a->getId(), iterator_to_array($secondPage->getIterator()));

        self::assertCount(10, $firstIds);
        self::assertCount(10, $secondIds);
        self::assertSame([], array_intersect($firstIds, $secondIds), 'pages must not overlap');
        self::assertGreaterThan(max($firstIds), min($secondIds), 'page 2 starts after page 1 by id-ASC order');
    }

    // Verifies the facet-count helpers reflect the fixture baseline (five LM buckets summing to 21, single openwebui bucket).
    public function testFacetCountsReflectFixtureBaseline(): void
    {
        $languageModels = $this->repository->languageModelFacetCounts();
        $frameworks = $this->repository->frameworkFacetCounts();

        $languageModelKeys = array_keys($languageModels);
        sort($languageModelKeys);
        self::assertSame(
            ['claude-3.5-sonnet', 'gpt-4o', 'gpt-4o-mini', 'llama-3.1-70b', 'mistral-large'],
            $languageModelKeys,
        );
        self::assertSame(21, array_sum($languageModels), 'facet counts sum to total fixture rows');

        self::assertSame(['openwebui' => 21], $frameworks);
    }

    // Tests that a `q` query narrows to rows whose title contains the term (case-insensitive).
    public function testFindPaginatedFiltersByQueryOnTitle(): void
    {
        $criteria = new CatalogCriteria(q: 'JOURNALISERINGSASSISTENT');

        $paginator = $this->repository->findPaginated($criteria, page: 1, perPage: 100);

        $titles = array_map(static fn (Assistant $a) => $a->getTitle(), iterator_to_array($paginator->getIterator()));
        self::assertSame(['Journaliseringsassistent'], $titles, 'query matches the title case-insensitively');
    }

    // Tests that a `q` query also matches the description, reaching every row that mentions the term.
    public function testFindPaginatedFiltersByQueryOnDescription(): void
    {
        // "KPI-rapporter" appears only in the Statistikfortolker description,
        // generated twice across the fifteen rotated entries.
        $criteria = new CatalogCriteria(q: 'kpi');

        $paginator = $this->repository->findPaginated($criteria, page: 1, perPage: 100);

        self::assertCount(2, $paginator, 'both KPI-mentioning rows are reached via the description');
        foreach ($paginator as $assistant) {
            self::assertStringContainsStringIgnoringCase('kpi', $assistant->getDescription());
        }
    }

    // Tests that the tags criterion keeps rows carrying at least one of the named tags (OR-within), without duplicate rows.
    public function testFindPaginatedFiltersByTags(): void
    {
        $criteria = new CatalogCriteria(tags: ['jura']);

        $paginator = $this->repository->findPaginated($criteria, page: 1, perPage: 100);

        // 'jura' is on the Borgerservice-vejviser detailed row plus the two
        // Forvaltningsret-vejviser generated rows.
        self::assertCount(3, $paginator);
        foreach ($paginator as $assistant) {
            self::assertContains(
                'jura',
                array_map(static fn (Tag $t) => $t->getName(), $assistant->getTags()->toArray()),
            );
        }
    }

    // Ensures multiple tags OR within the facet — the result is the union, deduplicated to one row per assistant.
    public function testFindPaginatedTagsOrWithinFacet(): void
    {
        $criteria = new CatalogCriteria(tags: ['jura', 'arkiv']);

        $paginator = $this->repository->findPaginated($criteria, page: 1, perPage: 100);

        // jura (3 rows) ∪ arkiv (1 distinct row) = 4, no duplicates from the join.
        self::assertCount(4, $paginator);
        $ids = array_map(static fn (Assistant $a) => (string) $a->getId(), iterator_to_array($paginator->getIterator()));
        self::assertSame($ids, array_values(array_unique($ids)), 'no assistant appears twice');
    }

    // Verifies tagFacetCounts() reflects the fixture baseline: 24 distinct tags summing to 45, ordered by count DESC.
    public function testTagFacetCountsReflectFixtureBaseline(): void
    {
        $tags = $this->repository->tagFacetCounts();

        self::assertCount(24, $tags, 'fixture baseline seeds 24 distinct tags');
        self::assertSame(45, array_sum($tags), 'tag counts sum to the number of assistant-tag links');
        self::assertSame(3, $tags['jura'], 'jura spans one detailed and two generated rows');
        self::assertSame(1, $tags['social']);

        $counts = array_values($tags);
        $sorted = $counts;
        rsort($sorted);
        self::assertSame($sorted, $counts, 'buckets are ordered by count DESC');
    }
}
