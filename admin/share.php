<?php
require dirname(__DIR__).'/includes/bootstrap.php';
require_admin();
if(!can_edit()) exit('Permission denied.');
$id=(string)($_GET['id']??'');
$s=$db->prepare('SELECT * FROM body_reports WHERE public_id=?');$s->execute([$id]);$r=$s->fetch();
if(!$r){http_response_code(404);exit('Case not found');}
$newShareUrl=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    if(isset($_POST['revoke_id'])){
        $rid=(int)$_POST['revoke_id'];
        $q=$db->prepare('UPDATE case_share_links SET revoked_at=NOW() WHERE id=? AND report_id=? AND revoked_at IS NULL');
        $q->execute([$rid,$r['id']]);
        audit_log($db,'revoke_case_share','body_report',$r['public_id'],'Share ID '.$rid);
        header('Location: '.app_base($config).'/admin/share/'.rawurlencode($r['public_id']).'?revoked=1');exit;
    }

    $recipientType=(string)($_POST['recipient_type']??'other');
    if(!in_array($recipientType,['police','rescue_team','other'],true)) $recipientType='other';
    $recipientName=trim((string)($_POST['recipient_name']??''));
    $recipientContact=trim((string)($_POST['recipient_contact']??''));
    $note=trim((string)($_POST['note']??''));
    $hours=(int)($_POST['hours']??24);
    $token=create_case_share($db,(int)$r['id'],$recipientType,$recipientName,$recipientContact,$note,$hours);
    $newShareUrl=app_base($config).'/share/'.$token;

    if($recipientType==='police'){
        $u=$db->prepare("UPDATE body_reports SET police_informed=1,police_informed_at=COALESCE(police_informed_at,NOW()),police_informed_source=IF(police_informed_source='reporter','reporter','admin'),location_shared_with_police_at=NOW(),police_unit=COALESCE(NULLIF(police_unit,''),?),police_contact_phone=COALESCE(NULLIF(police_contact_phone,''),?),last_edited_by=? WHERE id=?");
        $u->execute([$recipientName,$recipientContact,admin_user()['id']??null,$r['id']]);
    } elseif($recipientType==='rescue_team') {
        $u=$db->prepare("UPDATE body_reports SET location_shared_with_team_at=NOW(),rescue_team_name=COALESCE(NULLIF(rescue_team_name,''),?),rescue_team_contact=COALESCE(NULLIF(rescue_team_contact,''),?),last_edited_by=? WHERE id=?");
        $u->execute([$recipientName,$recipientContact,admin_user()['id']??null,$r['id']]);
    }
    audit_log($db,'create_case_share','body_report',$r['public_id'],'Recipient type '.$recipientType.'; recipient '.$recipientName);
}

$q=$db->prepare("SELECT l.*,u.name created_by_name FROM case_share_links l LEFT JOIN admin_users u ON u.id=l.created_by WHERE l.report_id=? ORDER BY l.created_at DESC");
$q->execute([$r['id']]);$links=$q->fetchAll();
$title='Share '.$r['public_id'];require dirname(__DIR__).'/includes/admin_header.php';
$maps=maps_url((float)$r['latitude'],(float)$r['longitude']);
?>
<div class="case-toolbar"><div><div class="muted">Secure exact-location sharing</div><h1 class="case-id"><?=e($r['public_id'])?></h1></div><div><a class="btn" href="<?=e(app_base($config))?>/admin/case.php?id=<?=rawurlencode($r['public_id'])?>">← Back to case</a></div></div>
<?php if(isset($_GET['revoked'])):?><div class="success" style="margin:14px 0">Share link revoked.</div><?php endif;?>
<?php if($newShareUrl):?>
<div class="success" style="margin:14px 0">
  <strong>Secure share link created.</strong> This raw link is shown only now. Send it only to the intended police/rescue recipient.
  <div class="share-box"><input class="input" id="new-share-url" value="<?=e($newShareUrl)?>" readonly><button class="btn btn-dark" type="button" onclick="navigator.clipboard.writeText(document.getElementById('new-share-url').value).then(()=>alert('Copied'))">Copy</button><button class="btn btn-primary" type="button" onclick="shareText('Exact location for case <?=e($r['public_id'])?>',document.getElementById('new-share-url').value)">Share</button></div>
