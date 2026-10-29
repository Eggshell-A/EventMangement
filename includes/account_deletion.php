<?php
declare(strict_types=1);
/** Erase account identity. Anonymous historic event/attendance/invoice references survive. */
function deleteOwnAccount(PDO $pdo, int $uid, int $version): void {
    if ($uid !== (int)currentUser()['user_id']) throw new DomainException('You can delete only your own account.');
    // Event locks precede the user lock, matching booking writers. Recheck the set after locking.
    $eventIds=function()use($pdo,$uid):array{
        $q=$pdo->prepare('SELECT event_id FROM bookings WHERE user_id=? UNION SELECT event_id FROM waiting_list WHERE user_id=? UNION SELECT event_id FROM events WHERE organiser_id=? ORDER BY event_id');$q->execute([$uid,$uid,$uid]);return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    };
    $ids=$eventIds();$pdo->beginTransaction();
    try {
        $events=[];
        foreach($ids as $id){$q=$pdo->prepare('SELECT * FROM events WHERE event_id=? FOR UPDATE');$q->execute([$id]);if($event=$q->fetch())$events[$id]=$event;}
        $q=$pdo->prepare('SELECT * FROM users WHERE user_id=? AND is_active=1 FOR UPDATE');$q->execute([$uid]);$user=$q->fetch();
        if(!$user || (int)$user['auth_version']!==$version)throw new DomainException('Your account changed. Log in again.');
        if($user['role']==='admin')throw new DomainException('Admin accounts need an administrator handover before deletion.');
        // Locking reads avoid a repeatable-read snapshot hiding a concurrent booking.
        $q=$pdo->prepare('SELECT event_id FROM bookings WHERE user_id=? FOR UPDATE');$q->execute([$uid]);$current=$q->fetchAll(PDO::FETCH_COLUMN);
        $q=$pdo->prepare('SELECT event_id FROM waiting_list WHERE user_id=? FOR UPDATE');$q->execute([$uid]);$current=array_merge($current,$q->fetchAll(PDO::FETCH_COLUMN));
        $q=$pdo->prepare('SELECT event_id FROM events WHERE organiser_id=? FOR UPDATE');$q->execute([$uid]);$current=array_merge($current,$q->fetchAll(PDO::FETCH_COLUMN));
        if(array_diff(array_map('intval',$current),$ids))throw new DomainException('A booking or event just changed. Please try again.');
        foreach($events as $event)if((int)$event['organiser_id']===$uid && strtotime($event['start_time'])>time() && !in_array($event['status'],['cancelled','removed'],true))throw new DomainException('Cancel your upcoming events before deleting your account.');
        $pdo->prepare("UPDATE users SET name='Deleted account',email=?,password_hash=?,is_active=0,deleted_at=NOW(),auth_version=auth_version+1,is_school_email=0,school_email_verified=0,email_verified_at=NULL,verified_at=NULL,verified_by=NULL WHERE user_id=?")->execute(['deleted-'.bin2hex(random_bytes(16)).'@invalid.local',password_hash(bin2hex(random_bytes(32)),PASSWORD_BCRYPT,['cost'=>12]),$uid]);
        $pdo->prepare('DELETE FROM waiting_list WHERE user_id=?')->execute([$uid]);
        foreach($events as $event)if(strtotime($event['start_time'])>time()){
            $q=$pdo->prepare('DELETE FROM bookings WHERE event_id=? AND user_id=?');$q->execute([$event['event_id'],$uid]);
            if($q->rowCount())promoteWaiting($pdo,$event,1);
        }
        foreach(['user_mfa','account_tokens','notifications']as $table)$pdo->prepare("DELETE FROM $table WHERE user_id=?")->execute([$uid]);
        $pdo->prepare('DELETE FROM staff_assignments WHERE staff_user_id=? OR creator_user_id=?')->execute([$uid,$uid]);
        $pdo->prepare("UPDATE creator_plans SET organisation_name='Deleted organisation',verification_reference=NULL WHERE user_id=?")->execute([$uid]);
        $pdo->prepare('UPDATE creator_invoices SET reference=NULL WHERE user_id=?')->execute([$uid]);
        audit($pdo,'account.deleted',$uid);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
