<?php
declare(strict_types=1);

require __DIR__ . '/../includes/event_catalog.php';

$failures = [];
function expectCatalog(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$categories = eventCategories();
expectCatalog(isset($categories['music'], $categories['community'], $categories['learning'], $categories['other']), 'Catalog must expose the supported finite category list.');

$filters = normaliseCatalogFilters([
    'q' => '  acoustic  ',
    'date' => 'weekend',
    'category' => 'not-a-category',
    'low_data' => 'yes',
    'ignored' => 'value',
]);
expectCatalog($filters === ['q' => 'acoustic', 'date' => 'weekend', 'category' => '', 'low_data' => true], 'Malformed and unknown filters must safely fall back to supported values.');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE events (event_id INTEGER PRIMARY KEY, title TEXT, description TEXT, location TEXT, category TEXT, status TEXT, start_time TEXT)');
$insert = $pdo->prepare('INSERT INTO events (event_id, title, description, location, category, status, start_time) VALUES (?, ?, ?, ?, ?, ?, ?)');
$insert->execute([1, 'Acoustic Future', 'Student bands', 'Union Hall', 'music', 'approved', '2099-06-05 19:00:00']);
$insert->execute([2, 'Draft Acoustic', 'Hidden draft', 'Union Hall', 'music', 'draft', '2099-06-06 19:00:00']);
$insert->execute([3, 'Acoustic Past', 'Past event', 'Union Hall', 'music', 'approved', '2020-06-06 19:00:00']);
$insert->execute([4, 'Community Future', 'Neighbourhood event', 'Library', 'community', 'approved', '2099-06-06 14:00:00']);

$where = catalogWhere(normaliseCatalogFilters(['q' => 'acoustic', 'category' => 'music']));
$statement = $pdo->prepare('SELECT event_id FROM events e ' . $where['sql'] . ' ORDER BY event_id');
$statement->execute($where['params']);
expectCatalog($statement->fetchAll(PDO::FETCH_COLUMN) === [1], 'Public catalog must exclude drafts and past events while applying keyword and category filters.');

$where = catalogWhere(normaliseCatalogFilters(['date' => 'today']), new DateTimeImmutable('2099-06-05 09:00:00'));
$statement = $pdo->prepare('SELECT event_id FROM events e ' . $where['sql'] . ' ORDER BY event_id');
$statement->execute($where['params']);
expectCatalog($statement->fetchAll(PDO::FETCH_COLUMN) === [1], 'Today filter must use the supplied local-day boundaries.');
expectCatalog(validHttpUrl('https://example.com/event'), 'HTTPS social links must be accepted.');
expectCatalog(validHttpUrl('http://example.com/event'), 'HTTP social links must be accepted.');
expectCatalog(validHttpUrl(''), 'An optional empty social link must be accepted.');
expectCatalog(!validHttpUrl('javascript:alert(1)'), 'JavaScript URLs must be rejected.');
expectCatalog(!validHttpUrl('ftp://example.com/file'), 'Non-HTTP URL schemes must be rejected.');
expectCatalog(eventStatusAfterSave('creator', 'approved', 'save', false) === 'approved', 'Ordinary edits to an existing approved event must preserve its status.');
expectCatalog(eventStatusAfterSave('creator', 'approved', 'save_draft', false) === 'draft', 'Creator must be able to move an event to draft.');
expectCatalog(eventStatusAfterSave('creator', 'draft', 'submit', false) === 'pending', 'Creator must be able to submit a draft for review.');
expectCatalog(eventStatusAfterSave('staff', 'approved', 'save_draft', false) === 'approved', 'Staff must not change an event review state.');
expectCatalog(eventStatusAfterSave('creator', null, 'submit', true) === 'pending', 'A new submitted event must enter the review queue.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "ok\n";
