<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Catalog\CatalogCriteria;
use App\Entity\Assistant;
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

    public function testRepositoryIsResolvableAndFindsFixtureRow(): void
    {
        self::assertInstanceOf(AssistantRepository::class, $this->repository);

        $assistant = $this->repository->findOneBy(['title' => 'Borgerservice-vejviser']);

        self::assertInstanceOf(Assistant::class, $assistant);
        self::assertSame('Borgerservice-vejviser', $assistant->getTitle());
        self::assertSame(['borgerservice', 'social', 'jura'], $assistant->getTags());
    }

    public function testFindPaginatedReturnsAllRowsForEmptyCriteria(): void
    {
        $paginator = $this->repository->findPaginated(new CatalogCriteria(), page: 1, perPage: 100);

        self::assertCount(21, $paginator, 'fixture baseline seeds 21 assistants');
        self::assertSame(21, iterator_count($paginator->getIterator()));
    }

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
}
