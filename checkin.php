<?php
require __DIR__.'/includes/bootstrap.php';requirePost();requireLogin();$bookingId=postPositiveInt('booking_id');$eventId=postPositiveInt('event_id');
try{$result=checkInBooking($pdo,$bookingId,$eventId);}catch(BookingAccessDenied $e){abortRequest(403,'You cannot check in this attendee.');}
$messages=['success'=>'Attendee checked in.','used'=>'This ticket has already been checked in.','missing'=>'Booking no longer exists.','closed'=>'Check-in is not open for this event.'];
flash($messages[$result]??'Ticket rejected.',$result==='success'?'success':'error');redirect('attendees.php?event_id='.$eventId);
