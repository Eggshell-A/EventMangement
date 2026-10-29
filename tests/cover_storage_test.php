<?php
if(PHP_SAPI!=='cli')exit;
$config=require __DIR__.'/../config.php';
if(($config['app_env']??'')!=='development'||!str_ends_with($config['db_name'],'_test'))exit("Refusing non-test database\n");
require __DIR__.'/../includes/cover_storage.php';
$pdo=new PDO('mysql:host='.$config['db_host'].';port='.$config['db_port'].';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$uid=0;$eid=0;$lock=coverStorageLock();$dir=__DIR__.'/../storage/covers/';$old=bin2hex(random_bytes(16)).'.jpg';$incoming=bin2hex(random_bytes(16)).'.jpg';$count=0;
function checkCover(bool $ok,string $label):void{global $count;if(!$ok)throw new RuntimeException($label);$count++;echo "PASS $label\n";}
try{
 $pdo->prepare("INSERT INTO users(name,email,password_hash,role)VALUES('Quota test',?,'not-a-login','creator')")->execute([bin2hex(random_bytes(8)).'@quota.test']);$uid=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO events(organiser_id,title,description,start_time,location,capacity,image_path)VALUES(?,'Quota','Test',NOW(),'Test',1,?)")->execute([$uid,$old]);$eid=(int)$pdo->lastInsertId();
 file_put_contents($dir.$old,str_repeat('x',100));file_put_contents($dir.$incoming,str_repeat('y',60));
 $config['cover_owner_bytes']=150;$config['cover_total_bytes']=PHP_INT_MAX;
 $rejected=false;try{checkCoverQuota($pdo,$uid,$incoming,null);}catch(DomainException $e){$rejected=true;}checkCover($rejected,'owner storage quota enforced');
 checkCoverQuota($pdo,$uid,$incoming,$old);checkCover(true,'replacement releases owner quota');
 $config['cover_total_bytes']=1;$rejected=false;try{checkCoverQuota($pdo,$uid,$incoming,$old);}catch(DomainException $e){$rejected=true;}checkCover($rejected,'global storage quota includes orphan files');
 removeUnusedCover($pdo,$old);checkCover(is_file($dir.$old),'referenced cover preserved');
 removeUnusedCover($pdo,$incoming);checkCover(!is_file($dir.$incoming),'orphan cover removed');
 removeUnusedCover($pdo,'../../config.php');checkCover(is_file(__DIR__.'/../config.php'),'cleanup traversal refused');
 echo "$count cover storage assertions passed.\n";
}finally{
 if($eid)$pdo->prepare('DELETE FROM events WHERE event_id=?')->execute([$eid]);
 if($uid)$pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$uid]);
 foreach([$old,$incoming]as $name)if(is_file($dir.$name))unlink($dir.$name);
 flock($lock,LOCK_UN);fclose($lock);
}
