<?php
require __DIR__.'/includes/bootstrap.php';$id=queryId();$q=$pdo->prepare("SELECT event_id FROM events WHERE event_id=? AND status='approved'");$q->execute([$id]);if(!$q->fetchColumn())abortRequest(404,'Event not found.');
header('Content-Type: image/svg+xml; charset=utf-8');echo qrSvg(rtrim($config['base_url'],'/').'/e.php?id='.$id);
