<?php

declare(strict_types=1);

namespace App\Pagination;

final class PageMetadata
{
    /**
     * Plain holder for the data a paginated view needs to render its
     * "N resultater" count, current-page badge, and page navigation.
     *
     * Constructed by {@see PaginationCalculator}; consumers (controllers,
     * templates) treat it as a read-only DTO.
     *
     * @param int $total     total matching rows across the whole result set
     * @param int $perPage   page size used to compute `$pageCount`
     * @param int $page      current 1-based page number (clamped by the caller)
     * @param int $pageCount total number of pages; always `>= 1`, even when `$total === 0`
     */
    public function __construct(
        public readonly int $total,
        public readonly int $perPage,
        public readonly int $page,
        public readonly int $pageCount,
    ) {
    }
}
