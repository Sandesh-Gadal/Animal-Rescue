<?php
require dirname(__DIR__).'/includes/bootstrap.php';require_admin();
$format=strtolower((string)($_GET['format']??'csv'));

$type=$_GET['type']??'';$status=$_GET['status']??'';$q=trim((string)($_GET['q']??''));$police=$_GET['police']??'';
$dateFrom=(string)($_GET['date_from']??'');$dateTo=(string)($_GET['date_to']??'');
if($dateFrom!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)) $dateFrom='';
if($dateTo!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)) $dateTo='';
$where=[];$params=[];
if(in_array($type,['human','animal','unsure'],true)){$where[]='body_type=?';$params[]=$type;}
if(array_key_exists($status,status_labels())){$where[]='status=?';$params[]=$status;}
if($police==='pending'){$where[]="body_type='human' AND police_informed=0";}
if($q!==''){$where[]='(public_id LIKE ? OR reporter_name LIKE ? OR reporter_phone LIKE ? OR location_text LIKE ? OR police_unit LIKE ? OR rescue_team_name LIKE ?)';for($i=0;$i<6;$i++)$params[]='%'.$q.'%';}
if($dateFrom!==''){$where[]='created_at >= ?';$params[]=$dateFrom.' 00:00:00';}
if($dateTo!==''){$where[]='created_at <= ?';$params[]=$dateTo.' 23:59:59';}

$sql="SELECT public_id,body_type,latitude,longitude,gps_accuracy,altitude,location_source,location_text,landmark,description,reporter_name,reporter_phone,alternate_phone,reporter_organization,reporter_private,submitted_by,status,verification_notes,confirmed_at,police_informed,police_informed_at,police_informed_source,police_unit,police_contact_name,police_contact_phone,police_reference,location_shared_with_police_at,rescue_team_name,rescue_team_contact,team_dispatched_at,location_shared_with_team_at,approx_gender,approx_age_min,approx_age_max,body_condition,clothes,identifying_marks,documents_found,nearby_objects,observed_at,weather_condition,animal_species,animal_species_other,estimated_size,carcass_count,decomposition_state,distance_water_source,distance_settlement,disposal_method,disposal_method_notes,equipment_needed,equipment_needed_other,disinfection_materials,recovered_by,recovered_at,destination_type,destination_name,mortuary_bag_no,muchulka_reference,buried_at,burial_location,burial_latitude,burial_longitude,burial_by,burial_notes,identified_name,identification_notes,closure_reason,false_report_details,closed_at,created_at,updated_at FROM body_reports".($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY created_at DESC';
$s=$db->prepare($sql);$s->execute($params);$rows=$s->fetchAll();
audit_log($db,'export_'.$format,'body_report',null,'Rows: '.count($rows).($where?' (filtered)':''));

if($format==='csv'){
 header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="dead-body-reports-'.date('Ymd-His').'.csv"');echo "\xEF\xBB\xBF";$o=fopen('php://output','w');if($rows){fputcsv($o,array_keys($rows[0]));foreach($rows as $r)fputcsv($o,$r);}fclose($o);exit;
}
if($format==='json'){
 header('Content-Type: application/json; charset=UTF-8');header('Content-Disposition: attachment; filename="dead-body-reports-'.date('Ymd-His').'.json"');echo json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if($format==='kml'){
 header('Content-Type: application/vnd.google-earth.kml+xml; charset=UTF-8');header('Content-Disposition: attachment; filename="dead-body-reports-'.date('Ymd-His').'.kml"');
 echo '<?xml version="1.0" encoding="UTF-8"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document>';
 foreach($rows as $r){echo '<Placemark><name>'.htmlspecialchars($r['public_id'],ENT_XML1).'</name><description>'.htmlspecialchars($r['body_type'].' | '.$r['status'].' | '.$r['location_text'],ENT_XML1).'</description><Point><coordinates>'.$r['longitude'].','.$r['latitude'].',0</coordinates></Point></Placemark>';}
 echo '</Document></kml>';exit;
}
if($format==='xlsx'){
 if(!class_exists('ZipArchive')){header('Location: export.php?format=csv');exit;}
 $headers=$rows?array_keys($rows[0]):['public_id'];
 $shared=[]; $siMap=[];
 $strIndex=function($v) use (&$shared,&$siMap){$v=(string)$v;if(!isset($siMap[$v])){$siMap[$v]=count($shared);$shared[]=$v;}return $siMap[$v];};
 $col=function($n){$s='';while($n>0){$n--;$s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;};
 $sheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
 $ri=1;$sheet.='<row r="1">';foreach($headers as $i=>$h){$idx=$strIndex($h);$sheet.='<c r="'.$col($i+1).'1" t="s"><v>'.$idx.'</v></c>';}$sheet.='</row>';
 foreach($rows as $row){$ri++;$sheet.='<row r="'.$ri.'">';$ci=0;foreach($headers as $h){$ci++;$v=$row[$h]??'';$idx=$strIndex($v);$sheet.='<c r="'.$col($ci).$ri.'" t="s"><v>'.$idx.'</v></c>';}$sheet.='</row>';}
 $sheet.='</sheetData></worksheet>';
 $sst='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($shared).'" uniqueCount="'.count($shared).'">';foreach($shared as $v)$sst.='<si><t xml:space="preserve">'.htmlspecialchars($v,ENT_XML1).'</t></si>';$sst.='</sst>';
 $tmp=tempnam(sys_get_temp_dir(),'xlsx');$z=new ZipArchive();$z->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE);
 $z->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
 $z->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
 $z->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Reports" sheetId="1" r:id="rId1"/></sheets></workbook>');
 $z->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
 $z->addFromString('xl/worksheets/sheet1.xml',$sheet);$z->addFromString('xl/sharedStrings.xml',$sst);$z->close();
 header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="dead-body-reports-'.date('Ymd-His').'.xlsx"');readfile($tmp);unlink($tmp);exit;
}
http_response_code(400);echo 'Unsupported export format.';
