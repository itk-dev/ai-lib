<?php

declare(strict_types=1);

namespace App\Repository;

use App\Catalog\CatalogCriteria;
use App\Catalog\CatalogSort;
use App\Entity\Assistant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Assistant>
 */
class AssistantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assistant::class);
    }

    /**
     * Count how many distinct `languageModel` values are in use across
     * the catalogue. Powers the frontpage "Sprogmodeller" stat.
     */
    public function countDistinctLanguageModels(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.languageModel)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Paginated catalogue listing filtered by the given criteria.
     *
     * Each facet selection on the criteria is an OR-within / AND-across
     * set: a non-empty `languageModels` keeps rows whose `languageModel`
     * is in that list, AND a non-empty `frameworks` further narrows on
     * `framework`, AND a non-empty `tags` keeps rows carrying at least
     * one of the named tags. An empty facet means "no filter on this
     * facet". A non-empty `q` further narrows to rows whose title or
     * description contains the query (case-insensitive substring).
     * Results are ordered according to `criteria->sort` (see
     * {@see self::applySort()}), with `id` as a tiebreaker so the order
     * is stable even when the primary sort key ties.
     *
     * The tag filter is expressed as an `id IN (subquery)` over the
     * `assistant_tag` join rather than a fetch join, so multiple selected
     * tags OR together without inflating the row count and breaking the
     * paginator's LIMIT/OFFSET maths.
     *
     * @param CatalogCriteria $criteria the user's filter selections
     * @param int             $page     1-based page number; clamped to `>= 1` by the caller
     * @param int             $perPage  results per page; must be `>= 1`
     *
     * @return Paginator<Assistant>
     *
     * @throws \Doctrine\DBAL\Exception when the underlying connection or query execution fails
     */
    public function findPaginated(CatalogCriteria $criteria, int $page, int $perPage): Paginator
    {
        $qb = $this->createQueryBuilder('a');
        $this->applySort($qb, $criteria->sort);

        if (null !== $criteria->q) {
            // Parenthesise the OR so it binds as a unit when ANDed with the
            // facet clauses below — otherwise SQL precedence would read it as
            // `title LIKE … OR (description LIKE … AND facet …)`.
            $qb->andWhere('(LOWER(a.title) LIKE :q OR LOWER(a.description) LIKE :q)')
                ->setParameter('q', '%'.mb_strtolower($criteria->q).'%');
        }

        if ([] !== $criteria->languageModels) {
            $qb->andWhere('a.languageModel IN (:languageModels)')
                ->setParameter('languageModels', $criteria->languageModels);
        }

        if ([] !== $criteria->frameworks) {
            $qb->andWhere('a.framework IN (:frameworks)')
                ->setParameter('frameworks', $criteria->frameworks);
        }

        if ([] !== $criteria->tags) {
            $qb->andWhere($qb->expr()->in(
                'a.id',
                $this->createQueryBuilder('a2')
                    ->select('a2.id')
                    ->join('a2.tags', 't')
                    ->andWhere('t.name IN (:tags)')
                    ->getDQL(),
            ))->setParameter('tags', $criteria->tags);
        }

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($qb->getQuery(), false);
    }

    /**
     * Apply the catalogue ordering for the given sort to a query builder.
     *
     * Each case sets the primary `ORDER BY` and then appends `a.id` in the
     * same direction as a tiebreaker, so rows that tie on the primary key
     * (e.g. the timestamp sorts, since fixtures share a creation instant)
     * still come back in a deterministic, paginatable order. Name sorts
     * tiebreak on `a.id ASC` regardless of name direction — the tiebreak
     * only needs to be stable, not aligned with the title direction.
     *
     * @param QueryBuilder $qb   the catalogue query under construction
     * @param CatalogSort  $sort the ordering the user asked for
     */
    private function applySort(QueryBuilder $qb, CatalogSort $sort): void
    {
        match ($sort) {
            CatalogSort::Newest => $qb->orderBy('a.createdAt', 'DESC')->addOrderBy('a.id', 'DESC'),
            CatalogSort::Oldest => $qb->orderBy('a.createdAt', 'ASC')->addOrderBy('a.id', 'ASC'),
            CatalogSort::RecentlyUpdated => $qb->orderBy('a.updatedAt', 'DESC')->addOrderBy('a.id', 'DESC'),
            CatalogSort::NameAsc => $qb->orderBy('a.title', 'ASC')->addOrderBy('a.id', 'ASC'),
            CatalogSort::NameDesc => $qb->orderBy('a.title', 'DESC')->addOrderBy('a.id', 'ASC'),
        };
    }

    /**
     * Count assistants grouped by their `languageModel` value.
     *
     * Powers the catalogue's "Sprogmodel" facet — label + per-bucket
     * count. Returns counts across the full catalogue (not narrowed by
     * the active filter set) so the user can always see what other
     * buckets are reachable from the current state.
     *
     * @return array<string, int> ordered by count DESC then value ASC; key is the language-model string
     *
     * @throws \Doctrine\DBAL\Exception when the underlying connection or query execution fails
     */
    public function languageModelFacetCounts(): array
    {
        return $this->facetCounts('languageModel');
    }

    /**
     * Count assistants grouped by their `framework` value.
     *
     * Powers the catalogue's "Rammeværk" facet — label + per-bucket
     * count, computed against the full catalogue for the same reason
     * documented on {@see self::languageModelFacetCounts()}.
     *
     * @return array<string, int> ordered by count DESC then value ASC; key is the framework string
     *
     * @throws \Doctrine\DBAL\Exception when the underlying connection or query execution fails
     */
    public function frameworkFacetCounts(): array
    {
        return $this->facetCounts('framework');
    }

    /**
     * Count assistants grouped by attached tag name.
     *
     * Powers the catalogue's "Tags" facet. Unlike the scalar facets this
     * joins the `assistant_tag` relation and groups on the tag name, so
     * an assistant contributes to each of its tags' buckets. Counts are
     * computed across the full catalogue (not narrowed by the active
     * filter set) for the same reason documented on
     * {@see self::languageModelFacetCounts()}. Tags carried by no
     * assistant never appear, since the inner join drops them.
     *
     * @return array<string, int> ordered by count DESC then name ASC; key is the tag name
     *
     * @throws \Doctrine\DBAL\Exception when the underlying connection or query execution fails
     */
    public function tagFacetCounts(): array
    {
        /** @var list<array{value: string, count: int|string}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('t.name AS value, COUNT(DISTINCT a.id) AS count')
            ->join('a.tags', 't')
            ->groupBy('t.name')
            ->orderBy('count', 'DESC')
            ->addOrderBy('value', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['value']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Build a value → count map for one scalar field on `Assistant`.
     *
     * Shared backbone for the facet helpers above. Groups on the named
     * field, orders the result by count DESC then value ASC, and casts
     * each row to native PHP types before returning.
     *
     * @param string $field DQL field name on the `Assistant` alias `a` (e.g. `languageModel`)
     *
     * @return array<string, int> ordered by count DESC then value ASC
     *
     * @throws \Doctrine\DBAL\Exception when the underlying connection or query execution fails
     */
    private function facetCounts(string $field): array
    {
        /** @var list<array{value: string, count: int|string}> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select(sprintf('a.%s AS value, COUNT(a.id) AS count', $field))
            ->groupBy('a.'.$field)
            ->orderBy('count', 'DESC')
            ->addOrderBy('value', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['value']] = (int) $row['count'];
        }

        return $counts;
    }
}
