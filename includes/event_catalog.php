<?php
declare(strict_types=1);

function eventCategories(): array
{
    return [
        'music' => 'Music & performance',
        'community' => 'Community',
        'learning' => 'Learning & workshops',
        'sports' => 'Sports & wellbeing',
        'social' => 'Social',
        'other' => 'Other',
    ];
}

function normaliseCatalogFilters(array $query): array
{
    $keyword = trim((string) ($query['q'] ?? ''));
    if (mb_strlen($keyword) > 80) {
        $keyword = mb_substr($keyword, 0, 80);
    }
    $date = (string) ($query['date'] ?? '');
    if (!in_array($date, ['', 'today', 'weekend', 'week'], true)) {
        $date = '';
    }
    $category = (string) ($query['category'] ?? '');
    if (!array_key_exists($category, eventCategories())) {
        $category = '';
    }
    $lowDataValue = strtolower((string) ($query['low_data'] ?? ''));
    return [
        'q' => $keyword,
        'date' => $date,
        'category' => $category,
        'low_data' => in_array($lowDataValue, ['1', 'true', 'yes', 'on'], true),
    ];
}

function catalogWhere(array $filters, ?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now');
    $conditions = ["e.status = 'approved'", 'e.start_time >= ?'];
    $params = [$now->format('Y-m-d H:i:s')];

    if (($filters['q'] ?? '') !== '') {
        $conditions[] = '(LOWER(e.title) LIKE ? OR LOWER(e.description) LIKE ? OR LOWER(e.location) LIKE ?)';
        $term = '%' . mb_strtolower((string) $filters['q']) . '%';
        array_push($params, $term, $term, $term);
    }
    if (($filters['category'] ?? '') !== '') {
        $conditions[] = 'e.category = ?';
        $params[] = (string) $filters['category'];
    }

    $date = (string) ($filters['date'] ?? '');
    if ($date === 'today') {
        $start = $now->setTime(0, 0);
        $end = $start->modify('+1 day');
        $conditions[] = 'e.start_time >= ? AND e.start_time < ?';
        array_push($params, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
    } elseif ($date === 'weekend') {
        $daysToSaturday = (6 - (int) $now->format('N') + 7) % 7;
        $start = $now->setTime(0, 0)->modify('+' . $daysToSaturday . ' days');
        $end = $start->modify('+2 days');
        $conditions[] = 'e.start_time >= ? AND e.start_time < ?';
        array_push($params, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'));
    } elseif ($date === 'week') {
        $end = $now->modify('+7 days');
        $conditions[] = 'e.start_time < ?';
        $params[] = $end->format('Y-m-d H:i:s');
    }

    return ['sql' => 'WHERE ' . implode(' AND ', $conditions), 'params' => $params];
}

function validHttpUrl(string $value): bool
{
    if ($value === '') {
        return true;
    }
    if (!filter_var($value, FILTER_VALIDATE_URL)) {
        return false;
    }
    return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
}

function eventStatusAfterSave(string $role, ?string $currentStatus, string $intent, bool $isNew): string
{
    if ($role !== 'creator') {
        return $currentStatus ?? 'pending';
    }
    if ($intent === 'save_draft') {
        return 'draft';
    }
    if ($intent === 'submit') {
        return 'pending';
    }
    return $isNew ? 'draft' : ($currentStatus ?? 'draft');
}
