<?php

declare(strict_types=1);

use Core\Http\PaginationRenderer;

function makeMeta(int $total, int $perPage, int $currentPage): array
{
    $lastPage = $total === 0 ? 1 : (int)ceil($total / $perPage);
    $offset   = ($currentPage - 1) * $perPage;
    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'last_page'    => $lastPage,
        'from'         => $total === 0 ? 0 : $offset + 1,
        'to'           => min($offset + $perPage, $total),
    ];
}

// -----------------------------------------------------------------------
// Single page — render nothing
// -----------------------------------------------------------------------

test('returns empty string when there is only one page', function () {
    $html = PaginationRenderer::render(makeMeta(5, 10, 1), '/items');
    expect($html)->toBe('');
});

test('returns empty string when total is zero', function () {
    $html = PaginationRenderer::render(makeMeta(0, 10, 1), '/items');
    expect($html)->toBe('');
});

// -----------------------------------------------------------------------
// Basic structure
// -----------------------------------------------------------------------

test('renders a nav element with lm-pagination class', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 1), '/items');
    expect($html)->toContain('<nav class="lm-pagination"');
});

test('renders the from/to/total info text', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 2), '/items');
    expect($html)->toContain('11');
    expect($html)->toContain('20');
    expect($html)->toContain('30');
});

// -----------------------------------------------------------------------
// Page links
// -----------------------------------------------------------------------

test('page links include the page query parameter', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 1), '/items');
    expect($html)->toContain('page=2');
    expect($html)->toContain('page=3');
});

test('current page carries aria-current="page"', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 2), '/items');
    expect($html)->toContain('aria-current="page"');
});

test('current page has lm-page-active class', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 2), '/items');
    expect($html)->toContain('lm-page-active');
});

// -----------------------------------------------------------------------
// Prev / Next disabled states
// -----------------------------------------------------------------------

test('previous link is disabled on the first page', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 1), '/items');
    expect($html)->toContain('lm-page-disabled');
    expect($html)->toContain('aria-disabled="true"');
});

test('next link is disabled on the last page', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 3), '/items');
    // There should be a disabled item (next)
    expect($html)->toContain('lm-page-disabled');
});

test('previous link is active on pages after the first', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 2), '/items');
    expect($html)->toContain('page=1');
});

test('next link is active before the last page', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 2), '/items');
    expect($html)->toContain('page=3');
});

// -----------------------------------------------------------------------
// Ellipsis
// -----------------------------------------------------------------------

test('adds ellipsis for large page ranges', function () {
    $html = PaginationRenderer::render(makeMeta(200, 10, 10), '/items');
    expect($html)->toContain('&hellip;');
});

test('no ellipsis when all pages fit in the window', function () {
    // 5 pages, current = 3 → pages 1-5 all within ±2 of current
    $html = PaginationRenderer::render(makeMeta(50, 10, 3), '/items');
    expect($html)->not->toContain('&hellip;');
});

// -----------------------------------------------------------------------
// Base URL handling
// -----------------------------------------------------------------------

test('preserves existing query parameters in page links', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 1), '/search?q=foo');
    expect($html)->toContain('q=foo');
    expect($html)->toContain('page=2');
});

test('HTML-encodes the generated URLs', function () {
    $html = PaginationRenderer::render(makeMeta(30, 10, 1), '/items');
    expect($html)->not->toContain('"&');  // unescaped & in href would be invalid HTML
});
