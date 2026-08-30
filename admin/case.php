<?php
require dirname(__DIR__).'/includes/bootstrap.php';
require_admin();
$id=(string)($_GET['id']??'');
$s=$db->prepare('SELECT * FROM body_reports WHERE public_id=?');
$s->execute([$id]);
$r=$s->fetch();
if(!$r){http_response_code(404);exit('Case not found');}

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    if(!can_edit()) exit('Permission denied.');

    // Guided one-click operational workflow. This is intentionally separate from
    // the full correction form below so field staff do not have to select statuses manually.
    if(isset($_POST['quick_action'])){
        $action=(string)$_POST['quick_action'];
        $oldStatus=(string)$r['status'];
        if(is_terminal_status($oldStatus)) exit('This case is already closed. Reopen it from the full case form if authorized.');
        $type=(string)$r['body_type'];
        $adminId=admin_user()['id']??null;
        $now=date('Y-m-d H:i:s');
        $updates=['last_edited_by'=>$adminId];
        $newStatus=$oldStatus;
        $note=mb_substr(trim((string)($_POST['quick_note']??'')),0,2000);

        switch($action){
            case 'confirm':
                $newStatus='confirmed';
                $updates['confirmed_at']=$r['confirmed_at'] ?: $now;
                $updates['confirmed_by']=$r['confirmed_by'] ?: $adminId;
                if($note!=='') $updates['verification_notes']=$note;
                if($note==='') $note='Report verified and case confirmed.';
                break;

            case 'classify_human':
                if($type!=='unsure') exit('This action is only for Unsure cases.');
                if(empty($r['team_dispatched_at'])) exit('Send the assessment/rescue team first.');
                $newStatus='police_informed';
                $updates['body_type']='human';
                $updates['police_informed']=1;
                $updates['police_informed_at']=$r['police_informed_at'] ?: $now;
                $updates['police_informed_source']='admin';
                foreach(['police_unit'=>160,'police_contact_name'=>160,'police_contact_phone'=>30,'police_reference'=>120] as $f=>$max){
                    $v=mb_substr(trim((string)($_POST[$f]??'')),0,$max);
                    if($v!=='') $updates[$f]=$v;
                }
                if($note==='') $note='Assessment team confirmed HUMAN remains. Nepal Police informed and human response workflow activated.';
                break;

            case 'classify_animal':
                if($type!=='unsure') exit('This action is only for Unsure cases.');
                if(empty($r['team_dispatched_at'])) exit('Send the assessment/rescue team first.');
                $newStatus='team_dispatched';
                $updates['body_type']='animal';
                $responderType=(string)($_POST['animal_responder_type']??'local_volunteer');
                $allowedResponders=['local_volunteer','municipality','animal_rescue','community_team','other'];
                if(!in_array($responderType,$allowedResponders,true)) $responderType='local_volunteer';
                $defaults=[
                    'local_volunteer'=>'Local Volunteer',
                    'municipality'=>'Municipality / Local Government Team',
                    'animal_rescue'=>'Animal Rescue Team',
                    'community_team'=>'Community Response Team',
                    'other'=>'Animal Recovery Team',
                ];
                $teamName=mb_substr(trim((string)($_POST['rescue_team_name']??'')),0,180);
                $teamContact=mb_substr(trim((string)($_POST['rescue_team_contact']??'')),0,60);
                $updates['rescue_team_name']=$teamName!==''?$teamName:$defaults[$responderType];
                if($teamContact!=='') $updates['rescue_team_contact']=$teamContact;
                if($note==='') $note='Assessment team confirmed ANIMAL. Animal recovery workflow activated; local volunteers or an animal response team may recover it.';
                break;

            case 'inform_police':
                if($type!=='human') exit('Nepal Police notification workflow is for human cases.');
                if(empty($r['confirmed_at'])) exit('Confirm the case first.');
                $newStatus='police_informed';
                $updates['police_informed']=1;
                $updates['police_informed_at']=$r['police_informed_at'] ?: $now;
                $updates['police_informed_source']='admin';
                foreach(['police_unit'=>160,'police_contact_name'=>160,'police_contact_phone'=>30,'police_reference'=>120] as $f=>$max){
                    $v=mb_substr(trim((string)($_POST[$f]??'')),0,$max);
                    if($v!=='') $updates[$f]=$v;
                }
                if($note==='') $note='Nepal Police informed. Exact location should be shared through the secure share link.';
                break;

            case 'dispatch_team':
                if(empty($r['confirmed_at'])) exit('Confirm the case first.');
                if($type==='human' && empty($r['police_informed'])) exit('For a human case, inform Nepal Police before dispatching the response team.');
                $newStatus='team_dispatched';
                $teamName=mb_substr(trim((string)($_POST['rescue_team_name']??'')),0,180);
                $teamContact=mb_substr(trim((string)($_POST['rescue_team_contact']??'')),0,60);
                $defaultTeam=$type==='unsure'?'Assessment / Rescue Team':($type==='animal'?'Local Volunteer / Animal Response Team':'Response / Recovery Team');
                $updates['rescue_team_name']=$teamName!==''?$teamName:($r['rescue_team_name']?:$defaultTeam);
                if($teamContact!=='') $updates['rescue_team_contact']=$teamContact;
                $updates['team_dispatched_at']=$r['team_dispatched_at'] ?: $now;
                if($note==='') $note='Response/recovery team dispatched to the reported location.';
                break;

            case 'recover':
                if(empty($r['team_dispatched_at'])) exit('Dispatch the response team first.');
                $newStatus='recovered';
                $recoveredBy=mb_substr(trim((string)($_POST['recovered_by']??'')),0,160);
                $updates['recovered_by']=$recoveredBy!==''?$recoveredBy:($r['recovered_by']?:($r['rescue_team_name']?:'Response / Recovery Team'));
                $updates['recovered_at']=$r['recovered_at'] ?: $now;
                if($note==='') $note=$type==='animal'?'Animal body recovered.':'Human remains recovered.';
                break;

            case 'handover':
                if($type!=='human') exit('Handover/mortuary step is for human cases.');
                if(empty($r['recovered_at'])) exit('Mark the human remains as recovered first.');
                $destType=(string)($_POST['destination_type']??'mortuary');
                if(!in_array($destType,['hospital','mortuary','other'],true)) $destType='mortuary';
                $destName=mb_substr(trim((string)($_POST['destination_name']??'')),0,200);
                if($destName==='') exit('Enter the police unit, hospital or mortuary where the body was handed over.');
                $newStatus='shifted';
                $updates['destination_type']=$destType;
                $updates['destination_name']=$destName;
                $bag=mb_substr(trim((string)($_POST['mortuary_bag_no']??'')),0,80);
                $muchulka=mb_substr(trim((string)($_POST['muchulka_reference']??'')),0,120);
                if($bag!=='') $updates['mortuary_bag_no']=$bag;
                if($muchulka!=='') $updates['muchulka_reference']=$muchulka;
                if($note==='') $note='Human remains handed over/transferred to '.$destName.'.';
                break;

            case 'bury':
                if($type!=='animal') exit('Burial/final disposal quick action is for animal cases.');
                if(empty($r['recovered_at'])) exit('Mark the animal body as recovered first.');
                $newStatus='buried';
                $burialLocation=mb_substr(trim((string)($_POST['burial_location']??'')),0,255);
                $burialBy=mb_substr(trim((string)($_POST['burial_by']??'')),0,180);
                $updates['buried_at']=$r['buried_at'] ?: $now;
                $updates['burial_location']=$burialLocation!==''?$burialLocation:($r['burial_location']?:($r['location_text']?:'Recovery area'));
                $updates['burial_by']=$burialBy!==''?$burialBy:($r['burial_by']?:($r['recovered_by']?:$r['rescue_team_name']));
                $updates['destination_type']='burial_site';
                $updates['destination_name']=$updates['burial_location'];
                if($note!=='') $updates['burial_notes']=$note;
                if($note==='') $note='Animal body buried / safely disposed.';
                break;

            case 'close':
                if(!can_close()) exit('You do not have permission to close cases.');
                if($type==='animal' && empty($r['buried_at'])) exit('Animal case can be closed only after burial/final disposal is recorded.');
                if($type==='human' && empty($r['recovered_at'])) exit('Human case can be closed only after recovery is recorded.');
                if($type==='human' && empty($r['destination_name'])) exit('Record handover to Nepal Police/hospital/mortuary before closing the human case.');
                $newStatus='closed';
                $reason=mb_substr(trim((string)($_POST['closure_reason']??'')),0,255);
                $updates['closure_reason']=$reason!==''?$reason:'Workflow completed';
                $updates['closed_at']=$now;
                $updates['closed_by']=$adminId;
                if($note==='') $note='Case formally closed after completion of required workflow.';
                break;

            case 'fake_close':
                if(!can_close()) exit('You do not have permission to close cases.');
                $details=trim((string)($_POST['false_report_details']??''));
                if($details==='') exit('Enter why/how the report was confirmed as fake.');
                $newStatus='false_report';
                $updates['false_report_details']=$details;
                $updates['closure_reason']='False / fake report';
                $updates['closed_at']=$now;
                $updates['closed_by']=$adminId;
                if($note==='') $note='Report verified as false/fake and case closed.';
                break;

            default:
                exit('Invalid workflow action.');
        }

        $updates['status']=$newStatus;
        $set=[];$params=[];
        foreach($updates as $column=>$value){
            $set[]="$column=?";$params[]=$value;
        }
        $params[]=$r['id'];
        $u=$db->prepare('UPDATE body_reports SET '.implode(',',$set).' WHERE id=?');
        $u->execute($params);
        if($oldStatus!==$newStatus || in_array($action,['classify_human','classify_animal'],true)){
            $h=$db->prepare('INSERT INTO status_history(report_id,old_status,new_status,note,changed_by) VALUES(?,?,?,?,?)');
            $h->execute([$r['id'],$oldStatus,$newStatus,$note,$adminId]);
        }
        audit_log($db,'workflow_action','body_report',$r['public_id'],$action.': '.$note);
        header('Location: '.app_base($config).'/admin/case.php?id='.rawurlencode($r['public_id']).'&saved=1&action='.rawurlencode($action));
        exit;
    }

    $oldStatus=(string)$r['status'];
    $newStatus=(string)($_POST['status']??$oldStatus);
    if(!array_key_exists($newStatus,status_labels())) exit('Invalid status.');
    if((is_terminal_status($newStatus) || is_terminal_status($oldStatus)) && !can_close()) exit('You do not have permission to close or reopen terminal cases.');

    $bodyType=(string)($_POST['body_type']??$r['body_type']);
    if(!in_array($bodyType,['human','animal','unsure'],true)) exit('Invalid body type.');
    $lat=filter_var($_POST['latitude']??null,FILTER_VALIDATE_FLOAT);
    $lng=filter_var($_POST['longitude']??null,FILTER_VALIDATE_FLOAT);
    if($lat===false || $lng===false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) exit('Valid coordinates are required.');
    $locSource=(string)($_POST['location_source']??'unknown');
    if(!in_array($locSource,['gps','map','manual','unknown'],true)) $locSource='unknown';

    $reporterName=mb_substr(trim((string)($_POST['reporter_name']??'')),0,120);
    $reporterPhone=mb_substr(trim((string)($_POST['reporter_phone']??'')),0,30);
    if($reporterName==='' || !validate_phone($reporterPhone)) exit('Valid reporter name and phone are required.');

    $policeInformed=isset($_POST['police_informed'])?1:0;
    $policeAt=trim((string)($_POST['police_informed_at']??''));
    $policeAt=$policeAt!==''?str_replace('T',' ',substr($policeAt,0,16)).':00':null;
    if(($newStatus==='police_informed' || $policeInformed) && !$policeAt) $policeAt=date('Y-m-d H:i:s');
    if($newStatus==='police_informed') $policeInformed=1;

    $confirmedAt=trim((string)($_POST['confirmed_at']??''));
    $confirmedAt=$confirmedAt!==''?str_replace('T',' ',substr($confirmedAt,0,16)).':00':null;
    if(in_array($newStatus,['verified','confirmed','police_informed','team_dispatched','recovered','shifted','buried','identified','closed'],true) && !$confirmedAt){
        $confirmedAt=$r['confirmed_at'] ?: date('Y-m-d H:i:s');
    }

    $teamDispatchedAt=trim((string)($_POST['team_dispatched_at']??''));
    $teamDispatchedAt=$teamDispatchedAt!==''?str_replace('T',' ',substr($teamDispatchedAt,0,16)).':00':null;
    if($newStatus==='team_dispatched' && !$teamDispatchedAt) $teamDispatchedAt=date('Y-m-d H:i:s');

    $recoveredAt=trim((string)($_POST['recovered_at']??''));
    $recoveredAt=$recoveredAt!==''?str_replace('T',' ',substr($recoveredAt,0,16)).':00':null;
    if(in_array($newStatus,['recovered','shifted','buried','identified','closed'],true) && !$recoveredAt && in_array($newStatus,['recovered','shifted','buried','identified'],true)) $recoveredAt=date('Y-m-d H:i:s');

    $observedAt=trim((string)($_POST['observed_at']??''));
    $observedAt=$observedAt!==''?str_replace('T',' ',substr($observedAt,0,16)).':00':null;

    $buriedAt=trim((string)($_POST['buried_at']??''));
    $buriedAt=$buriedAt!==''?str_replace('T',' ',substr($buriedAt,0,16)).':00':null;
    if($newStatus==='buried' && !$buriedAt) $buriedAt=date('Y-m-d H:i:s');

    $destType=(string)($_POST['destination_type']??'none');
    if(!in_array($destType,['hospital','mortuary','shelter','burial_site','other','none'],true)) $destType='none';
    $gender=(string)($_POST['approx_gender']??'unknown');
    if(!in_array($gender,['male','female','unknown','not_applicable'],true)) $gender='unknown';

    $fields=[
        'body_type'=>$bodyType,
        'latitude'=>$lat,'longitude'=>$lng,
        'gps_accuracy'=>($_POST['gps_accuracy']??'')!==''?(float)$_POST['gps_accuracy']:null,
        'location_source'=>$locSource,
        'location_text'=>mb_substr(trim((string)($_POST['location_text']??'')),0,255),
        'landmark'=>mb_substr(trim((string)($_POST['landmark']??'')),0,255),
        'description'=>mb_substr(trim((string)($_POST['description']??'')),0,3000),
        'reporter_name'=>$reporterName,'reporter_phone'=>$reporterPhone,
        'alternate_phone'=>mb_substr(trim((string)($_POST['alternate_phone']??'')),0,30),
        'reporter_organization'=>mb_substr(trim((string)($_POST['reporter_organization']??'')),0,160),
        'status'=>$newStatus,
        'verification_notes'=>trim((string)($_POST['verification_notes']??'')),
        'confirmed_at'=>$confirmedAt,
        'confirmed_by'=>$confirmedAt?($r['confirmed_by'] ?: (admin_user()['id']??null)):null,
        'police_informed'=>$policeInformed,
        'police_informed_at'=>$policeInformed?$policeAt:null,
        'police_informed_source'=>$policeInformed?($r['police_informed_source']==='reporter'?'reporter':'admin'):'unknown',
        'police_unit'=>mb_substr(trim((string)($_POST['police_unit']??'')),0,160),
        'police_contact_name'=>mb_substr(trim((string)($_POST['police_contact_name']??'')),0,160),
        'police_contact_phone'=>mb_substr(trim((string)($_POST['police_contact_phone']??'')),0,30),
        'police_reference'=>mb_substr(trim((string)($_POST['police_reference']??'')),0,120),
        'rescue_team_name'=>mb_substr(trim((string)($_POST['rescue_team_name']??'')),0,180),
        'rescue_team_contact'=>mb_substr(trim((string)($_POST['rescue_team_contact']??'')),0,60),
        'team_dispatched_at'=>$teamDispatchedAt,
        'approx_gender'=>$gender,
        'approx_age_min'=>($_POST['approx_age_min']??'')!==''?(int)$_POST['approx_age_min']:null,
        'approx_age_max'=>($_POST['approx_age_max']??'')!==''?(int)$_POST['approx_age_max']:null,
        'body_condition'=>mb_substr(trim((string)($_POST['body_condition']??'')),0,160),
        'clothes'=>trim((string)($_POST['clothes']??'')),
        'identifying_marks'=>trim((string)($_POST['identifying_marks']??'')),
        'documents_found'=>trim((string)($_POST['documents_found']??'')),
        'nearby_objects'=>trim((string)($_POST['nearby_objects']??'')),
        'observed_at'=>$observedAt,
        'weather_condition'=>array_key_exists((string)($_POST['weather_condition']??''),weather_condition_labels())?$_POST['weather_condition']:null,
        'animal_species'=>array_key_exists((string)($_POST['animal_species']??''),animal_species_labels())?$_POST['animal_species']:null,
        'animal_species_other'=>mb_substr(trim((string)($_POST['animal_species_other']??'')),0,120),
        'estimated_size'=>array_key_exists((string)($_POST['estimated_size']??''),estimated_size_labels())?$_POST['estimated_size']:null,
        'carcass_count'=>max(1,(int)($_POST['carcass_count']??1)),
        'decomposition_state'=>array_key_exists((string)($_POST['decomposition_state']??''),decomposition_state_labels())?$_POST['decomposition_state']:null,
        'distance_water_source'=>array_key_exists((string)($_POST['distance_water_source']??''),distance_water_source_labels())?$_POST['distance_water_source']:null,
        'distance_settlement'=>array_key_exists((string)($_POST['distance_settlement']??''),distance_settlement_labels())?$_POST['distance_settlement']:null,
        'disposal_method'=>array_key_exists((string)($_POST['disposal_method']??''),disposal_method_labels())?$_POST['disposal_method']:null,
        'disposal_method_notes'=>mb_substr(trim((string)($_POST['disposal_method_notes']??'')),0,255),
        'equipment_needed'=>implode(',',array_intersect((array)($_POST['equipment_needed']??[]),array_keys(equipment_needed_labels()))) ?: null,
        'equipment_needed_other'=>mb_substr(trim((string)($_POST['equipment_needed_other']??'')),0,120),
        'disinfection_materials'=>implode(',',array_intersect((array)($_POST['disinfection_materials']??[]),array_keys(disinfection_materials_labels()))) ?: null,
        'recovered_by'=>mb_substr(trim((string)($_POST['recovered_by']??'')),0,160),
        'recovered_at'=>$recoveredAt,
        'destination_type'=>$destType,
        'destination_name'=>mb_substr(trim((string)($_POST['destination_name']??'')),0,200),
        'mortuary_bag_no'=>mb_substr(trim((string)($_POST['mortuary_bag_no']??'')),0,80),
        'muchulka_reference'=>mb_substr(trim((string)($_POST['muchulka_reference']??'')),0,120),
        'buried_at'=>$buriedAt,
        'burial_location'=>mb_substr(trim((string)($_POST['burial_location']??'')),0,255),
        'burial_latitude'=>($_POST['burial_latitude']??'')!==''?(float)$_POST['burial_latitude']:null,
        'burial_longitude'=>($_POST['burial_longitude']??'')!==''?(float)$_POST['burial_longitude']:null,
        'burial_by'=>mb_substr(trim((string)($_POST['burial_by']??'')),0,180),
        'burial_notes'=>trim((string)($_POST['burial_notes']??'')),
        'identified_name'=>mb_substr(trim((string)($_POST['identified_name']??'')),0,180),
        'identification_notes'=>trim((string)($_POST['identification_notes']??'')),
        'closure_reason'=>mb_substr(trim((string)($_POST['closure_reason']??'')),0,255),
        'false_report_details'=>trim((string)($_POST['false_report_details']??'')),
        'last_edited_by'=>admin_user()['id']??null,
        'id'=>$r['id'],
    ];

    $set=[];
    foreach(array_keys($fields) as $key){ if($key!=='id') $set[]="$key=:$key"; }
    if(is_terminal_status($newStatus)){
        $set[]='closed_at=COALESCE(closed_at,NOW())';
        $set[]='closed_by=:closed_by';
        $fields['closed_by']=admin_user()['id']??null;
    } else {
        $set[]='closed_at=NULL';
        $set[]='closed_by=NULL';
    }
    $u=$db->prepare('UPDATE body_reports SET '.implode(',',$set).' WHERE id=:id');
    $u->execute($fields);

    if($oldStatus!==$newStatus){
        $h=$db->prepare('INSERT INTO status_history(report_id,old_status,new_status,note,changed_by) VALUES(?,?,?,?,?)');
        $h->execute([$r['id'],$oldStatus,$newStatus,mb_substr(trim((string)($_POST['status_note']??'')),0,2000),admin_user()['id']??null]);
    }

    $changed=[];
    foreach($fields as $k=>$v){
        if($k==='id' || !array_key_exists($k,$r)) continue;
        if((string)($r[$k]??'')!==(string)($v??'')) $changed[]=$k;
    }
    audit_log($db,'update_case','body_report',$r['public_id'],'Changed: '.implode(', ',$changed).'; status '.$oldStatus.' -> '.$newStatus);
    header('Location: '.app_base($config).'/admin/case.php?id='.rawurlencode($r['public_id']).'&saved=1');
    exit;
}

