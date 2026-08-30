<?php
require dirname(__DIR__).'/includes/bootstrap.php';require_admin();
$id=(string)($_GET['id']??'');$s=$db->prepare('SELECT * FROM body_reports WHERE public_id=?');$s->execute([$id]);$r=$s->fetch();if(!$r){exit('Not found');}
$photos=report_photos($db,(int)$r['id']);$title='Case Report '.$r['public_id'];require dirname(__DIR__).'/includes/admin_header.php';

function rfield(string $label,?string $val): string {
    $val=trim((string)$val);
    if ($val==='') return '';
    return '<div class="rf"><span class="rl">'.e($label).'</span><span class="rv">'.nl2br(e($val)).'</span></div>';
}

$ageMin=$r['approx_age_min'];$ageMax=$r['approx_age_max'];
$ageRange=($ageMin!==null||$ageMax!==null)?(($ageMin??'?').'–'.($ageMax??'?')):'';
$destination=$r['destination_type']==='none'?'':trim(ucfirst(str_replace('_',' ',(string)$r['destination_type'])).($r['destination_name']?' — '.$r['destination_name']:''));
$equipment=implode(', ',array_map(fn($k)=>equipment_needed_labels()[$k]??$k,array_filter(explode(',',(string)$r['equipment_needed']))));
if ($r['equipment_needed_other']) $equipment=trim($equipment.($equipment?' + ':'').$r['equipment_needed_other']);
$disinfect=implode(', ',array_map(fn($k)=>disinfection_materials_labels()[$k]??$k,array_filter(explode(',',(string)$r['disinfection_materials']))));
$burialGps=($r['burial_latitude']&&$r['burial_longitude'])?$r['burial_latitude'].', '.$r['burial_longitude']:'';
$hasBurial=$r['buried_at']||$r['burial_location']||$burialGps||$r['burial_by']||$r['burial_notes'];
$hasClosure=$r['closed_at']||$r['closure_reason']||$r['false_report_details'];
?>
<div class="report-doc">
<div class="report-topbar no-print">
  <a class="btn" href="<?=e($base)?>/admin/case.php?id=<?=rawurlencode($r['public_id'])?>">← Back to case</a>
  <button class="btn btn-dark" onclick="window.print()">Print</button>
</div>

<header class="report-head">
  <div><div class="report-org"><?=e($config['app_name'] ?? 'Case Management System')?></div><h1>Case Report</h1></div>
  <div class="report-idbox"><div class="report-id"><?=e($r['public_id'])?></div><div class="report-meta-line">Printed <?=e(date('Y-m-d H:i'))?></div></div>
</header>

<div class="report-badges">
  <span class="rb"><?=e(body_type_labels()[$r['body_type']]??$r['body_type'])?></span>
  <span class="rb rb-status"><?=e(status_labels()[$r['status']]??$r['status'])?></span>
  <span class="rb">Reported <?=e($r['created_at'])?></span>
</div>

<section class="report-grid">
  <div class="report-col">
    <h2>Location &amp; Reporting</h2>
    <?=rfield('GPS',$r['latitude'].', '.$r['longitude'])?>
    <?=rfield('Source / accuracy',trim(($r['location_source']?:'').($r['gps_accuracy']?' / '.$r['gps_accuracy'].' m':'')))?>
    <?=rfield('Location',$r['location_text'])?>
    <?=rfield('Landmark',$r['landmark'])?>
    <?=rfield('Reporter',$r['reporter_name'])?>
    <?=rfield('Phone',$r['reporter_phone'])?>
    <?=rfield('Organization',$r['reporter_organization'])?>
    <?=rfield('Police informed',$r['police_informed']?'Yes':'No')?>
    <?=rfield('Police unit',$r['police_unit'])?>
    <?=rfield('Police contact',trim(($r['police_contact_name']?:'').($r['police_contact_phone']?' ('.$r['police_contact_phone'].')':'')))?>
    <?=rfield('Police reference',$r['police_reference'])?>
  </div>
  <div class="report-col">
    <h2>Response &amp; Recovery</h2>
    <?=rfield('Confirmed',$r['confirmed_at'])?>
    <?=rfield('Team',$r['rescue_team_name'])?>
    <?=rfield('Team contact',$r['rescue_team_contact'])?>
    <?=rfield('Dispatched',$r['team_dispatched_at'])?>
    <?=rfield('Recovered by',$r['recovered_by'])?>
    <?=rfield('Recovered',$r['recovered_at'])?>
    <?=rfield('Destination',$destination)?>
    <?=rfield('Mortuary bag',$r['mortuary_bag_no'])?>
    <?=rfield('Muchulka',$r['muchulka_reference'])?>
  </div>
