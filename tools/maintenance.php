<?php
// Daily CLI maintenance. Uses application credentials; never accepts a path or SQL from HTTP.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require($argv[1]??__DIR__.'/../config.php');
require __DIR__.'/../includes/cover_storage.php';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION];
if(!empty($config['db_ssl_ca'])){$options[PDO::MYSQL_ATTR_SSL_CA]=$config['db_ssl_ca'];$options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true;}
$pdo=new PDO('mysql:host='.$config['db_host'].';port='.($config['db_port']??3306).';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],$options);
$lock=coverStorageLock();
try {
    foreach(glob(__DIR__.'/../storage/covers/*.jpg')?:[]as $path)if(filemtime($path)<time()-86400)removeUnusedCover($pdo,basename($path));
}finally{flock($lock,LOCK_UN);fclose($lock);}
$pdo->exec('DELETE FROM account_tokens WHERE expires_at<NOW()');
$pdo->prepare('DELETE FROM auth_rate_limits WHERE window_started<?')->execute([time()-86400]);
// Development emails contain bearer links and must not accumulate indefinitely.
foreach(glob(__DIR__.'/../storage/mail/*.txt')?:[]as $file)if(filemtime($file)<time()-86400)unlink($file);
echo "Expired tokens, limits and unused covers cleaned.\n";