// Reload after any redirectless access.
$s=$db->prepare('SELECT r.*,a.name submitted_by_name FROM body_reports r LEFT JOIN admin_users a ON a.id=r.submitted_by WHERE r.public_id=?');$s->execute([$id]);$r=$s->fetch();
$photos=report_photos($db,(int)$r['id']);
$hist=$db->prepare("SELECT h.*,u.name admin_name FROM status_history h LEFT JOIN admin_users u ON u.id=h.changed_by WHERE h.report_id=? ORDER BY h.created_at DESC");
$hist->execute([$r['id']]);$history=$hist->fetchAll();
$shareCount=$db->prepare("SELECT COUNT(*) FROM case_share_links WHERE report_id=? AND revoked_at IS NULL AND expires_at>NOW()");$shareCount->execute([$r['id']]);$activeShares=(int)$shareCount->fetchColumn();
$title='Case '.$r['public_id'];$useLeaflet=true;require dirname(__DIR__).'/includes/admin_header.php';
$maps=maps_url((float)$r['latitude'],(float)$r['longitude']);
$nextAction=workflow_next_action($r);
?>
<?php if(isset($_GET['saved'])):?><div class="success" style="margin-bottom:16px">Case updated successfully.</div><?php endif;?>
<div class="success no-print" style="margin-bottom:16px"><strong>CASE UPDATE CONTROLS ACTIVE</strong> — complete the green Next Required Action below. Fake reports can be closed from the red section.</div>
<div class="case-toolbar">
  <div><div class="muted">Case</div><h1 class="case-id"><?=e($r['public_id'])?></h1><span class="badge"><?=e(status_labels()[$r['status']]??$r['status'])?></span></div>
  <div class="no-print toolbar-actions"><a class="btn" href="<?=e(app_base($config))?>/admin">← Dashboard</a>
    <a class="btn" target="_blank" href="<?=e(app_base($config))?>/admin/print_case.php?id=<?=rawurlencode($r['public_id'])?>">Print Report</a>
    <a class="btn btn-dark" target="_blank" rel="noopener" href="<?=e($maps)?>">Navigate to Exact GPS</a>
    <a class="btn btn-primary" href="<?=e(app_base($config))?>/admin/share.php?id=<?=rawurlencode($r['public_id'])?>">Share Exact Location (<?=$activeShares?> active)</a>
  </div>
