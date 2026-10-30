<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$config=require __DIR__.'/../config.php';
date_default_timezone_set($config['timezone']??'Asia/Hong_Kong');
if(($config['app_env']??'')!=='development'||!str_ends_with($config['db_name'],'_test'))exit("Refusing non-test database\n");
$pdo=new PDO('mysql:host='.$config['db_host'].';port='.$config['db_port'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$offset=(new DateTimeImmutable())->format('P');$pdo->exec('SET time_zone = '.$pdo->quote($offset));
$name='eventlink_migration_'.bin2hex(random_bytes(5)).'_test';$config['db_name']=$name;$tmp=tempnam(sys_get_temp_dir(),'fairboard-test-');
try{
 $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("USE `$name`");$pdo->exec(file_get_contents(__DIR__.'/fixtures/upstream.sql'));
 $pdo->exec("ALTER TABLE users MODIFY role ENUM('user','creator','staff','admin','attendee','organiser') NOT NULL DEFAULT 'user'");
 $pdo->exec("INSERT INTO users(name,email,password_hash,role)VALUES('Existing creator','existing-creator@example.test','not-a-login-hash','organiser'),('Existing attendee','existing-user@example.test','not-a-login-hash','attendee')");
 $pdo->exec("INSERT INTO events(organiser_id,title,description,start_time,location,capacity,status)VALUES(1,'Existing event','Keep this event',DATE_ADD(NOW(),INTERVAL 7 DAY),'Campus',5,'approved')");$pdo->exec('INSERT INTO bookings(event_id,user_id)VALUES(1,2)');
 file_put_contents($tmp,"<?php return ".var_export($config,true).";\n");chmod($tmp,0600);
 $migrate=function()use($tmp){for($i=0;$i<2;$i++){$process=proc_open([PHP_BINARY,'-c',php_ini_loaded_file()?:'',__DIR__.'/../tools/migrate.php',$tmp],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException($out.$err);}};$migrate();
 foreach(['users'=>2,'events'=>1,'bookings'=>1,'creator_plans'=>1]as $table=>$expected){$count=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();if($count!==$expected)throw new RuntimeException("Unexpected $table count");}
 if($pdo->query('SELECT description FROM events WHERE event_id=1')->fetchColumn()!=='Keep this event')throw new RuntimeException('Existing event was changed');
 if($pdo->query('SELECT role FROM users WHERE user_id=1')->fetchColumn()!=='creator'||$pdo->query('SELECT role FROM users WHERE user_id=2')->fetchColumn()!=='user')throw new RuntimeException('Legacy roles were not mapped');
 foreach(['account_tokens','audit_log','creator_invoices','notifications','auth_rate_limits','user_mfa']as $table)$pdo->query("SELECT COUNT(*) FROM `$table`");
 echo "PASS: upstream database migrated twice; users, event and booking preserved; legacy roles mapped; creator plan backfilled; release tables present.\n";
 $pdo->exec("DROP DATABASE `$name`");$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("USE `$name`");$pdo->exec(file_get_contents(__DIR__.'/fixtures/upstream-redesign.sql'));
 $pdo->exec("INSERT INTO users(name,email,password_hash,role)VALUES('Redesign creator','creator@example.test','not-a-login-hash','creator'),('Attendee','attendee@example.test','not-a-login-hash','user')");
 $pdo->exec("INSERT INTO events(organiser_id,title,description,start_time,location,capacity,original_capacity,refill_count,category,poster_path,social_url,status)VALUES(1,'Draft','Preserve draft',DATE_ADD(NOW(),INTERVAL 7 DAY),'Campus',120,100,7,'learning','uploads/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png','https://example.org/event','draft'),(1,'Cancelled','Preserve cancellation',DATE_ADD(NOW(),INTERVAL 7 DAY),'Campus',50,50,3,'sports',NULL,NULL,'cancelled')");
 $pdo->exec('INSERT INTO bookings(event_id,user_id)VALUES(1,2)');$migrate();
 $row=$pdo->query('SELECT * FROM events WHERE event_id=1')->fetch(PDO::FETCH_ASSOC);
 if($row['status']!=='draft'||$row['category']!=='Learning'||(int)$row['original_capacity']!==100||(int)$row['refill_count']!==7||$row['poster_path']!=='uploads/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png'||$row['social_url']!=='https://example.org/event')throw new RuntimeException('Redesign metadata changed');
 if($pdo->query('SELECT status FROM events WHERE event_id=2')->fetchColumn()!=='cancelled'||(int)$pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn()!==1)throw new RuntimeException('Redesign status/bookings lost');
 echo "PASS: latest team redesign schema migrated twice; drafts, cancellation, categories, original capacity, refill count, posters, social links and bookings preserved.\n";
 $pdo->exec("DROP DATABASE `$name`");$pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("USE `$name`");$pdo->exec(file_get_contents(__DIR__.'/../database.sql'));
 if((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()")->fetchColumn()!==13)throw new RuntimeException('Fresh schema table count mismatch');
 echo "PASS: fresh schema imports with all 13 application tables.\n";
 $pdo->exec("INSERT INTO users(name,email,password_hash,role)VALUES('Existing staff','staff@eventlink.invalid','keep-existing-hash','staff')");
 $pdo->exec("INSERT INTO users(name,email,password_hash,role)VALUES('Pending attendee','pending@example.test','keep-pending-hash','user')");
 $pendingUser=(int)$pdo->lastInsertId();
 $pdo->exec("INSERT INTO user_mfa(user_id,secret_cipher,recovery_hashes)VALUES(1,'keep-existing-encrypted-secret','[]')");
 $pdo->exec("INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at)VALUES(1,'reset',REPEAT('a',64),DATE_ADD(NOW(),INTERVAL 1 DAY))");
 $pdo->prepare("INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at)VALUES(?,'verify',REPEAT('c',64),DATE_ADD(NOW(),INTERVAL 1 DAY))")->execute([$pendingUser]);
 $pdo->exec("CREATE TABLE staff_invites(invite_id INT PRIMARY KEY,token_hash CHAR(64)); INSERT INTO staff_invites VALUES(1,REPEAT('b',64))");
 $migrate();
 if((int)$pdo->query('SELECT COUNT(*) FROM staff_invites')->fetchColumn()!==0||(int)$pdo->query('SELECT COUNT(*) FROM account_tokens')->fetchColumn()!==0)throw new RuntimeException('Legacy staff links not retired');
 if((int)$pdo->query("SELECT COUNT(*) FROM account_tokens WHERE purpose='verify'")->fetchColumn()!==0)throw new RuntimeException('Legacy 24-hour verification token was not retired');
 $pdo->prepare("INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at)VALUES(?,'verify',REPEAT('d',64),DATE_ADD(NOW(),INTERVAL 9 MINUTE))")->execute([$pendingUser]);
 $migrate();
 if((int)$pdo->query("SELECT COUNT(*) FROM account_tokens WHERE purpose='verify'")->fetchColumn()!==1)throw new RuntimeException('Repeat migration removed a new short-lived verification token');
 if($pdo->query('SELECT password_hash FROM users WHERE user_id=1')->fetchColumn()!=='keep-existing-hash'||$pdo->query('SELECT secret_cipher FROM user_mfa WHERE user_id=1')->fetchColumn()!=='keep-existing-encrypted-secret'||(int)$pdo->query('SELECT must_change_password FROM users WHERE user_id=1')->fetchColumn()!==0)throw new RuntimeException('Existing staff credentials changed');
 echo "PASS: invitation-release migration repeated safely; legacy verification links retired; new short-lived links preserved; existing staff passwords and MFA preserved.\n";
}finally{$pdo->exec("DROP DATABASE IF EXISTS `$name`");if(is_file($tmp))unlink($tmp);}
