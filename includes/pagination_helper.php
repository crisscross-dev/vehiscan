<?php

function normalizePagination($pageInput, $perPageInput, array $allowedPerPage, int $defaultPerPage, int $maxPage = 10000): array
{
    $page = max(1, (int)$pageInput);
    $page = min($page, max(1, $maxPage));

    $perPage = (int)$perPageInput;
    if (!in_array($perPage, $allowedPerPage, true)) {
        $perPage = $defaultPerPage;
    }

    $offset = ($page - 1) * $perPage;

    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => $offset,
    ];
}

function clampPaginationPage(int $requestedPage, int $totalRows, int $perPage): array
{
    $totalPages = max(1, (int)ceil($totalRows / max(1, $perPage)));
    $page = min(max(1, $requestedPage), $totalPages);
    $offset = ($page - 1) * $perPage;

    return [
        'page' => $page,
        'total_pages' => $totalPages,
        'offset' => $offset,
    ];
}
