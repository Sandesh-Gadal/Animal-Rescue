<?php
require __DIR__.'/includes/bootstrap.php';
$title='Livestock Carcass Management';
$useLeaflet=true;
$simpleNav=true;
$stats=$db->query("SELECT COUNT(*) total, SUM(status IN ('new','verification_required')) pending, SUM(status IN ('confirmed','police_informed','team_dispatched')) in_progress, SUM(status IN ('recovered','shifted','buried','identified','closed')) rescued FROM body_reports")->fetch();
$rows=$db->query("SELECT public_id,body_type,status,latitude,longitude,location_text,created_at FROM body_reports WHERE status NOT IN ('false_report','invalid','duplicate','unable_to_locate') ORDER BY created_at DESC LIMIT 1000")->fetchAll();
$points=[];
foreach($rows as $r){$points[]=['id'=>$r['public_id'],'type'=>$r['body_type'],'status'=>$r['status'],'lat'=>public_coord((float)$r['latitude'],$config),'lng'=>public_coord((float)$r['longitude'],$config),'location'=>$r['location_text'],'created'=>$r['created_at']];}
require __DIR__.'/includes/public_header.php';
?>
<section class="hero"><div class="container">
<div class="badge">Public safety dashboard</div>
<h1>Livestock Carcass Management / पशु चौपाया शव व्यवस्थापन</h1>
<p>Current case counts across all human and animal reports.</p>
<div style="margin-top:14px"><a class="btn btn-primary" href="<?=app_base($config)?>/report">Report the Case / घटना पठाउनुहोस्</a></div>
</div></section>
<section class="section"><div class="container grid grid-3">
<div class="card"><div class="muted">Pending</div><div class="stats"><?=number_format((int)$stats['pending'])?></div></div>
<div class="card"><div class="muted">In progress</div><div class="stats"><?=number_format((int)$stats['in_progress'])?></div></div>
<div class="card"><div class="muted">Rescued / recovered</div><div class="stats"><?=number_format((int)$stats['rescued'])?></div></div>
</div></section>
<section class="section"><div class="container">
<h2 style="margin-top:0">Public Map / सार्वजनिक नक्सा</h2>
<div class="info">Human-report coordinates are intentionally reduced in precision. Exact GPS, reporter details and photographs are available only to authorized administrators.</div>
<div id="map" class="map" style="margin-top:16px"></div>
</div></section>
<script>
const points=<?=json_encode($points,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
document.addEventListener('DOMContentLoaded',()=>{
 const center=points.length?[points[0].lat,points[0].lng]:[28.3949,84.1240];
 const m=L.map('map').setView(center,points.length?8:7);
 L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(m);
 const bounds=[];
 points.forEach(p=>{const mk=L.marker([p.lat,p.lng]).addTo(m);mk.bindPopup(`<b>${p.id}</b><br>${p.type} · ${p.status}<br>${p.location||''}<br><a href="case/${encodeURIComponent(p.id)}">View case</a>`);bounds.push([p.lat,p.lng]);});
 if(bounds.length>1)m.fitBounds(bounds,{padding:[25,25]});
});
</script>
<?php require __DIR__.'/includes/public_footer.php'; ?>