</div>

<div class="contact-actions no-print" style="margin-top:12px">
  <?php if(!empty($r['reporter_phone'])):?><a class="btn" href="tel:<?=e($r['reporter_phone'])?>">☎ Call Reporter</a><?php endif;?>
  <?php if($r['body_type']==='human' && !empty($r['police_contact_phone'])):?><a class="btn btn-primary" href="tel:<?=e($r['police_contact_phone'])?>">☎ Call Police Contact</a><?php endif;?>
  <?php if(!empty($r['rescue_team_contact'])):?><a class="btn btn-green" href="tel:<?=e($r['rescue_team_contact'])?>">☎ Call Response Team</a><?php endif;?>
</div>

<?php if(!can_edit()): ?><div class="warning no-print" style="margin-top:18px"><strong>Your account is Viewer-only.</strong> Log in with an Admin or Operator account to update cases.</div><?php endif; ?>
<div class="card workflow-card no-print" style="margin-top:18px">
  <div class="workflow-head">
    <div>
      <div class="muted">NEXT REQUIRED ACTION</div>
      <h2 style="margin:4px 0 6px"><?=e($nextAction['label'])?></h2>
      <p class="muted" style="margin:0"><?=e($nextAction['description'])?></p>
    </div>
    <span class="workflow-type"><?=e(strtoupper((string)$r['body_type']))?></span>
  </div>

  <?php if($nextAction['key']==='classify_found'): ?>
    <div class="info" style="margin-top:14px"><strong>Assessment team reached the location.</strong> Record what they found. Do not use Advanced Edit just to classify the case.</div>
    <div class="grid grid-2" style="margin-top:14px;align-items:start">
      <form method="post" class="card" style="margin:0;border:2px solid #b91c1c">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="quick_action" value="classify_human">
        <h3 style="margin-top:0">Found Human</h3>
        <p class="small muted">Changes the case to Human and records Nepal Police as informed.</p>
        <div class="form-group"><label>Police unit / station</label><input class="input" name="police_unit" placeholder="Nearest Nepal Police unit"></div>
        <div class="grid grid-2"><div class="form-group"><label>Police contact</label><input class="input" name="police_contact_phone"></div><div class="form-group"><label>Reference / diary no.</label><input class="input" name="police_reference"></div></div>
        <div class="form-group"><label>Contact person</label><input class="input" name="police_contact_name"></div>
        <div class="form-group"><label>Note</label><input class="input" name="quick_note" placeholder="Optional verification / police notification note"></div>
        <button class="btn btn-primary btn-lg" <?=can_edit()?'':'disabled'?>>Found Human → Inform Nepal Police</button>
      </form>
      <form method="post" class="card" style="margin:0;border:2px solid #15803d">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="quick_action" value="classify_animal">
        <h3 style="margin-top:0">Found Animal</h3>
        <p class="small muted">Changes the case to Animal. A local volunteer may recover the animal if appropriate and safe.</p>
        <div class="form-group"><label>Who will recover / rescue?</label><select class="select" name="animal_responder_type"><option value="local_volunteer">Local Volunteer</option><option value="municipality">Municipality / Local Government Team</option><option value="animal_rescue">Animal Rescue Team</option><option value="community_team">Community Response Team</option><option value="other">Other</option></select></div>
        <div class="grid grid-2"><div class="form-group"><label>Responder / team name</label><input class="input" name="rescue_team_name" placeholder="Optional; defaults to selected responder"></div><div class="form-group"><label>Contact</label><input class="input" name="rescue_team_contact"></div></div>
        <div class="form-group"><label>Note</label><input class="input" name="quick_note" placeholder="Optional classification note"></div>
        <button class="btn btn-green btn-lg" <?=can_edit()?'':'disabled'?>>Found Animal → Continue Animal Rescue</button>
      </form>
    </div>
  <?php elseif($nextAction['key']==='closed'): ?>
    <div class="success" style="margin-top:14px"><strong>No operational update pending.</strong> This case is terminal/closed.</div>
  <?php else: ?>
    <form method="post" class="quick-action-form" style="margin-top:16px">
      <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
      <input type="hidden" name="quick_action" value="<?=e($nextAction['key'])?>">

      <?php if($nextAction['key']==='confirm'): ?>
        <div class="form-group"><label>Verification note <span class="muted small">(optional)</span></label><input class="input" name="quick_note" placeholder="Example: Reporter contacted and location/photo verified"></div>
      <?php elseif($nextAction['key']==='inform_police'): ?>
        <div class="grid grid-2"><div class="form-group"><label>Police unit / station</label><input class="input" name="police_unit" placeholder="Nearest Nepal Police unit"></div><div class="form-group"><label>Police contact</label><input class="input" name="police_contact_phone" placeholder="Contact number"></div><div class="form-group"><label>Contact person</label><input class="input" name="police_contact_name"></div><div class="form-group"><label>Reference / diary no.</label><input class="input" name="police_reference"></div></div>
        <div class="info" style="margin-bottom:14px">After recording this step, use <strong>Share Exact Location</strong> above to create a secure location link for the police unit.</div>
      <?php elseif($nextAction['key']==='dispatch_team'): ?>
        <div class="grid grid-2"><div class="form-group"><label><?= $r['body_type']==='unsure'?'Assessment / rescue team':'Team name' ?></label><input class="input" name="rescue_team_name" placeholder="<?= $r['body_type']==='unsure'?'Nearest responder / local volunteer / municipality / police':'Police / APF / Army / municipality / local volunteer / animal team' ?>"></div><div class="form-group"><label>Team contact</label><input class="input" name="rescue_team_contact"></div></div>
        <div class="info" style="margin-bottom:14px"><?php if($r['body_type']==='unsure'): ?><strong>Unsure case:</strong> send a responder to inspect first. After dispatch, the next screen will let you record <strong>Found Human</strong> or <strong>Found Animal</strong>.<?php else: ?>Use <strong>Share Exact Location</strong> to send the protected GPS link to the dispatched team.<?php endif; ?></div>
      <?php elseif($nextAction['key']==='recover'): ?>
        <div class="form-group"><label>Recovered / rescued by</label><input class="input" name="recovered_by" value="<?=e($r['rescue_team_name'])?>" placeholder="Team / organization"></div>
      <?php elseif($nextAction['key']==='handover'): ?>
        <div class="grid grid-2"><div class="form-group"><label>Handover destination</label><select class="select" name="destination_type"><option value="mortuary">Mortuary</option><option value="hospital">Hospital</option><option value="other">Nepal Police / Other</option></select></div><div class="form-group"><label>Destination / unit name *</label><input class="input" name="destination_name" required placeholder="Police unit, hospital or mortuary"></div><div class="form-group"><label>Mortuary Bag No.</label><input class="input" name="mortuary_bag_no"></div><div class="form-group"><label>Muchulka / police reference</label><input class="input" name="muchulka_reference"></div></div>
      <?php elseif($nextAction['key']==='bury'): ?>
        <div class="grid grid-2"><div class="form-group"><label>Burial / disposal location</label><input class="input" name="burial_location" placeholder="If blank, recovery area is used"></div><div class="form-group"><label>Buried / handled by</label><input class="input" name="burial_by" value="<?=e($r['recovered_by']?:$r['rescue_team_name'])?>"></div></div>
      <?php elseif($nextAction['key']==='close'): ?>
        <div class="form-group"><label>Closure reason</label><input class="input" name="closure_reason" value="Workflow completed" placeholder="Why this case can now be closed"></div>
      <?php endif; ?>

      <button class="btn btn-green btn-lg" <?=can_edit()?'':'disabled'?>><?=e($nextAction['label'])?></button>
    </form>
  <?php endif; ?>

  <?php if(!is_terminal_status((string)$r['status'])): ?>
    <div class="fake-case-box danger" style="margin-top:18px" id="fake-report-section">
      <h3 style="margin-top:0">Fake / False Report</h3>
      <p class="small">If verification proves the report is false, enter the reason and close it. The original report remains in the audit trail.</p>
      <form method="post" style="margin-top:12px" onsubmit="return confirm('Mark this report as FALSE and close the case? The original record will remain in the audit trail.');">
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <input type="hidden" name="quick_action" value="fake_close">
        <div class="form-group"><label>Why is this report fake? *</label><textarea class="textarea" name="false_report_details" required placeholder="How was it checked and why was the report determined to be false?"></textarea></div>
        <button class="btn btn-primary" <?=can_close()?'':'disabled'?>>Mark Fake & Close Case</button>
        <?php if(!can_close()): ?><span class="small muted"> Your account can edit cases but cannot close them.</span><?php endif; ?>
      </form>
    </div>
  <?php endif; ?>
