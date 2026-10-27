<?php
// Test fixture bridge. CLI only; refuse non-development or non-test databases.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$c=require __DIR__.'/../config.php';if(($c['app_env']??'')!=='development'||!str_ends_with($c['db_name'],'_test'))exit("Refusing non-test database\n");
$pdo=new PDO('mysql:host='.$c['db_host'].';port='.$c['db_port'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$r=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
if(isset($r['session_id'])){
 if(!preg_match('/^[a-zA-Z0-9,-]{16,128}$/D',$r['session_id']))exit('Invalid test session');
 session_name('FAIRBOARDSESSID');session_id($r['session_id']);session_start();
 if(($r['expire']??'')==='idle')$_SESSION['last_activity']=time()-1801;
 if(($r['expire']??'')==='absolute'){$_SESSION['last_activity']=time();$_SESSION['authenticated_at']=time()-28801;}
 $state=['last_activity'=>$_SESSION['last_activity']??null];session_write_close();echo json_encode($state);exit;
}
if(isset($r['hash'])){echo json_encode(password_hash($r['hash'],PASSWORD_BCRYPT,['cost'=>12]));exit;}
$pdo->exec("SET time_zone='+08:00'");$q=$pdo->prepare($r['sql']);$q->execute($r['params']??[]);echo json_encode(['rows'=>$q->columnCount()?$q->fetchAll():[],'id'=>$pdo->lastInsertId(),'affected'=>$q->rowCount()],JSON_THROW_ON_ERROR);
