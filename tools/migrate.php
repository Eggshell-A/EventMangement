<?php
// CLI-only, additive, repeatable migration. Back up the database first.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config=require ($argv[1] ?? __DIR__.'/../config.php');
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION];
if (!empty($config['db_ssl_ca'])) {
    if (!is_readable($config['db_ssl_ca'])) throw new RuntimeException('SSL CA is unreadable');
    $options[PDO::MYSQL_ATTR_SSL_CA]=$config['db_ssl_ca'];
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true;
}
$pdo=new PDO('mysql:host='.$config['db_host'].';port='.($config['db_port']?:3306).';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_pass'],$options);
$column=function(string $table,string $column,string $definition)use($pdo):void {
    $q=$pdo->prepare('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute([$table,$column]);
    if (!$q->fetchColumn()) $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
};
$pdo->exec(file_get_contents(__DIR__.'/../database/migrate_week12.sql'));
foreach(['is_school_email'=>'TINYINT(1) NOT NULL DEFAULT 0','school_email_verified'=>'TINYINT(1) NOT NULL DEFAULT 0','verified_at'=>'DATETIME NULL','verified_by'=>'INT UNSIGNED NULL','email_verified_at'=>'DATETIME NULL','is_active'=>'TINYINT(1) NOT NULL DEFAULT 1','auth_version'=>'INT UNSIGNED NOT NULL DEFAULT 1']as $name=>$def)$column('users',$name,$def);
foreach(['category'=>"VARCHAR(32) NOT NULL DEFAULT 'Community'",'artwork'=>"VARCHAR(32) NOT NULL DEFAULT 'city'",'image_path'=>'VARCHAR(80) NULL']as $name=>$def)$column('events',$name,$def);
$column('bookings','checked_in_at','DATETIME NULL');$column('bookings','checked_in_by','INT UNSIGNED NULL');
$pdo->exec("CREATE TABLE IF NOT EXISTS creator_plans (user_id INT UNSIGNED PRIMARY KEY,organisation_name VARCHAR(120) NOT NULL,plan_code ENUM('verified_society','external_monthly') NOT NULL,FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE RESTRICT) ENGINE=InnoDB");
$column('creator_plans','verification_reference','VARCHAR(120) NULL');
$pdo->exec("INSERT IGNORE INTO creator_plans(user_id,organisation_name,plan_code) SELECT user_id,name,'external_monthly' FROM users WHERE role='creator'");
$pdo->exec(file_get_contents(__DIR__.'/../database/release_tables.sql'));
echo "Migration complete. Existing users, events and bookings preserved.\n";
