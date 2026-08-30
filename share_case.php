<?php
require __DIR__.'/includes/bootstrap.php';
$token=(string)($_GET['token']??'');
if($token==='' || strlen($token)<30){http_response_code(404);exit('Share link not found.');}
$hash=hash('sha256',$token);
$q=$db->prepare("SELECT l.*,r.public_id,r.body_type,r.latitude,r.longitude,r.gps_accuracy,r.location_source,r.location_text,r.landmark,r.description,r.status,r.police_unit,r.rescue_team_name,r.team_dispatched_at,r.recovered_at,r.destination_type,r.destination_name,r.buried_at,r.burial_location,r.identified_name FROM case_share_links l JOIN body_reports r ON r.id=l.report_id WHERE l.token_hash=? AND l.revoked_at IS NULL AND l.expires_at>NOW() LIMIT 1");
$q->execute([$hash]);$r=$q->fetch();
if(!$r){http_response_code(410);header('Cache-Control: no-store');exit('This secure share link is invalid, expired or revoked.');}
$db->prepare('UPDATE case_share_links SET access_count=access_count+1,last_accessed_at=NOW() WHERE id=?')->execute([$r['id']]);
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Cache-Control: no-store, private');
$title='Secure Case '.$r['public_id'];$useLeaflet=true;require __DIR__.'/includes/public_header.php';
$maps=maps_url((float)$r['latitude'],(float)$r['longitude']);
?>
<section class="section"><div class="container" style="max-width:900px">
<div class="danger"><strong>Restricted operational location.</strong> This page contains exact coordinates shared for an authorized police/rescue response. Do not redistribute publicly.</div>
<div class="grid grid-2" style="margin-top:18px"><div class="card"><div class="muted">Case ID</div><h1 class="case-id"><?=e($r['public_id'])?></h1><p><strong>Type:</strong> <?=e(body_type_labels()[$r['body_type']]??$r['body_type'])?><br><strong>Status:</strong> <?=e(status_labels()[$r['status']]??$r['status'])?></p><h3>Exact GPS</h3><p class="case-id"><?=e($r['latitude'])?>, <?=e($r['longitude'])?></p><p><strong>Accuracy:</strong> <?=e((string)$r['gps_accuracy'])?> m · <strong>Source:</strong> <?=e($r['location_source'])?></p><p><strong>Location:</strong> <?=e($r['location_text'])?><br><strong>Landmark:</strong> <?=e($r['landmark'])?></p><p><?=nl2br(e($r['description']))?></p><a class="btn btn-primary btn-block" target="_blank" rel="noopener" href="<?=e($maps)?>">Navigate to Exact Location</a><p class="small muted">Link expires: <?=e($r['expires_at'])?></p></div><div class="card"><div id="map" class="map" style="height:430px"></div></div></div>
<div class="card" style="margin-top:18px"><h2>Current response information</h2><div class="grid grid-2"><p><strong>Police unit:</strong><br><?=e($r['police_unit']?:'Not recorded')?></p><p><strong>Rescue/recovery team:</strong><br><?=e($r['rescue_team_name']?:'Not recorded')?></p><p><strong>Team dispatched:</strong><br><?=e($r['team_dispatched_at']?:'Pending/not recorded')?></p><p><strong>Recovered:</strong><br><?=e($r['recovered_at']?:'Pending/not recorded')?></p><p><strong>Destination:</strong><br><?=e(trim(($r['destination_type']??'').' '.($r['destination_name']??'')))?></p><p><strong>Burial/final handling:</strong><br><?=e($r['buried_at']?:'Pending/not recorded')?> <?=e($r['burial_location']?:'')?></p></div></div>
</div></section>
<script>document.addEventListener('DOMContentLoaded',()=>{const lat=<?=(float)$r['latitude']?>,lng=<?=(float)$r['longitude']?>;const m=L.map('map').setView([lat,lng],17);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(m);L.marker([lat,lng]).addTo(m).bindPopup('Exact operational location').openPopup();});</script>
<?php require __DIR__.'/includes/public_footer.php'; ?>
