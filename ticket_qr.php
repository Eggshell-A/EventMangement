<?php
require __DIR__.'/includes/bootstrap.php';requirePost();requireRole('user');$bookingId=postPositiveInt('booking_id');
$q=$pdo->prepare("SELECT b.*,e.status,e.start_time FROM bookings b JOIN events e ON e.event_id=b.event_id WHERE b.booking_id=? AND b.user_id=?");$q->execute([$bookingId,currentUser()['user_id']]);$booking=$q->fetch();
if(!$booking)abortRequest(404,'Ticket not found.');
if($booking['status']!=='approved'||$booking['checked_in']||time()>strtotime($booking['start_time'])+(int)($config['checkin_after_seconds']??43200))abortRequest(409,'This ticket is no longer active.');
$now=time();$token=ticketToken($bookingId,(int)$booking['event_id'],$now);
// A fragment keeps the ticket token out of access logs and referrer headers.
$url=rtrim($config['base_url'],'/').'/scan.php#token='.$token;
$svg=qrSvg($url);header('Content-Type: application/json; charset=utf-8');
echo json_encode(['image'=>'data:image/svg+xml;base64,'.base64_encode($svg),'token'=>$token,'server_time'=>$now,'expires_at'=>(intdiv($now,TICKET_INTERVAL)+1)*TICKET_INTERVAL],JSON_THROW_ON_ERROR);
