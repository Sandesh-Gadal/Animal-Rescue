<?php
declare(strict_types=1);
$config=require __DIR__.'/config.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Kathmandu');
$key=(string)($_GET['key']??'');
$expected=(string)($config['security']['setup_key']??'');
if($expected==='' || $expected==='CHANGE_THIS_TO_A_LONG_RANDOM_SECRET' || !hash_equals($expected,$key)){
    http_response_code(403);exit('Invalid migration key.');
}
$dsn='mysql:host='.$config['db']['host'].';port='.(int)$config['db']['port'].';dbname='.$config['db']['name'].';charset='.$config['db']['charset'];
$db=new PDO($dsn,$config['db']['user'],$config['db']['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$steps=[];
$steps[]="ALTER TABLE body_reports MODIFY COLUMN status ENUM('new','verification_required','verified','confirmed','police_informed','team_dispatched','recovered','shifted','buried','identified','closed','false_report','invalid','duplicate','unable_to_locate') NOT NULL DEFAULT 'new', MODIFY COLUMN destination_type ENUM('hospital','mortuary','shelter','burial_site','other','none') NOT NULL DEFAULT 'none'";
$columns=[
"location_source ENUM('gps','map','manual','unknown') NOT NULL DEFAULT 'unknown' AFTER altitude",
"confirmed_at DATETIME NULL AFTER verification_notes",
"confirmed_by INT UNSIGNED NULL AFTER confirmed_at",
"police_informed TINYINT(1) NOT NULL DEFAULT 0 AFTER confirmed_by",
"police_informed_at DATETIME NULL AFTER police_informed",
"police_informed_source ENUM('reporter','admin','system','unknown') NOT NULL DEFAULT 'unknown' AFTER police_informed_at",
"police_contact_name VARCHAR(160) NULL AFTER police_unit",
"police_contact_phone VARCHAR(30) NULL AFTER police_contact_name",
"police_reference VARCHAR(120) NULL AFTER police_contact_phone",
"location_shared_with_police_at DATETIME NULL AFTER police_reference",
"rescue_team_name VARCHAR(180) NULL AFTER location_shared_with_police_at",
"rescue_team_contact VARCHAR(60) NULL AFTER rescue_team_name",
"team_dispatched_at DATETIME NULL AFTER rescue_team_contact",
"location_shared_with_team_at DATETIME NULL AFTER team_dispatched_at",
"buried_at DATETIME NULL AFTER muchulka_reference",
"burial_location VARCHAR(255) NULL AFTER buried_at",
"burial_latitude DECIMAL(10,7) NULL AFTER burial_location",
"burial_longitude DECIMAL(10,7) NULL AFTER burial_latitude",
"burial_by VARCHAR(180) NULL AFTER burial_longitude",
"burial_notes TEXT NULL AFTER burial_by",
"closure_reason VARCHAR(255) NULL AFTER identification_notes",
"false_report_details TEXT NULL AFTER closure_reason",
"last_edited_by INT UNSIGNED NULL AFTER closed_by"
];
$existing=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
$existing->execute([$config['db']['name'],'body_reports']);
$existingColumns=$existing->fetchAll(PDO::FETCH_COLUMN);
foreach($columns as $c){
    $name=strtok($c,' ');
    if(in_array($name,$existingColumns,true))continue;
    $steps[]='ALTER TABLE body_reports ADD COLUMN '.$c;
}
$steps[]="CREATE TABLE IF NOT EXISTS case_share_links (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,report_id BIGINT UNSIGNED NOT NULL,token_hash CHAR(64) NOT NULL UNIQUE,recipient_type ENUM('police','rescue_team','other') NOT NULL,recipient_name VARCHAR(180) NULL,recipient_contact VARCHAR(80) NULL,note VARCHAR(500) NULL,expires_at DATETIME NOT NULL,revoked_at DATETIME NULL,created_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,last_accessed_at DATETIME NULL,access_count INT UNSIGNED NOT NULL DEFAULT 0,INDEX idx_share_report (report_id),INDEX idx_share_expiry (expires_at),CONSTRAINT fk_share_report FOREIGN KEY (report_id) REFERENCES body_reports(id) ON DELETE CASCADE,CONSTRAINT fk_share_admin FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
try{
    foreach($steps as $sql)$db->exec($sql);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Dead Body Mapping v1.2 migration complete</h1><p>Database upgraded successfully.</p><p><strong>Delete migrate_v1_2.php from the server now.</strong></p><p><a href="admin">Open Admin Dashboard</a></p>';
}catch(Throwable $e){
    http_response_code(500);header('Content-Type: text/plain; charset=utf-8');
    echo "Migration failed:\n".$e->getMessage();
}