</div>
<?php endif;?>

<div class="grid grid-2">
<div class="card"><h2>Exact location</h2><p class="case-id"><?=e($r['latitude'])?>, <?=e($r['longitude'])?></p><p><?=e($r['location_text'])?><br><?=e($r['landmark'])?></p><a class="btn btn-dark" target="_blank" rel="noopener" href="<?=e($maps)?>">Open Navigation</a><div class="warning" style="margin-top:14px">Human-case coordinates are sensitive. Do not post this secure link publicly or in open social-media groups.</div></div>
<div class="card"><h2>Create secure link</h2><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="form-group"><label>Recipient type</label><select class="select" name="recipient_type"><option value="police">Nepal Police</option><option value="rescue_team">Rescue / Recovery Team</option><option value="other">Other authorized recipient</option></select></div>
<div class="form-group"><label>Recipient / unit name</label><input class="input" name="recipient_name" maxlength="180" placeholder="Police station, APF unit, team name"></div>
<div class="form-group"><label>Contact number / identifier</label><input class="input" name="recipient_contact" maxlength="80"></div>
<div class="form-group"><label>Link validity</label><select class="select" name="hours"><option value="6">6 hours</option><option value="12">12 hours</option><option value="24" selected>24 hours</option><option value="48">48 hours</option><option value="72">72 hours</option><option value="168">7 days</option></select></div>
<div class="form-group"><label>Note</label><input class="input" name="note" maxlength="500" placeholder="Purpose / dispatch reference"></div>
<button class="btn btn-primary btn-block">Create Secure Exact-GPS Link</button></form></div>
</div>

<div class="card" style="margin-top:18px"><h2>Share history</h2>
<div class="mobile-case-list">
<?php foreach($links as $l):?><?php $expired=strtotime($l['expires_at'])<time();?><article class="mobile-case-card"><div class="mobile-case-head"><strong><?=e($l['recipient_type'])?> · <?=e($l['recipient_name']?:'Unnamed recipient')?></strong><span class="badge"><?=e($l['revoked_at']?'Revoked':($expired?'Expired':'Active'))?></span></div><div class="mobile-case-meta"><div><small>Created</small><strong><?=e($l['created_at'])?></strong></div><div><small>Expires</small><strong><?=e($l['expires_at'])?></strong></div><div><small>Opened</small><strong><?=(int)$l['access_count']?> time(s)</strong></div><div><small>Contact</small><strong><?=e($l['recipient_contact']?:'—')?></strong></div></div><?php if(!$l['revoked_at']&&!$expired):?><form method="post" onsubmit="return confirm('Revoke this share link?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="revoke_id" value="<?=(int)$l['id']?>"><button class="btn btn-block">Revoke Link</button></form><?php endif;?></article><?php endforeach;?>
</div>
<div class="table-wrap desktop-case-table"><table class="table"><thead><tr><th>Created</th><th>Recipient</th><th>Expires</th><th>Opened</th><th>Last opened</th><th>State</th><th></th></tr></thead><tbody><?php foreach($links as $l):?><?php $expired=strtotime($l['expires_at'])<time();?><tr><td><?=e($l['created_at'])?></td><td><?=e($l['recipient_type'])?> · <?=e($l['recipient_name'])?><br><span class="small muted"><?=e($l['recipient_contact'])?></span></td><td><?=e($l['expires_at'])?></td><td><?=(int)$l['access_count']?></td><td><?=e($l['last_accessed_at']?:'-')?></td><td><?=e($l['revoked_at']?'Revoked':($expired?'Expired':'Active'))?></td><td><?php if(!$l['revoked_at']&&!$expired):?><form method="post" onsubmit="return confirm('Revoke this share link?')"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="revoke_id" value="<?=(int)$l['id']?>"><button class="btn">Revoke</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div>
<?php require dirname(__DIR__).'/includes/admin_footer.php'; ?>
