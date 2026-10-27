<?php
require __DIR__.'/includes/bootstrap.php';requireLogin();
if(!in_array(currentUser()['role'],['creator','staff'],true))abortRequest(403,'Only authorised event staff can scan tickets.');
$message='';$success=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 $raw=trim(postString('token'));
 if(preg_match('/(?:#|&)token=(v1\.[0-9.]+\.[a-f0-9]{64})$/D',$raw,$m))$raw=$m[1];
 $retry=consumeAuthLimits($pdo,[authBucketKey('scan-user',(string)currentUser()['user_id'])=>300],60);
 if($retry){http_response_code(429);header('Retry-After: '.$retry);$message='Too many scans. Please wait a moment.';}
 else{
  $ticket=verifyTicketToken($raw);
  if(!$ticket)$message='Expired or invalid code. Ask the guest to refresh their ticket.';
  else try{
   $result=checkInBooking($pdo,$ticket['booking_id'],$ticket['event_id'],$raw);
   $success=$result==='success';
   $message=match($result){'success'=>'Welcome in! Booking #'.$ticket['booking_id'].' checked in.','used'=>'Already checked in. This ticket cannot be used twice.','missing'=>'This booking no longer exists.','closed'=>'Check-in is not open. It opens 2 hours before the event by default.',default=>'Expired code. Please scan the current ticket.'};
  }catch(BookingAccessDenied $e){http_response_code(403);$message='You are not assigned to this event.';}
 }
}
$pageTitle='Ticket scanner';require __DIR__.'/includes/header.php';?>
<div class="page-head"><div><p class="eyebrow">AT THE DOOR</p><h1>Welcome them in.</h1></div><span class="pill">Staff scanner</span></div>
<div class="two-column scanner-layout"><section class="panel"><h2>Scan an admission code</h2><p class="muted">Point the camera at the guest's live QR. Review the code, then confirm check-in.</p><video id="scanner-video" playsinline muted hidden></video><canvas id="scanner-canvas" hidden></canvas><div class="scanner-placeholder" data-scanner-placeholder><span aria-hidden="true">⌗</span><p>Ready when you are.</p></div><div class="form-actions"><button type="button" data-camera-start>Start camera</button><button type="button" class="secondary" data-camera-stop hidden>Stop camera</button></div><p class="fine-print" data-scanner-status role="status">Camera access needs HTTPS or localhost. You can also paste a ticket code.</p></section>
<section class="panel"><p class="eyebrow">CONFIRM ADMISSION</p><h2>Ticket check-in</h2><?php if($message):?><p class="flash <?=$success?'success':'error'?>" role="status"><?=e($message)?></p><?php endif;?><form method="post" id="scan-form"><?=csrfField()?><label for="ticket-token">Ticket code or QR link</label><textarea id="ticket-token" name="token" required maxlength="1000" rows="4" autocomplete="off"></textarea><p class="fine-print">Live codes expire after 30 seconds. Opening a QR link does not check anyone in.</p><button class="wide">Confirm check-in</button></form></section></div>
<script src="assets/vendor/jsQR.js" defer></script><script src="assets/scanner.js" defer></script>
<?php require __DIR__.'/includes/footer.php';?>
