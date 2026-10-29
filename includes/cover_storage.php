<?php
declare(strict_types=1);
function coverStorageLock() {
    $dir=__DIR__.'/../storage/covers';
    if(!is_dir($dir) && !mkdir($dir,0700,true) && !is_dir($dir))throw new RuntimeException('Cannot create storage');
    $lock=fopen($dir.'/.write.lock','c');
    if(!$lock || !flock($lock,LOCK_EX|LOCK_NB))throw new DomainException('Another cover is being saved. Please try again.');
    chmod($dir.'/.write.lock',0600);return $lock;
}
function checkCoverQuota(PDO $pdo, int $owner, ?string $incoming, ?string $replaced): void {
    global $config;
    if(!$incoming)return;
    $dir=__DIR__.'/../storage/covers/';$total=0;$owned=filesize($dir.$incoming);
    foreach(glob($dir.'*.jpg')?:[]as $path)$total+=filesize($path);
    $q=$pdo->prepare('SELECT image_path FROM events WHERE organiser_id=? AND image_path IS NOT NULL');$q->execute([$owner]);
    foreach($q->fetchAll(PDO::FETCH_COLUMN)as $name)if($name!==$replaced && preg_match('/^[a-f0-9]{32}\.jpg$/D',$name) && is_file($dir.$name))$owned+=filesize($dir.$name);
    if($total>(int)($config['cover_total_bytes']??536870912)||$owned>(int)($config['cover_owner_bytes']??52428800))throw new DomainException('Cover storage limit reached. Use a template or contact the administrator.');
}
/** Caller holds the shared storage lock. A filename may still be referenced by legacy data. */
function removeUnusedCover(PDO $pdo, ?string $name): void {
    if(!$name || !preg_match('/^[a-f0-9]{32}\.jpg$/D',$name))return;
    $q=$pdo->prepare('SELECT 1 FROM events WHERE image_path=? LIMIT 1');$q->execute([$name]);
    $path=__DIR__.'/../storage/covers/'.$name;
    if(!$q->fetchColumn() && is_file($path) && !unlink($path))error_log('Fairboard: cover cleanup failed');
}
