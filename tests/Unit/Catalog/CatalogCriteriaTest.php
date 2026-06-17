<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\CatalogCriteria;
use App\Http\QueryStringList;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class CatalogCriteriaTest extends TestCase
{
    private QueryStringList $lists;

    protected function setUp(): void
    {
        $this->lists = new QueryStringList();
    }

    public function testFromRequestWithEmptyQueryReturnsEmptyCriteria(): void
    {
        $criteria = CatalogCriteria::fromRequest(Request::create('/search'), $this->lists);

        self::assertNull($criteria->q);
        self::assertSame([], $criteria->languageModels);
        self::assertSame([], $criteria->frameworks);
        self::assertTrue($criteria->isEmpty());
        self::assertSame([], $criteria->activeFilters());
        self::assertSame([], $criteria->toQueryArray());
    }

    public function testFromRequestNormalisesWhitespaceQToNull(): void
    {
        $criteria = CatalogCriteria::fromRequest(
            Request::create('/search', 'GET', ['q' => '   ']),
            $this->lists,
        );

        self::assertNull($criteria->q, 'whitespace-only `q` must normalise to null');
        self::assertTrue($criteria->isEmpty());
    }

    public function testFromRequestReadsQAndBothFacets(): void
    {
        $criteria = CatalogCriteria::fromRequest(
            Request::create('/search', 'GET', [
                'q' => 'borger',
                'language_model' => ['gpt-4o'],
                'framework' => ['openwebui'],
            ]),
            $this->lists,
        );

        self::assertSame('borger', $criteria->q);
        self::assertSame(['gpt-4o'], $criteria->languageModels);
        self::assertSame(['openwebui'], $criteria->frameworks);
        self::assertFalse($criteria->isEmpty());
        self::assertSame(
            ['q' => 'borger', 'language_model' => ['gpt-4o'], 'framework' => ['openwebui']],
            $criteria->toQueryArray(),
        );
    }

    public function testActiveFiltersYieldsQThenLanguageModelsThenFrameworks(): void
    {
        $criteria = new CatalogCriteria(
            q: 'borger',
            languageModels: ['gpt-4o', 'mistral-large'],
            frameworks: ['openwebui'],
        );

        $filters = $criteria->activeFilters();

        self::assertCount(4, $filters);
        self::assertSame(['q', 'language_model', 'language_model', 'framework'], array_map(
            static fn ($f) => $f->type,
            $filters,
        ));
        self::assertSame('borger', $filters[0]->value);
        self::assertSame('"borger"', $filters[0]->label, 'search-query chip wraps the value in quotes');
        self::assertSame('gpt-4o', $filters[1]->value);
        self::assertSame('gpt-4o', $filters[1]->label, 'facet chips display the raw value');
    }

    public function testActiveFilterRemoveQueryDropsOnlyTargetedValue(): void
    {
        $criteria = new CatalogCriteria(
            languageModels: ['gpt-4o', 'mistral-large'],
            frameworks: ['openwebui'],
        );

        $filters = $criteria->activeFilters();

        // First chip targets 'gpt-4o' — removeQuery must keep mistral-large and the framework.
        self::assertSame(
            ['language_model' => ['mistral-large'], 'framework' => ['openwebui']],
            $filters[0]->removeQuery,
        );
    }

    public function testActiveFilterRemoveQueryDropsKeyWhenLastValueRemoved(): void
    {
        $criteria = new CatalogCriteria(
            languageModels: ['gpt-4o'],
            frameworks: ['openwebui'],
        );

        $filters = $criteria->activeFilters();

        // Removing the only LM value collapses the key entirely so the URL
        // emits `?framework[]=openwebui`, not `?language_model[]=&framework[]=…`.
        self::assertSame(['framework' => ['openwebui']], $filters[0]->removeQuery);
        self::assertArrayNotHasKey('language_model', $filters[0]->removeQuery);
    }
}
