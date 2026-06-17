<?php

declare(strict_types=1);

namespace App\Pagination;

use Doctrine\ORM\Tools\Pagination\Paginator;

final class PaginationCalculator
{
    /**
     * Build {@see PageMetadata} from a Doctrine ORM `Paginator`.
     *
     * Reads the total row count from the paginator (which issues the
     * COUNT query lazily on first access), derives `pageCount` from
     * `$perPage`, and floors it to a minimum of 1 so views can render
     * a "page 1 of 1" state even when the result set is empty.
     *
     * @param Paginator<object> $paginator the executed paginator whose total is needed
     * @param int               $page      the 1-based page the caller asked for; passed through verbatim
     * @param int               $perPage   the page size that the caller used when querying; must be `>= 1`
     *
     * @return PageMetadata immutable read-only view metadata
     */
    public function fromPaginator(Paginator $paginator, int $page, int $perPage): PageMetadata
    {
        $total = \count($paginator);
        $pageCount = max(1, (int) ceil($total / $perPage));

        return new PageMetadata($total, $perPage, $page, $pageCount);
    }
}
