<?php
require __DIR__.'/includes/bootstrap.php';
$id=(string)($_GET['id'] ?? '');
$s=$db->prepare('SELECT * FROM body_reports WHERE public_id=?');$s->execute([$id]);$r=$s->fetch();
if(!$r){http_response_code(404);exit('Case not found');}
$h=$db->prepare('SELECT old_status,new_status,created_at FROM status_history WHERE report_id=? ORDER BY created_at ASC');
$h->execute([$r['id']]);$history=$h->fetchAll();
$title='Case '.$r['public_id'];$useLeaflet=true;
require __DIR__.'/includes/public_header.php';
$lat=public_coord((float)$r['latitude'],$config);$lng=public_coord((float)$r['longitude'],$config);
?>
<section class="section"><div class="container">
<div class="grid grid-2">
<div class="card"><div class="muted">Case ID</div><h1 class="case-id"><?=e($r['public_id'])?></h1>
<p><strong>Type:</strong> <?=e(body_type_labels()[$r['body_type']] ?? $r['body_type'])?></p>
<p><strong>Current status:</strong> <span class="badge"><?=e(status_labels()[$r['status']] ?? $r['status'])?></span></p>
<p><strong>Reported:</strong> <?=e($r['created_at'])?></p>
<p><strong>Area:</strong> <?=e($r['location_text'] ?: 'Approximate location shown on map')?></p>
<?php if($r['body_type']==='human'): ?><div class="info">For privacy and scene protection, this public page shows only an approximate coordinate. Reporter contact, exact GPS and body photographs are restricted to authorized responders.</div><?php endif; ?>
</div>
<div class="card"><div id="map" class="map" style="height:340px"></div></div>
</div>

<div class="card" style="margin-top:18px"><h2>Response tracking / केस प्रगति</h2>
<div class="milestone-grid public-milestones <?=$r['body_type']==='animal'?'milestone-grid-5':''?>">
  <div class="milestone <?=!empty($r['confirmed_at'])?'done':''?>"><strong>Confirmed</strong><span><?=e($r['confirmed_at'] ?: 'Pending')?></span></div>
  <?php if($r['body_type']==='human'): ?>
    <div class="milestone <?=!empty($r['police_informed'])?'done':''?>"><strong>Nepal Police informed</strong><span><?=e($r['police_informed_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['team_dispatched_at'])?'done':''?>"><strong>Team dispatched</strong><span><?=e($r['team_dispatched_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['recovered_at'])?'done':''?>"><strong>Recovered</strong><span><?=e($r['recovered_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=$r['status']==='shifted'||!empty($r['destination_name'])?'done':''?>"><strong>Official handover</strong><span><?=e($r['destination_name'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['closed_at'])?'done':''?>"><strong>Closed</strong><span><?=e($r['closed_at'] ?: 'Open')?></span></div>
  <?php elseif($r['body_type']==='animal'): ?>
    <div class="milestone <?=!empty($r['team_dispatched_at'])?'done':''?>"><strong>Team dispatched</strong><span><?=e($r['team_dispatched_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['recovered_at'])?'done':''?>"><strong>Recovered</strong><span><?=e($r['recovered_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['buried_at'])?'done':''?>"><strong>Buried / disposed</strong><span><?=e($r['buried_at'] ?: 'Pending')?></span></div>
    <div class="milestone <?=!empty($r['closed_at'])?'done':''?>"><strong>Closed</strong><span><?=e($r['closed_at'] ?: 'Open')?></span></div>
  <?php else: ?>
    <div class="milestone"><strong>Classification</strong><span>Pending</span></div>
    <div class="milestone"><strong>Response</strong><span>Pending</span></div>
    <div class="milestone"><strong>Recovery</strong><span>Pending</span></div>
    <div class="milestone"><strong>Final handling</strong><span>Pending</span></div>
    <div class="milestone <?=!empty($r['closed_at'])?'done':''?>"><strong>Closed</strong><span><?=e($r['closed_at'] ?: 'Open')?></span></div>
  <?php endif; ?>
</div>
<?php if($r['identified_name']):?><div class="success" style="margin-top:14px"><strong>Identification recorded:</strong> <?=e($r['identified_name'])?></div><?php endif;?>
<?php if(is_terminal_status((string)$r['status']) && $r['closure_reason']):?><div class="info" style="margin-top:14px"><strong>Closure:</strong> <?=e($r['closure_reason'])?></div><?php endif;?>
</div>

<div class="card" style="margin-top:18px"><h2>Status timeline</h2><div class="timeline"><?php foreach($history as $item):?><div class="timeline-item"><strong><?=e(status_labels()[$item['new_status']]??$item['new_status'])?></strong><span><?=e($item['created_at'])?></span></div><?php endforeach;?></div></div>
</div></section>
<script>
document.addEventListener('DOMContentLoaded',()=>{const m=L.map('map').setView([<?=$lat?>,<?=$lng?>],14);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(m);L.circle([<?=$lat?>,<?=$lng?>],{radius:<?=($r['body_type']==='human'?160:80)?>}).addTo(m).bindPopup('Approximate report area');});
</script>
<?php require __DIR__.'/includes/public_footer.php'; ?>