</div>

<div class="milestone-grid <?=e($r['body_type']==='animal'?'milestone-grid-5':'')?>">
  <div class="milestone <?=!empty($r['confirmed_at'])?'done':''?>"><strong>1. Confirmed</strong><span><?=e($r['confirmed_at'] ?: 'Pending')?></span></div>
  <?php if($r['body_type']==='human'): ?>
    <div class="milestone <?=!empty($r['police_informed'])?'done':''?>"><strong>2. Nepal Police informed</strong><span><?=e($r['police_informed_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['team_dispatched_at'])?'done':''?>"><strong>3. Team dispatched</strong><span><?=e($r['team_dispatched_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['recovered_at'])?'done':''?>"><strong>4. Recovered</strong><span><?=e($r['recovered_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=$r['status']==='shifted'||!empty($r['destination_name'])?'done':''?>"><strong>5. Handover / mortuary</strong><span><?=e($r['destination_name'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['closed_at'])?'done':''?>"><strong>6. Closed</strong><span><?=e($r['closed_at'] ?: 'Open')?></span></div>
  <?php elseif($r['body_type']==='animal'): ?>
    <div class="milestone <?=!empty($r['team_dispatched_at'])?'done':''?>"><strong>2. Team dispatched</strong><span><?=e($r['team_dispatched_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['recovered_at'])?'done':''?>"><strong>3. Recovered</strong><span><?=e($r['recovered_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['buried_at'])?'done':''?>"><strong>4. Buried / disposed</strong><span><?=e($r['buried_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['closed_at'])?'done':''?>"><strong>5. Closed</strong><span><?=e($r['closed_at'] ?: 'Open')?></span></div>
  <?php else: ?>
    <div class="milestone <?=!empty($r['team_dispatched_at'])?'done':''?>"><strong>2. Assessment team sent</strong><span><?=e($r['team_dispatched_at'] ?: 'Pending')?></span></div>
    <div class="milestone"><strong>3. Identify at scene</strong><span>Human or Animal</span></div>
    <div class="milestone"><strong>4. Police / animal response</strong><span>Starts after identification</span></div>
    <div class="milestone"><strong>5. Recovery & final handling</strong><span>Pending</span></div>
    <div class="milestone <?=!empty($r['closed_at'])?'done':''?>"><strong>6. Closed</strong><span><?=e($r['closed_at'] ?: 'Open')?></span></div>
  <?php endif; ?>
</div>

<div class="grid grid-2" style="margin-top:18px">
<div class="card"><h2>Original report</h2>
<p><strong>Type:</strong> <?=e(body_type_labels()[$r['body_type']]??$r['body_type'])?><br>
<strong>Exact GPS:</strong> <?=e($r['latitude'])?>, <?=e($r['longitude'])?><br>
<strong>Source:</strong> <?=e($r['location_source'])?> · <strong>Accuracy:</strong> <?=e((string)$r['gps_accuracy'])?> m<br>
<strong>Location:</strong> <?=e($r['location_text'])?><br><strong>Landmark:</strong> <?=e($r['landmark'])?></p>
<p><?=nl2br(e($r['description']))?></p><hr>
<p><strong>Reporter:</strong> <?=e($r['reporter_name'])?><br><strong>Phone:</strong> <?=e($r['reporter_phone'])?><br><strong>Alternative:</strong> <?=e($r['alternate_phone'])?><br><strong>Organization:</strong> <?=e($r['reporter_organization'])?>
<?php if(!empty($r['submitted_by_name'])):?><br><strong>Filed by:</strong> <?=e($r['submitted_by_name'])?><?php endif;?></p>
<?php if($r['body_type']==='animal' && ($r['observed_at']||$r['animal_species']||$r['decomposition_state']||$r['distance_water_source']||$r['disposal_method']||$r['equipment_needed']||$r['disinfection_materials'])):?>
<hr>
<p><strong>Observed:</strong> <?=e($r['observed_at'])?> · <strong>Weather:</strong> <?=e(weather_condition_labels()[$r['weather_condition']]??$r['weather_condition'])?><br>
<strong>Species:</strong> <?=e(animal_species_labels()[$r['animal_species']]??$r['animal_species'])?><?=$r['animal_species']==='other'?' — '.e($r['animal_species_other']):''?><br>
<strong>Size:</strong> <?=e(estimated_size_labels()[$r['estimated_size']]??$r['estimated_size'])?> · <strong>Count:</strong> <?=e((string)$r['carcass_count'])?> · <strong>Decomposition:</strong> <?=e(decomposition_state_labels()[$r['decomposition_state']]??$r['decomposition_state'])?><br>
<strong>Distance from water:</strong> <?=e(distance_water_source_labels()[$r['distance_water_source']]??$r['distance_water_source'])?> · <strong>Distance from settlement:</strong> <?=e(distance_settlement_labels()[$r['distance_settlement']]??$r['distance_settlement'])?><br>
<strong>Proposed disposal:</strong> <?=e(disposal_method_labels()[$r['disposal_method']]??$r['disposal_method'])?><?=$r['disposal_method_notes']?' — '.e($r['disposal_method_notes']):''?><br>
<strong>Equipment needed:</strong> <?=e(implode(', ',array_map(fn($k)=>equipment_needed_labels()[$k]??$k,array_filter(explode(',',(string)$r['equipment_needed'])))))?><?=$r['equipment_needed_other']?' + '.e($r['equipment_needed_other']):''?><br>
<strong>Disinfection materials:</strong> <?=e(implode(', ',array_map(fn($k)=>disinfection_materials_labels()[$k]??$k,array_filter(explode(',',(string)$r['disinfection_materials'])))))?></p>
<?php endif;?>
</div>
<div class="card"><div id="map" class="map" style="height:410px"></div></div>
</div>

<div class="card" style="margin-top:18px"><h2>Evidence photos</h2><?php if(!$photos):?><p class="muted">No photos.</p><?php else:?><div class="photo-grid"><?php foreach($photos as $p):?><a target="_blank" href="photo.php?id=<?=e((string)$p['id'])?>"><img src="photo.php?id=<?=e((string)$p['id'])?>" alt="Restricted case evidence"></a><?php endforeach;?></div><?php endif;?></div>

<details class="card no-print" style="margin-top:18px" id="case-details"><summary class="details-summary" style="cursor:pointer;font-weight:850;font-size:18px;min-height:48px;display:flex;align-items:center;justify-content:space-between;gap:10px"><span>Advanced Edit / Correct Case Data</span><span class="details-chevron"><?=icon_chevron()?></span></summary>
<form method="post" style="margin-top:14px">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<p class="muted">Use this only for corrections, detailed police/recovery information, or a manual status change. Normal operations should use the guided action above.</p>
<div class="grid grid-2"><div class="form-group"><label>Status</label><select class="select" name="status"><?php foreach(status_labels() as $k=>$v):?><option value="<?=$k?>" <?=$r['status']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div><div class="form-group"><label>Status change note</label><input class="input" name="status_note" placeholder="Reason / action taken"></div></div>

<h3>Correct original report</h3>
<div class="grid grid-3">
<div class="form-group"><label>Body type</label><select class="select" name="body_type"><?php foreach(body_type_labels() as $k=>$v):?><option value="<?=$k?>" <?=$r['body_type']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Latitude</label><input class="input" name="latitude" inputmode="decimal" value="<?=e((string)$r['latitude'])?>" required></div>
<div class="form-group"><label>Longitude</label><input class="input" name="longitude" inputmode="decimal" value="<?=e((string)$r['longitude'])?>" required></div>
</div>
<div class="grid grid-3"><div class="form-group"><label>GPS accuracy (m)</label><input class="input" name="gps_accuracy" inputmode="decimal" value="<?=e((string)$r['gps_accuracy'])?>"></div><div class="form-group"><label>Location source</label><select class="select" name="location_source"><?php foreach(['gps','map','manual','unknown'] as $v):?><option value="<?=$v?>" <?=$r['location_source']===$v?'selected':''?>><?=$v?></option><?php endforeach;?></select></div><div></div></div>
<div class="grid grid-2"><div class="form-group"><label>Location text</label><input class="input" name="location_text" value="<?=e($r['location_text'])?>"></div><div class="form-group"><label>Landmark</label><input class="input" name="landmark" value="<?=e($r['landmark'])?>"></div></div>
<div class="form-group"><label>Description</label><textarea class="textarea" name="description"><?=e($r['description'])?></textarea></div>
<div class="grid grid-2"><div class="form-group"><label>Reporter name</label><input class="input" name="reporter_name" value="<?=e($r['reporter_name'])?>" required></div><div class="form-group"><label>Reporter phone</label><input class="input" name="reporter_phone" value="<?=e($r['reporter_phone'])?>" required></div><div class="form-group"><label>Alternative phone</label><input class="input" name="alternate_phone" value="<?=e($r['alternate_phone'])?>"></div><div class="form-group"><label>Organization</label><input class="input" name="reporter_organization" value="<?=e($r['reporter_organization'])?>"></div></div>

<h3>Confirmation</h3>
<div class="grid grid-2"><div class="form-group"><label>Confirmed at</label><input class="input" type="datetime-local" name="confirmed_at" value="<?=e($r['confirmed_at']?str_replace(' ','T',substr($r['confirmed_at'],0,16)):'')?>"></div><div class="form-group"><label>Verification notes</label><textarea class="textarea" name="verification_notes"><?=e($r['verification_notes'])?></textarea></div></div>

<h3>Nepal Police</h3>
<label class="privacy"><input type="checkbox" name="police_informed" value="1" <?=$r['police_informed']?'checked':''?>><span>Nepal Police has been informed</span></label>
<div class="grid grid-3" style="margin-top:12px"><div class="form-group"><label>Informed at</label><input class="input" type="datetime-local" name="police_informed_at" value="<?=e($r['police_informed_at']?str_replace(' ','T',substr($r['police_informed_at'],0,16)):'')?>"></div><div class="form-group"><label>Police unit/station</label><input class="input" name="police_unit" value="<?=e($r['police_unit'])?>"></div><div class="form-group"><label>Police reference</label><input class="input" name="police_reference" value="<?=e($r['police_reference'])?>"></div><div class="form-group"><label>Contact person</label><input class="input" name="police_contact_name" value="<?=e($r['police_contact_name'])?>"></div><div class="form-group"><label>Police contact</label><input class="input" name="police_contact_phone" value="<?=e($r['police_contact_phone'])?>"></div><div class="form-group"><label>Exact GPS shared</label><input class="input" value="<?=e($r['location_shared_with_police_at'] ?: 'Not recorded')?>" readonly></div></div>

<h3>Rescue / Recovery Team</h3>
<div class="grid grid-2"><div class="form-group"><label>Team name</label><input class="input" name="rescue_team_name" value="<?=e($r['rescue_team_name'])?>" placeholder="Nepal Police / APF / Army / local team"></div><div class="form-group"><label>Team contact</label><input class="input" name="rescue_team_contact" value="<?=e($r['rescue_team_contact'])?>"></div><div class="form-group"><label>Dispatched at</label><input class="input" type="datetime-local" name="team_dispatched_at" value="<?=e($r['team_dispatched_at']?str_replace(' ','T',substr($r['team_dispatched_at'],0,16)):'')?>"></div><div class="form-group"><label>Exact GPS shared</label><input class="input" value="<?=e($r['location_shared_with_team_at'] ?: 'Not recorded')?>" readonly></div></div>

<h3>Identification descriptors</h3>
<div class="grid grid-3"><div class="form-group"><label>Approx gender</label><select class="select" name="approx_gender"><?php foreach(['unknown','male','female','not_applicable'] as $v):?><option value="<?=$v?>" <?=$r['approx_gender']===$v?'selected':''?>><?=$v?></option><?php endforeach;?></select></div><div class="form-group"><label>Age min</label><input class="input" type="number" name="approx_age_min" value="<?=e((string)$r['approx_age_min'])?>"></div><div class="form-group"><label>Age max</label><input class="input" type="number" name="approx_age_max" value="<?=e((string)$r['approx_age_max'])?>"></div></div>
<div class="grid grid-2"><div class="form-group"><label>Body condition</label><input class="input" name="body_condition" value="<?=e($r['body_condition'])?>"></div><div class="form-group"><label>Clothes</label><textarea class="textarea" name="clothes"><?=e($r['clothes'])?></textarea></div><div class="form-group"><label>Tattoo / scars / marks</label><textarea class="textarea" name="identifying_marks"><?=e($r['identifying_marks'])?></textarea></div><div class="form-group"><label>Documents found</label><textarea class="textarea" name="documents_found"><?=e($r['documents_found'])?></textarea></div></div>
<div class="form-group"><label>Nearby objects / vehicle / belongings</label><textarea class="textarea" name="nearby_objects"><?=e($r['nearby_objects'])?></textarea></div>

<h3>Animal Carcass Details</h3>
<div class="grid grid-3">
<div class="form-group"><label>Observed at</label><input class="input" type="datetime-local" name="observed_at" value="<?=e($r['observed_at']?str_replace(' ','T',substr($r['observed_at'],0,16)):'')?>"></div>
<div class="form-group"><label>Weather</label><select class="select" name="weather_condition"><option value="">—</option><?php foreach(weather_condition_labels() as $k=>$v):?><option value="<?=$k?>" <?=$r['weather_condition']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Carcass count</label><input class="input" type="number" min="1" name="carcass_count" value="<?=e((string)($r['carcass_count']?:1))?>"></div>
</div>
<div class="grid grid-3">
<div class="form-group"><label>Species</label><select class="select" name="animal_species"><option value="">—</option><?php foreach(animal_species_labels() as $k=>$v):?><option value="<?=$k?>" <?=$r['animal_species']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Species (if Other)</label><input class="input" name="animal_species_other" value="<?=e($r['animal_species_other'])?>"></div>
<div class="form-group"><label>Estimated size</label><select class="select" name="estimated_size"><option value="">—</option><?php foreach(estimated_size_labels() as $k=>$v):?><option value="<?=$k?>" <?=$r['estimated_size']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
</div>
<div class="grid grid-3">
<div class="form-group"><label>Decomposition state</label><select class="select" name="decomposition_state"><option value="">—</option><?php foreach(decomposition_state_labels() as $k=>$v):?><option value="<?=$k?>" <?=$r['decomposition_state']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Distance from water</label><select class="select" name="distance_water_source"><option value="">—</option><?php foreach(distance_water_source_labels() as $k=>$v):?><option value="<?=$k?>" <?=$r['distance_water_source']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Distance from settlement</label><select class="select" name="distance_settlement"><option value="">—</option><?php foreach(distance_settlement_labels() as $k=>$v):?><option value="<?=$k?>" <?=$r['distance_settlement']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
</div>
<div class="grid grid-2">
<div class="form-group"><label>Disposal method</label><select class="select" name="disposal_method"><option value="">—</option><?php foreach(disposal_method_labels() as $k=>$v):?><option value="<?=$k?>" <?=$r['disposal_method']===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label>Disposal notes (if Other)</label><input class="input" name="disposal_method_notes" value="<?=e($r['disposal_method_notes'])?>"></div>
</div>
<div class="form-group"><label>Equipment needed</label>
<?php $eq=array_filter(explode(',',(string)$r['equipment_needed'])); foreach(equipment_needed_labels() as $k=>$v):?>
<label class="privacy"><input type="checkbox" name="equipment_needed[]" value="<?=$k?>" <?=in_array($k,$eq,true)?'checked':''?>><span><?=e($v)?></span></label>
<?php endforeach;?>
<input class="input" name="equipment_needed_other" value="<?=e($r['equipment_needed_other'])?>" placeholder="Other equipment" style="margin-top:8px"></div>
<div class="form-group"><label>Disinfection materials needed</label>
<?php $di=array_filter(explode(',',(string)$r['disinfection_materials'])); foreach(disinfection_materials_labels() as $k=>$v):?>
<label class="privacy"><input type="checkbox" name="disinfection_materials[]" value="<?=$k?>" <?=in_array($k,$di,true)?'checked':''?>><span><?=e($v)?></span></label>
<?php endforeach;?></div>

<h3>Recovery / transfer</h3>
<div class="grid grid-2"><div class="form-group"><label>Recovered by</label><input class="input" name="recovered_by" value="<?=e($r['recovered_by'])?>"></div><div class="form-group"><label>Recovered at</label><input class="input" type="datetime-local" name="recovered_at" value="<?=e($r['recovered_at']?str_replace(' ','T',substr($r['recovered_at'],0,16)):'')?>"></div><div class="form-group"><label>Destination type</label><select class="select" name="destination_type"><?php foreach(['none','hospital','mortuary','shelter','burial_site','other'] as $v):?><option value="<?=$v?>" <?=$r['destination_type']===$v?'selected':''?>><?=$v?></option><?php endforeach;?></select></div><div class="form-group"><label>Destination name</label><input class="input" name="destination_name" value="<?=e($r['destination_name'])?>"></div><div class="form-group"><label>Mortuary Bag No.</label><input class="input" name="mortuary_bag_no" value="<?=e($r['mortuary_bag_no'])?>"></div><div class="form-group"><label>Muchulka / police reference</label><input class="input" name="muchulka_reference" value="<?=e($r['muchulka_reference'])?>"></div></div>

<h3>Burial / final handling</h3>
<div class="grid grid-2"><div class="form-group"><label>Buried/handled at</label><input class="input" type="datetime-local" name="buried_at" value="<?=e($r['buried_at']?str_replace(' ','T',substr($r['buried_at'],0,16)):'')?>"></div><div class="form-group"><label>Burial/handling location</label><input class="input" name="burial_location" value="<?=e($r['burial_location'])?>"></div><div class="form-group"><label>Burial latitude</label><input class="input" name="burial_latitude" inputmode="decimal" value="<?=e((string)$r['burial_latitude'])?>"></div><div class="form-group"><label>Burial longitude</label><input class="input" name="burial_longitude" inputmode="decimal" value="<?=e((string)$r['burial_longitude'])?>"></div><div class="form-group"><label>Buried / handled by</label><input class="input" name="burial_by" value="<?=e($r['burial_by'])?>"></div><div class="form-group"><label>Notes</label><textarea class="textarea" name="burial_notes"><?=e($r['burial_notes'])?></textarea></div></div>

<h3>Identification & closure</h3>
<div class="grid grid-2"><div class="form-group"><label>Identified person name</label><input class="input" name="identified_name" value="<?=e($r['identified_name'])?>"></div><div class="form-group"><label>Identification notes</label><textarea class="textarea" name="identification_notes"><?=e($r['identification_notes'])?></textarea></div><div class="form-group"><label>Closure reason</label><input class="input" name="closure_reason" value="<?=e($r['closure_reason'])?>" placeholder="Recovered and handed over / false report / duplicate / etc."></div><div class="form-group"><label>False/incorrect report details</label><textarea class="textarea" name="false_report_details" placeholder="What was false or corrected, and how was it verified?"><?=e($r['false_report_details'])?></textarea></div></div>
<div class="warning" style="margin-bottom:16px"><strong>False reports:</strong> Correct the underlying data if known, choose <em>False Report</em> (or another terminal status), record the reason/evidence, and save. Terminal cases can only be closed/reopened by an authorized closer.</div>
<button class="btn btn-green btn-block" <?=can_edit()?'':'disabled'?>>Save Case Update</button>
</form>
</details>

<div class="card" style="margin-top:18px"><h2>Status history</h2><div class="table-wrap"><table class="table"><tr><th>Date</th><th>From</th><th>To</th><th>By</th><th>Note</th></tr><?php foreach($history as $h):?><tr><td><?=e($h['created_at'])?></td><td><?=e(status_labels()[$h['old_status']]??(string)$h['old_status'])?></td><td><?=e(status_labels()[$h['new_status']]??$h['new_status'])?></td><td><?=e($h['admin_name']??'Public/System')?></td><td><?=e($h['note'])?></td></tr><?php endforeach;?></table></div></div>

<script>document.addEventListener('DOMContentLoaded',()=>{const lat=<?=(float)$r['latitude']?>,lng=<?=(float)$r['longitude']?>;const m=L.map('map').setView([lat,lng],16);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(m);L.marker([lat,lng]).addTo(m).bindPopup('Exact report location').openPopup();});</script>
<?php require dirname(__DIR__).'/includes/admin_footer.php'; ?>
