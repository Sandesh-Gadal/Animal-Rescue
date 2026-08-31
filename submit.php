<?php
require __DIR__.'/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { header('Location: report'); exit; }
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    $maxMb=(int)($config['security']['max_upload_mb'] ?? 8);
    exit('Your photos were too large to upload together. Please try again with fewer photos, or photos under '.$maxMb.'MB each.');
}
csrf_check();

$recent=$db->prepare("SELECT COUNT(*) FROM body_reports WHERE client_ip_hash=? AND created_at >= (NOW() - INTERVAL 10 MINUTE)");
$recent->execute([ip_hash()]);
if((int)$recent->fetchColumn() >= 10){http_response_code(429);exit('Too many recent reports from this connection. Please contact the responsible authority if this is urgent.');}

$type=$_POST['body_type'] ?? '';
if (!in_array($type,['human','animal','unsure'],true)) exit('Invalid body type.');
$lat=filter_var($_POST['latitude'] ?? null,FILTER_VALIDATE_FLOAT);
$lng=filter_var($_POST['longitude'] ?? null,FILTER_VALIDATE_FLOAT);
if ($lat===false || $lng===false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) exit('Valid GPS or map coordinates are required.');
$name=trim((string)($_POST['reporter_name'] ?? ''));
$phone=trim((string)($_POST['reporter_phone'] ?? ''));
if ($name==='' || mb_strlen($name)>120 || !validate_phone($phone)) exit('Valid name and phone are required.');
$locationSource=(string)($_POST['location_source'] ?? 'unknown');
if(!in_array($locationSource,['gps','map','manual','unknown'],true)) $locationSource='unknown';
$policeInformed=$type==='human' && isset($_POST['police_informed']);
$policeUnit=mb_substr(trim((string)($_POST['police_unit'] ?? '')),0,160);
$policeRef=mb_substr(trim((string)($_POST['police_reference'] ?? '')),0,120);

$isAnimal=$type==='animal';
$observedAt=trim((string)($_POST['observed_at'] ?? ''));
$observedAt=$observedAt!==''?str_replace('T',' ',substr($observedAt,0,16)).':00':null;

$weather=(string)($_POST['weather_condition'] ?? '');
if(!array_key_exists($weather,weather_condition_labels())) $weather=null;

$species=(string)($_POST['animal_species'] ?? '');
if(!array_key_exists($species,animal_species_labels())) $species=null;
$speciesOther=$species==='other'?mb_substr(trim((string)($_POST['animal_species_other'] ?? '')),0,120):'';

$size=(string)($_POST['estimated_size'] ?? '');
if(!array_key_exists($size,estimated_size_labels())) $size=null;

$carcassCount=filter_var($_POST['carcass_count'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>32767]]);
if($carcassCount===false) $carcassCount=1;

$decomp=(string)($_POST['decomposition_state'] ?? '');
if(!array_key_exists($decomp,decomposition_state_labels())) $decomp=null;

$distWater=(string)($_POST['distance_water_source'] ?? '');
if(!array_key_exists($distWater,distance_water_source_labels())) $distWater=null;

$distSettlement=(string)($_POST['distance_settlement'] ?? '');
if(!array_key_exists($distSettlement,distance_settlement_labels())) $distSettlement=null;

$disposal=(string)($_POST['disposal_method'] ?? '');
if(!array_key_exists($disposal,disposal_method_labels())) $disposal=null;
$disposalNotes=$disposal==='other_approved'?mb_substr(trim((string)($_POST['disposal_method_notes'] ?? '')),0,255):'';

$equipmentPosted=$_POST['equipment_needed'] ?? [];
$equipment=is_array($equipmentPosted)?array_values(array_intersect($equipmentPosted,array_keys(equipment_needed_labels()))):[];
$equipmentOther=mb_substr(trim((string)($_POST['equipment_needed_other'] ?? '')),0,120);

$disinfectPosted=$_POST['disinfection_materials'] ?? [];
$disinfect=is_array($disinfectPosted)?array_values(array_intersect($disinfectPosted,array_keys(disinfection_materials_labels()))):[];

try {
    $db->beginTransaction();
    $publicId=generate_public_id($db);
    $stmt=$db->prepare("INSERT INTO body_reports
      (public_id,body_type,latitude,longitude,gps_accuracy,altitude,location_source,location_text,landmark,description,
       reporter_name,reporter_phone,alternate_phone,reporter_organization,reporter_private,submitted_by,
       police_informed,police_informed_at,police_informed_source,police_unit,police_reference,
       observed_at,weather_condition,animal_species,animal_species_other,estimated_size,carcass_count,
       decomposition_state,distance_water_source,distance_settlement,disposal_method,disposal_method_notes,
       equipment_needed,equipment_needed_other,disinfection_materials,
       client_ip_hash,user_agent)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
      $publicId,$type,$lat,$lng,
      ($_POST['gps_accuracy'] ?? '')!==''?(float)$_POST['gps_accuracy']:null,
      ($_POST['altitude'] ?? '')!==''?(float)$_POST['altitude']:null,
      $locationSource,
      mb_substr(trim((string)($_POST['location_text'] ?? '')),0,255),
      mb_substr(trim((string)($_POST['landmark'] ?? '')),0,255),
      mb_substr(trim((string)($_POST['description'] ?? '')),0,3000),
      $name,$phone,
      mb_substr(trim((string)($_POST['alternate_phone'] ?? '')),0,30),
      mb_substr(trim((string)($_POST['reporter_organization'] ?? '')),0,160),
      isset($_POST['reporter_private'])?1:0,
      admin_user()['id'] ?? null,
      $policeInformed?1:0,
      $policeInformed?date('Y-m-d H:i:s'):null,
      $policeInformed?'reporter':'unknown',
      $policeUnit,
      $policeRef,
      $isAnimal?$observedAt:null,
      $isAnimal?$weather:null,
      $isAnimal?$species:null,
      $isAnimal?$speciesOther:null,
      $isAnimal?$size:null,
      $isAnimal?$carcassCount:1,
      $isAnimal?$decomp:null,
      $isAnimal?$distWater:null,
      $isAnimal?$distSettlement:null,
      $isAnimal?$disposal:null,
      $isAnimal?$disposalNotes:null,
      $isAnimal?(implode(',',$equipment)?:null):null,
      $isAnimal?($equipmentOther?:null):null,
      $isAnimal?(implode(',',$disinfect)?:null):null,
      ip_hash(),
      mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,255)
    ]);
    $reportId=(int)$db->lastInsertId();
    upload_photos($db,$reportId,$config);
    $h=$db->prepare("INSERT INTO status_history(report_id,old_status,new_status,note) VALUES(?,NULL,'new',?)");
    $note='Public report submitted; location source: '.$locationSource;
    if($policeInformed) $note.='; reporter stated Nepal Police was informed';
    $h->execute([$reportId,$note]);
    $db->commit();
    header('Location: success/'.rawurlencode($publicId));
} catch(RuntimeException $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(422);
    exit($e->getMessage());
} catch(Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    error_log('Body report save failed: '.$e->getMessage());
    exit('Could not save the report. Please try again or contact the responsible authority.');
}
