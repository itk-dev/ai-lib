<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Assistant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * Paginated catalogue listing filtered by the given facet selections.
     *
     * Each filter parameter is an OR-within / AND-across set: a non-empty
     * `$languageModels` keeps rows whose `languageModel` is in that list,
     * AND a non-empty `$frameworks` further narrows on `framework`. An
     * empty list means "no filter on this facet". Results are sorted by
     * `id ASC` for a stable, fixture-friendly ordering.
     *
     * @param list<string> $languageModels exact `languageModel` values to keep; empty list = no filter
     * @param list<string> $frameworks     exact `framework` values to keep; empty list = no filter
     * @param int          $page           1-based page number; clamped to `>= 1` by the caller
     * @param int          $perPage        results per page; must be `>= 1`
     *
     * @return Paginator<Assistant>
     */
    public function findPaginated(array $languageModels, array $frameworks, int $page, int $perPage): Paginator
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.id', 'ASC');

        if ([] !== $languageModels) {
            $qb->andWhere('a.languageModel IN (:languageModels)')
                ->setParameter('languageModels', $languageModels);
        }

        if ([] !== $frameworks) {
            $qb->andWhere('a.framework IN (:frameworks)')
                ->setParameter('frameworks', $frameworks);
        }

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        return new Paginator($qb->getQuery(), false);
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
     */
    public function frameworkFacetCounts(): array
    {
        return $this->facetCounts('framework');
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
