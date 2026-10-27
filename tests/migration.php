<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require __DIR__.'/../config.php';
if(($config['app_env']??'')!=='development'||!str_ends_with($config['db_name'],'_test'))exit("Refusing non-test database\n");
$pdo=new PDO('mysql:host='.$config['db_host'].';port='.$config['db_port'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='eventlink_migration_'.bin2hex(random_bytes(5)).'_test';$config['db_name']=$name;$tmp=tempnam(sys_get_temp_dir(),'fairboard-test-');
try{
 $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("USE `$name`");$pdo->exec(file_get_contents(__DIR__.'/fixtures/upstream.sql'));
 $pdo->exec("ALTER TABLE users MODIFY role ENUM('user','creator','staff','admin','attendee','organiser') NOT NULL DEFAULT 'user'");
 $pdo->exec("INSERT INTO users(name,email,password_hash,role)VALUES('Existing creator','existing-creator@example.test','not-a-login-hash','organiser'),('Existing attendee','existing-user@example.test','not-a-login-hash','attendee')");
 $pdo->exec("INSERT INTO events(organiser_id,title,description,start_time,location,capacity,status)VALUES(1,'Existing event','Keep this event',DATE_ADD(NOW(),INTERVAL 7 DAY),'Campus',5,'approved')");$pdo->exec('INSERT INTO bookings(event_id,user_id)VALUES(1,2)');
 file_put_contents($tmp,"<?php return ".var_export($config,true).";\n");chmod($tmp,0600);
 for($i=0;$i<2;$i++){$process=proc_open([PHP_BINARY,'-c',php_ini_loaded_file()?:'',__DIR__.'/../tools/migrate.php',$tmp],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException($out.$err);}
 foreach(['users'=>2,'events'=>1,'bookings'=>1,'creator_plans'=>1]as $table=>$expected){$count=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();if($count!==$expected)throw new RuntimeException("Unexpected $table count");}
 if($pdo->query('SELECT description FROM events WHERE event_id=1')->fetchColumn()!=='Keep this event')throw new RuntimeException('Existing event was changed');
 if($pdo->query('SELECT role FROM users WHERE user_id=1')->fetchColumn()!=='creator'||$pdo->query('SELECT role FROM users WHERE user_id=2')->fetchColumn()!=='user')throw new RuntimeException('Legacy roles were not mapped');
 foreach(['account_tokens','audit_log','creator_invoices','notifications','auth_rate_limits']as $table)$pdo->query("SELECT COUNT(*) FROM `$table`");
 echo "PASS: upstream database migrated twice; users, event and booking preserved; legacy roles mapped; creator plan backfilled; release tables present.\n";
 $pdo->exec("DROP DATABASE `$name`");$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("USE `$name`");$pdo->exec(file_get_contents(__DIR__.'/../database.sql'));
 if((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchColumn()!==12)throw new RuntimeException('Fresh schema table count mismatch');
 echo "PASS: fresh schema imports with all 12 application tables.\n";
}finally{$pdo->exec("DROP DATABASE IF EXISTS `$name`");if(is_file($tmp))unlink($tmp);}
