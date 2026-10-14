<?php

function page_params(int $defaultLimit = 10): array
{
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? $defaultLimit)));
    $page  = max(1, (int) ($_GET['page'] ?? 1));

    return [$page, $limit, ($page - 1) * $limit];
}

function sort_clause(array $allowed, string $default): string
{
    $parts = [];

    foreach (explode(',', (string) ($_GET['sort'] ?? '')) as $token) {
        $token     = trim($token);
        $direction = str_starts_with($token, '-') ? 'DESC' : 'ASC';
        $name      = ltrim($token, '-+ ');

        if (isset($allowed[$name])) {
            $parts[] = $allowed[$name] . ' ' . $direction;
        }
    }

    return implode(', ', $parts ?: [$default]);
}

function paginate(string $columns, string $from, array $conditions, array $params, array $sortable, string $defaultSort, ?callable $transform = null): void
{
    [$page, $limit, $offset] = page_params();

    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $count = db()->prepare("SELECT COUNT(*) FROM $from$where");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $order = sort_clause($sortable, $defaultSort);

    $statement = db()->prepare("SELECT $columns FROM $from$where ORDER BY $order LIMIT $limit OFFSET $offset");
    $statement->execute($params);
    $rows = $statement->fetchAll();

    if ($transform) {
        $rows = array_map($transform, $rows);
    }

    json_ok($rows, '', 200, [
        'total' => $total,
        'page'  => $page,
        'limit' => $limit,
        'pages' => max(1, (int) ceil($total / $limit)),
    ]);
}

function search_condition(array $columns, string $term, array &$params): string
{
    $likes = [];

    foreach ($columns as $column) {
        $likes[]  = "$column LIKE ?";
        $params[] = '%' . $term . '%';
    }

    return '(' . implode(' OR ', $likes) . ')';
}
