<?php
require __DIR__.'/includes/bootstrap.php';requirePost();requireRole('creator');$id=postPositiveInt('event_id');$action=postString('action');
if(!in_array($action,['add_places','cancel'],true))abortRequest(400,'Invalid action.');
enforceActionLimit($pdo,'organiser-change',30);
$number=$action==='add_places'?postPositiveInt('add_places'):0;
try{
 withEventLock($pdo,$id,function($event)use($pdo,$id,$action,$number){
  if(!$event||!isEventOwnerOrAdmin($event))throw new BookingAccessDenied();
  if(strtotime($event['start_time'])<=time())throw new DomainException('Past events cannot be changed.');
  if(in_array($event['status'],['cancelled','removed'],true))throw new DomainException('This event is already closed.');
  if($action==='add_places'){
   if((int)$event['capacity']+$number>100000)throw new DomainException('Total capacity cannot exceed 100,000.');
   $pdo->prepare('UPDATE events SET capacity=capacity+? WHERE event_id=?')->execute([$number,$id]);$event['capacity']+=$number;
   $moved=promoteWaiting($pdo,$event,$number);audit($pdo,'event.refill',$id);flash($number.' places added; '.$moved.' waiting attendees promoted.');
  }else{
   $pdo->prepare("UPDATE events SET status='cancelled' WHERE event_id=?")->execute([$id]);
   $q=$pdo->prepare('SELECT user_id FROM bookings WHERE event_id=? UNION SELECT user_id FROM waiting_list WHERE event_id=?');$q->execute([$id,$id]);foreach($q->fetchAll()as $u)notifyUser($pdo,(int)$u['user_id'],'Event cancelled: '.$event['title'],$id);
   audit($pdo,'event.cancel',$id);flash('Event cancelled. Attendees have been notified.');
  }
 });
}catch(BookingAccessDenied $e){abortRequest(403,'Event not found.');}catch(DomainException $e){flash($e->getMessage(),'error');}
redirect('dashboard.php');
