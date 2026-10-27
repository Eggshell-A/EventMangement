<?php
require __DIR__.'/includes/bootstrap.php';requirePost();requireRole('user');$id=postPositiveInt('event_id');
withEventLock($pdo,$id,function($event)use($pdo,$id){$pdo->prepare('DELETE FROM waiting_list WHERE event_id=? AND user_id=?')->execute([$id,currentUser()['user_id']]);});
flash('You have left the waiting list.');redirect('my_bookings.php');