</section>

<?php if (trim((string)$r['description'])!==''): ?>
<section class="report-block"><h2>Description</h2><p><?=nl2br(e($r['description']))?></p></section>
<?php endif; ?>

<?php if ($photos || $r['body_type']==='animal'): ?>
<section class="report-flex">
  <?php if ($photos): ?>
  <div class="report-col report-col-photos">
    <h2>Restricted Photos — internal use only</h2>
    <div class="report-photos"><?php foreach ($photos as $p): ?><img src="<?=e($base)?>/admin/photo.php?id=<?=e((string)$p['id'])?>" alt="Restricted evidence"><?php endforeach; ?></div>
  </div>
  <?php endif; ?>
  <?php if ($r['body_type']==='animal'): ?>
  <div class="report-col">
    <h2>Animal Carcass Assessment</h2>
    <?=rfield('Observed',$r['observed_at'])?>
    <?=rfield('Weather',weather_condition_labels()[$r['weather_condition']]??'')?>
    <?=rfield('Species',trim((animal_species_labels()[$r['animal_species']]??'').($r['animal_species']==='other'&&$r['animal_species_other']?' — '.$r['animal_species_other']:'')))?>
    <?=rfield('Size',estimated_size_labels()[$r['estimated_size']]??'')?>
    <?=rfield('Count',(string)$r['carcass_count'])?>
    <?=rfield('Decomposition',decomposition_state_labels()[$r['decomposition_state']]??'')?>
    <?=rfield('From water source',distance_water_source_labels()[$r['distance_water_source']]??'')?>
    <?=rfield('From settlement',distance_settlement_labels()[$r['distance_settlement']]??'')?>
    <?=rfield('Disposal method',trim((disposal_method_labels()[$r['disposal_method']]??'').($r['disposal_method_notes']?' — '.$r['disposal_method_notes']:'')))?>
    <?=rfield('Equipment needed',$equipment)?>
    <?=rfield('Disinfection',$disinfect)?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="report-block">
  <h2>Identification</h2>
  <?=rfield('Gender',$r['approx_gender']!=='unknown'?ucfirst((string)$r['approx_gender']):'')?>
  <?=rfield('Age range',$ageRange)?>
  <?=rfield('Condition',$r['body_condition'])?>
  <?=rfield('Clothes',$r['clothes'])?>
  <?=rfield('Marks',$r['identifying_marks'])?>
  <?=rfield('Documents',$r['documents_found'])?>
  <?=rfield('Identified as',$r['identified_name'])?>
</section>

<?php if ($hasBurial): ?>
<section class="report-block">
  <h2>Burial / Final Handling</h2>
  <?=rfield('Date',$r['buried_at'])?>
  <?=rfield('Location',$r['burial_location'])?>
  <?=rfield('GPS',$burialGps)?>
  <?=rfield('Handled by',$r['burial_by'])?>
  <?=rfield('Notes',$r['burial_notes'])?>
</section>
<?php endif; ?>

<?php if ($hasClosure): ?>
<section class="report-block">
  <h2>Closure / Correction</h2>
  <?=rfield('Closed',$r['closed_at'])?>
  <?=rfield('Reason',$r['closure_reason'])?>
  <?=rfield('False/incorrect report notes',$r['false_report_details'])?>
</section>
<?php endif; ?>

<footer class="report-footer"><?=e($config['app_name'] ?? '')?> · Confidential — restricted distribution · Generated <?=e(date('Y-m-d H:i'))?></footer>
</div>
<?php require dirname(__DIR__).'/includes/admin_footer.php'; ?>
