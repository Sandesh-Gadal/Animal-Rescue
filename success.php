<?php
require __DIR__.'/includes/bootstrap.php';
$id=(string)($_GET['id'] ?? '');
$s=$db->prepare('SELECT public_id,body_type,status,police_informed,created_at FROM body_reports WHERE public_id=?');
$s->execute([$id]); $r=$s->fetch();
if(!$r){http_response_code(404);exit('Report not found');}
$title='Report Submitted';
require __DIR__.'/includes/public_header.php';
$police=(string)($config['emergency']['police_control']??'100');
$toll=(string)($config['emergency']['police_toll_free']??'16600141516');
?>
<section class="section"><div class="container" style="max-width:760px"><div class="card">
<div class="success"><strong>Report received / सूचना प्राप्त भयो</strong></div>
<h1 class="case-id"><?=e($r['public_id'])?></h1>
<p>Please keep this Case ID. You can use it to track confirmation, dispatch, recovery and closure.</p>
<p><strong>Type:</strong> <?=e(body_type_labels()[$r['body_type']] ?? $r['body_type'])?><br><strong>Status:</strong> <?=e(status_labels()[$r['status']] ?? $r['status'])?></p>
<?php if($r['body_type']==='human'):?>
<div class="warning"><strong>Do not disturb the scene.</strong> If Nepal Police has not yet been informed, contact them now. The official Nepal Police emergency listing shows Police Control <strong><?=e($police)?></strong> and toll-free <strong><?=e($toll)?></strong>.</div>
<div class="grid grid-2" style="margin-top:14px"><a class="btn btn-primary btn-block" href="tel:<?=e($police)?>">Call Nepal Police <?=e($police)?></a><a class="btn btn-dark btn-block" href="tel:<?=e($toll)?>">Call Toll-Free <?=e($toll)?></a></div>
<?php if($r['police_informed']):?><div class="success" style="margin-top:14px">You indicated that Nepal Police has already been informed.</div><?php endif;?>
<?php endif;?>
<div style="margin-top:18px">
<a class="btn btn-dark" href="../case/<?=rawurlencode($r['public_id'])?>">Track This Case</a>
</div>
</div></div></section>
<?php require __DIR__.'/includes/public_footer.php'; ?>
