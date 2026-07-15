<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * PaginationRenderer — renders HTML page-navigation from paginate() metadata.
 *
 * Usage in a Blade template:
 *   @pagination($page)               — uses current request URL, preserves all query params
 *   @pagination($page, '/users')     — explicit base URL
 *
 * The $meta array is the full result of ModelQuery::paginate() or the 'meta'
 * portion from ApiResource::fromPaginator().  Required keys:
 *   total, per_page, current_page, last_page, from, to
 *
 * Returns an empty string when last_page <= 1 (nothing to paginate).
 *
 * CSS classes use the `lm-` prefix to avoid collisions. Override freely.
 */
class PaginationRenderer
{
    public static function render(array $meta, string $baseUrl = ''): string
    {
        $lastPage    = (int)($meta['last_page']    ?? 1);
        $currentPage = (int)($meta['current_page'] ?? 1);
        $total       = (int)($meta['total']        ?? 0);
        $from        = (int)($meta['from']         ?? 0);
        $to          = (int)($meta['to']           ?? 0);

        if ($lastPage <= 1) {
            return '';
        }

        if ($baseUrl === '') {
            $uri     = $_SERVER['REQUEST_URI'] ?? '/';
            $baseUrl = $uri;
        }

        $prevDisabled = $currentPage <= 1;
        $nextDisabled = $currentPage >= $lastPage;

        $html  = '<nav class="lm-pagination" role="navigation" aria-label="Page navigation">' . "\n";
        $html .= '  <span class="lm-pagination-info">';
        $html .= 'Showing ' . $from . ' to ' . $to . ' of ' . $total . ' results';
        $html .= '</span>' . "\n";
        $html .= '  <ul class="lm-pagination-list">' . "\n";

        // Previous
        if ($prevDisabled) {
            $html .= '    <li class="lm-page-item lm-page-disabled" aria-disabled="true">';
            $html .= '<span class="lm-page-link" aria-label="Previous page">&lsaquo; Prev</span>';
            $html .= '</li>' . "\n";
        } else {
            $html .= '    <li class="lm-page-item">';
            $html .= '<a class="lm-page-link" href="' . self::pageUrl($baseUrl, $currentPage - 1) . '" aria-label="Previous page">&lsaquo; Prev</a>';
            $html .= '</li>' . "\n";
        }

        // Page numbers with ellipsis
        $visiblePages = self::pageRange($currentPage, $lastPage);
        $prev = null;
        foreach ($visiblePages as $page) {
            if ($prev !== null && $page - $prev > 1) {
                $html .= '    <li class="lm-page-item lm-page-ellipsis" aria-hidden="true"><span class="lm-page-link">&hellip;</span></li>' . "\n";
            }
            if ($page === $currentPage) {
                $html .= '    <li class="lm-page-item lm-page-active">';
                $html .= '<a class="lm-page-link" href="' . self::pageUrl($baseUrl, $page) . '" aria-current="page" aria-label="Page ' . $page . ', current">' . $page . '</a>';
                $html .= '</li>' . "\n";
            } else {
                $html .= '    <li class="lm-page-item">';
                $html .= '<a class="lm-page-link" href="' . self::pageUrl($baseUrl, $page) . '" aria-label="Page ' . $page . '">' . $page . '</a>';
                $html .= '</li>' . "\n";
            }
            $prev = $page;
        }

        // Next
        if ($nextDisabled) {
            $html .= '    <li class="lm-page-item lm-page-disabled" aria-disabled="true">';
            $html .= '<span class="lm-page-link" aria-label="Next page">Next &rsaquo;</span>';
            $html .= '</li>' . "\n";
        } else {
            $html .= '    <li class="lm-page-item">';
            $html .= '<a class="lm-page-link" href="' . self::pageUrl($baseUrl, $currentPage + 1) . '" aria-label="Next page">Next &rsaquo;</a>';
            $html .= '</li>' . "\n";
        }

        $html .= '  </ul>' . "\n";
        $html .= '</nav>' . "\n";

        return $html;
    }

    /** Builds a URL for a specific page, preserving existing query parameters. */
    private static function pageUrl(string $baseUrl, int $page): string
    {
        $parts = parse_url($baseUrl);
        parse_str($parts['query'] ?? '', $params);
        $params['page'] = $page;
        $path = $parts['path'] ?? '/';
        return htmlspecialchars($path . '?' . http_build_query($params), ENT_QUOTES, 'UTF-8');
    }

    /** Returns the set of page numbers to show, always including first, last, and current ±2. */
    private static function pageRange(int $current, int $last): array
    {
        $pages = [];
        for ($i = 1; $i <= $last; $i++) {
            if ($i === 1 || $i === $last || abs($i - $current) <= 2) {
                $pages[] = $i;
            }
        }
        return $pages;
    }
}
