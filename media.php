<?php
require __DIR__.'/includes/bootstrap.php';$name=queryString('image');if(!preg_match('/^[a-f0-9]{32}\.jpg$/D',$name))abortRequest(404,'Image not found.');$q=$pdo->prepare('SELECT * FROM events WHERE image_path=? LIMIT 1');$q->execute([$name]);$event=$q->fetch();if(!$event)abortRequest(404,'Image not found.');
$allowed=eventIsAccessible($event)||canEditEvent($pdo,$event);
if(!$allowed&&isLoggedIn()){$q=$pdo->prepare('SELECT 1 FROM bookings WHERE event_id=? AND user_id=? UNION SELECT 1 FROM waiting_list WHERE event_id=? AND user_id=?');$q->execute([$event['event_id'],currentUser()['user_id'],$event['event_id'],currentUser()['user_id']]);$allowed=(bool)$q->fetchColumn();}
if(!$allowed)abortRequest(404,'Image not found.');$path=__DIR__.'/storage/covers/'.$name;if(!is_file($path))abortRequest(404,'Image not found.');header('Content-Type: image/jpeg');header('Content-Length: '.filesize($path));readfile($path);
