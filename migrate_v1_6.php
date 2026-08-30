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

$existing=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
$existing->execute([$config['db']['name'],'body_reports']);
$existingColumns=$existing->fetchAll(PDO::FETCH_COLUMN);

if(!in_array('submitted_by',$existingColumns,true)){
    $steps[]='ALTER TABLE body_reports ADD COLUMN submitted_by INT UNSIGNED NULL AFTER reporter_private';
}

$idx=$db->prepare('SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=?');
$idx->execute([$config['db']['name'],'body_reports','idx_submitted_by']);
if(!$idx->fetchColumn()){
    $steps[]='ALTER TABLE body_reports ADD INDEX idx_submitted_by (submitted_by)';
}

$fk=$db->prepare('SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME=?');
$fk->execute([$config['db']['name'],'body_reports','fk_submitted_by']);
if(!$fk->fetchColumn()){
    $steps[]='ALTER TABLE body_reports ADD CONSTRAINT fk_submitted_by FOREIGN KEY (submitted_by) REFERENCES admin_users(id) ON DELETE SET NULL';
}

$columns=[
"observed_at DATETIME NULL AFTER nearby_objects",
"weather_condition ENUM('clear','raining','extreme_heat') NULL AFTER observed_at",
"animal_species ENUM('cow_ox','buffalo','goat_sheep','pig_boar','chicken_duck','other') NULL AFTER weather_condition",
"animal_species_other VARCHAR(120) NULL AFTER animal_species",
"estimated_size ENUM('large','medium','small') NULL AFTER animal_species_other",
"carcass_count SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER estimated_size",
"decomposition_state ENUM('fresh','decomposing','fully_decomposed','mutilated') NULL AFTER carcass_count",
"distance_water_source ENUM('under_50m','50_to_200m','over_200m') NULL AFTER decomposition_state",
"distance_settlement ENUM('under_50m','100_to_500m','over_500m') NULL AFTER distance_water_source",
"disposal_method ENUM('trench_burial','offsite_transport','other_approved') NULL AFTER distance_settlement",
"disposal_method_notes VARCHAR(255) NULL AFTER disposal_method",
"equipment_needed VARCHAR(255) NULL AFTER disposal_method_notes",
"equipment_needed_other VARCHAR(120) NULL AFTER equipment_needed",
"disinfection_materials VARCHAR(255) NULL AFTER equipment_needed_other",
];
foreach($columns as $c){
    $name=strtok($c,' ');
    if(in_array($name,$existingColumns,true))continue;
    $steps[]='ALTER TABLE body_reports ADD COLUMN '.$c;
}

try{
    foreach($steps as $sql)$db->exec($sql);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Dead Body Mapping v1.6 migration complete</h1><p>Database upgraded successfully.</p><p><strong>Delete migrate_v1_6.php from the server now.</strong></p><p><a href="admin">Open Admin Dashboard</a></p>';
}catch(Throwable $e){
    http_response_code(500);header('Content-Type: text/plain; charset=utf-8');
    echo "Migration failed:\n".$e->getMessage();
}
