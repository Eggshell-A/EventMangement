<?php
require __DIR__.'/includes/bootstrap.php';requirePost();requireRole('admin');$action=postString('action');requireRecentAuthentication();enforceActionLimit($pdo,'admin-action',60);
if($action==='create_staff'||$action==='reset_staff_password'){
 require_once __DIR__.'/includes/staff_accounts.php';
 try{
  $credentials=$action==='create_staff'?createStaffAccount($pdo,postString('name'),postString('email')):resetStaffPassword($pdo,postPositiveInt('user_id'));
  require __DIR__.'/includes/staff_credentials.php';exit;
 }catch(DomainException $e){flash($e->getMessage(),'error');}
 redirect('admin.php');
}
if($action==='assign_staff'||$action==='unassign_staff'){
 $staff=postPositiveInt('staff_id');$creator=postPositiveInt('creator_id');
 $pdo->beginTransaction();
 $q=$pdo->prepare("SELECT user_id,role FROM users WHERE user_id IN (?,?) AND is_active=1 ORDER BY user_id FOR UPDATE");$q->execute([$staff,$creator]);$roles=array_column($q->fetchAll(),'role','user_id');
 if(($roles[$staff]??'')!=='staff'||($roles[$creator]??'')!=='creator'){$pdo->rollBack();abortRequest(400,'Choose an active staff member and creator.');}
 if($action==='assign_staff')$pdo->prepare('INSERT IGNORE INTO staff_assignments(staff_user_id,creator_user_id)VALUES(?,?)')->execute([$staff,$creator]);
 else $pdo->prepare('DELETE FROM staff_assignments WHERE staff_user_id=? AND creator_user_id=?')->execute([$staff,$creator]);
 audit($pdo,$action,$staff);$pdo->commit();flash('Staff assignment updated.');redirect('admin.php');
}
if($action==='approve_society'){
 $uid=postPositiveInt('user_id');$reference=trim(postString('reference'));if($reference===''||mb_strlen($reference)>120)abortRequest(400,'Enter a verification reference of up to 120 characters.');
 $pdo->beginTransaction();$q=$pdo->prepare("SELECT user_id FROM users WHERE user_id=? AND role='creator' AND is_school_email=1 AND school_email_verified=1 AND email_verified_at IS NOT NULL FOR UPDATE");$q->execute([$uid]);if(!$q->fetchColumn()){$pdo->rollBack();abortRequest(400,'The creator must verify ownership of an approved school email first.');}
 $pdo->prepare('UPDATE users SET verified_by=?,verified_at=NOW() WHERE user_id=?')->execute([currentUser()['user_id'],$uid]);$pdo->prepare("UPDATE creator_plans SET plan_code='verified_society',verification_reference=? WHERE user_id=?")->execute([$reference,$uid]);
 audit($pdo,'society.approve',$uid);notifyUser($pdo,$uid,'Your society has been approved. Future creator invoices are waived.');$pdo->commit();flash('Society approved.');redirect('admin.php');
}
if($action==='set_active'){
 $uid=postPositiveInt('user_id');$active=postString('active')==='1'?1:0;
 $pdo->beginTransaction();$q=$pdo->prepare("SELECT user_id FROM users WHERE user_id=? AND role<>'admin' AND deleted_at IS NULL FOR UPDATE");$q->execute([$uid]);if(!$q->fetchColumn()){$pdo->rollBack();abortRequest(403,'Admin accounts cannot be changed here.');}
 $pdo->prepare('UPDATE users SET is_active=?,auth_version=auth_version+1 WHERE user_id=?')->execute([$active,$uid]);$pdo->prepare('DELETE FROM account_tokens WHERE user_id=?')->execute([$uid]);audit($pdo,$active?'account.enable':'account.disable',$uid);$pdo->commit();flash('Account status updated. Existing sessions were invalidated.');redirect('admin.php');
}
$map=['approve'=>'approved','reject'=>'rejected','remove'=>'removed'];if(!isset($map[$action]))abortRequest(400,'Invalid action.');$id=postPositiveInt('event_id');
try{withEventLock($pdo,$id,function($event)use($pdo,$id,$action,$map){
 if(!$event)throw new BookingAccessDenied();
 if(in_array($action,['approve','reject'],true)&&$event['status']!=='pending')throw new DomainException('This event is no longer pending. Reload the queue.');
 if($action==='approve'&&strtotime($event['start_time'])<=time())throw new DomainException('Past events cannot be approved.');
 $pdo->prepare('UPDATE events SET status=? WHERE event_id=?')->execute([$map[$action],$id]);audit($pdo,'event.'.$action,$id);notifyUser($pdo,(int)$event['organiser_id'],'Event '.$map[$action].': '.$event['title'],$id);
 $q=$pdo->prepare('SELECT user_id FROM bookings WHERE event_id=? UNION SELECT user_id FROM waiting_list WHERE event_id=?');$q->execute([$id,$id]);foreach($q->fetchAll()as $u)notifyUser($pdo,(int)$u['user_id'],'Event '.$map[$action].': '.$event['title'],$id);
 if($action==='approve'){$event['status']='approved';promoteWaiting($pdo,$event,(int)$event['capacity']);}
 });flash('Event status updated.');}catch(BookingAccessDenied $e){abortRequest(404,'Event not found.');}catch(DomainException $e){flash($e->getMessage(),'error');}
redirect('admin.php');
