<?php
require __DIR__.'/includes/bootstrap.php';
$legacy=isset($_GET['poster']);$name=queryString($legacy?'poster':'image');
$pattern=$legacy?'/^[a-f0-9]{32}\.(jpg|png|webp)$/D':'/^[a-f0-9]{32}\.jpg$/D';
if(!preg_match($pattern,$name))abortRequest(404,'Image not found.');
$q=$pdo->prepare($legacy?'SELECT * FROM events WHERE poster_path=? LIMIT 1':'SELECT * FROM events WHERE image_path=? LIMIT 1');
$q->execute([$legacy?'uploads/'.$name:$name]);$event=$q->fetch();
if(!$event)abortRequest(404,'Image not found.');
$allowed=eventIsAccessible($event)||canEditEvent($pdo,$event);
if(!$allowed&&isLoggedIn()){
 $q=$pdo->prepare('SELECT 1 FROM bookings WHERE event_id=? AND user_id=? UNION SELECT 1 FROM waiting_list WHERE event_id=? AND user_id=?');
 $q->execute([$event['event_id'],currentUser()['user_id'],$event['event_id'],currentUser()['user_id']]);$allowed=(bool)$q->fetchColumn();
}
if(!$allowed)abortRequest(404,'Image not found.');
$path=__DIR__.($legacy?'/uploads/':'/storage/covers/').$name;
if(!is_file($path)||filesize($path)>5*1024*1024)abortRequest(404,'Image not found.');
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||!getimagesize($path))abortRequest(404,'Image not found.');
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));readfile($path);
