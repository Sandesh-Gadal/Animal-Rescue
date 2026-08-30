<?php
require dirname(__DIR__).'/includes/bootstrap.php';require_admin();
$title='Dashboard';
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
$perPage=10;
$countStmt=$db->prepare('SELECT COUNT(*) FROM body_reports'.($where?' WHERE '.implode(' AND ',$where):''));
$countStmt->execute($params);
$totalRows=(int)$countStmt->fetchColumn();
$totalPages=max(1,(int)ceil($totalRows/$perPage));
$page=max(1,min($totalPages,(int)($_GET['page']??1)));
$offset=($page-1)*$perPage;
$sql='SELECT * FROM body_reports'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY created_at DESC LIMIT '.$perPage.' OFFSET '.$offset;
$s=$db->prepare($sql);$s->execute($params);$rows=$s->fetchAll();
$stats=$db->query("SELECT COUNT(*) total,SUM(status='new') new_count,SUM(body_type='human') humans,SUM(body_type='human' AND police_informed=0 AND status NOT IN ('closed','false_report','invalid','duplicate')) police_pending,SUM(team_dispatched_at IS NOT NULL AND recovered_at IS NULL) dispatched,SUM(recovered_at IS NOT NULL) recovered,SUM(closed_at IS NOT NULL) closed_count FROM body_reports")->fetch();
$filtered=(bool)$where;
$filterParams=['type'=>$type,'status'=>$status,'police'=>$police,'q'=>$q,'date_from'=>$dateFrom,'date_to'=>$dateTo];
$exportQuery=http_build_query($filterParams);
require dirname(__DIR__).'/includes/admin_header.php';
$base=app_base($config);
$pageQuery=fn(int $p)=>$base.'/admin?'.http_build_query($filterParams+['page'=>$p]);
?>
<h1>Operations Dashboard</h1>
<div class="dashboard-stats" aria-label="Case statistics">
<div class="card"><div class="muted">Total</div><div class="stats"><?=(int)$stats['total']?></div></div>
<div class="card"><div class="muted">New</div><div class="stats"><?=(int)$stats['new_count']?></div></div>
<div class="card"><div class="muted">Human</div><div class="stats"><?=(int)$stats['humans']?></div></div>
<div class="card"><div class="muted">Police pending</div><div class="stats"><?=(int)$stats['police_pending']?></div></div>
<div class="card"><div class="muted">Dispatched</div><div class="stats"><?=(int)$stats['dispatched']?></div></div>
<div class="card"><div class="muted">Recovered</div><div class="stats"><?=(int)$stats['recovered']?></div></div>
<div class="card"><div class="muted">Closed</div><div class="stats"><?=(int)$stats['closed_count']?></div></div>
</div>

<div class="card" style="margin-top:14px"><form class="filters" method="get" role="search">
<div class="form-group"><label for="dash-q">Search cases</label><input class="input" id="dash-q" name="q" value="<?=e($q)?>" inputmode="search" enterkeyhint="search" placeholder="Case ID, phone, location, police/team"></div>
<div class="form-group"><label for="dash-type">Type</label><select class="select" id="dash-type" name="type"><option value="">All</option><?php foreach(body_type_labels() as $k=>$v):?><option value="<?=$k?>" <?=$type===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label for="dash-status">Status</label><select class="select" id="dash-status" name="status"><option value="">All</option><?php foreach(status_labels() as $k=>$v):?><option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label for="dash-police">Human police</label><select class="select" id="dash-police" name="police"><option value="">All</option><option value="pending" <?=$police==='pending'?'selected':''?>>Not informed/recorded</option></select></div>
<div class="form-group"><label for="dash-date-from">Date from</label><input class="input" type="date" id="dash-date-from" name="date_from" value="<?=e($dateFrom)?>"></div>
<div class="form-group"><label for="dash-date-to">Date to</label><input class="input" type="date" id="dash-date-to" name="date_to" value="<?=e($dateTo)?>"></div>
<button class="btn btn-dark">Search / Filter</button>
<?php if($filtered):?><a class="btn" style="margin-top:0" href="<?=$base?>/admin">Clear filters</a><?php endif;?>
</form>
<?php if($filtered):?>
<div style="margin-top:14px"><a class="btn btn-green" href="<?=$base?>/admin/export.php?format=csv&<?=$exportQuery?>">⇩ Download Filtered Results (CSV) — <?=count($rows)?> case<?=count($rows)===1?'':'s'?></a></div>
<?php endif;?>
</div>

<div class="mobile-case-list" style="margin-top:14px" aria-label="Case list">
<?php if(!$rows):?><div class="card"><strong>No matching cases.</strong><div class="muted">Change the search or filters.</div></div><?php endif;?>
<?php foreach($rows as $r):?>
<article class="mobile-case-card">
  <div class="mobile-case-head"><div><div class="case-id"><?=e($r['public_id'])?></div><span class="badge"><?=e(body_type_labels()[$r['body_type']]??$r['body_type'])?></span></div><span class="badge"><?=e(status_labels()[$r['status']]??$r['status'])?></span></div>
  <div class="next-action-callout"><small class="muted">NEXT REQUIRED ACTION</small><br><strong><?=e(workflow_next_label($r))?></strong></div>
  <div class="mobile-case-meta">
    <div><small>Location</small><strong><?=e($r['location_text']?:'GPS recorded')?></strong></div>
    <div><small>Reported</small><strong><?=e(substr((string)$r['created_at'],0,16))?></strong></div>
  </div>
  <?php if($r['reporter_phone']):?><div class="contact-actions" style="margin-bottom:9px"><a class="btn" href="tel:<?=e($r['reporter_phone'])?>">☎ Call Reporter</a></div><?php endif;?>
  <div class="case-actions">
    <a class="btn icon-btn" href="<?=$base?>/admin/case.php?id=<?=rawurlencode($r['public_id'])?>" aria-label="View case"><?=icon_view()?> View</a>
    <?php if(can_edit()):?><a class="btn btn-green icon-btn" href="<?=$base?>/admin/case.php?id=<?=rawurlencode($r['public_id'])?>#case-details" aria-label="Edit case"><?=icon_edit()?> Edit</a><?php endif;?>
    <?php if(can_close() && !is_terminal_status((string)$r['status'])):?><a class="btn btn-primary icon-btn" href="<?=$base?>/admin/case.php?id=<?=rawurlencode($r['public_id'])?>#fake-report-section" aria-label="Close case as false/invalid"><?=icon_delete()?> Delete</a><?php endif;?>
  </div>
</article>
<?php endforeach;?>
</div>

<div class="card desktop-case-table" style="margin-top:18px"><div class="table-wrap"><table class="table"><thead><tr><th>Case</th><th>Type</th><th>Status</th><th>Next action</th><th>Action</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><a class="case-id" href="<?=$base?>/admin/case.php?id=<?=rawurlencode($r['public_id'])?>"><?=e($r['public_id'])?></a></td><td><?=e($r['body_type'])?></td><td><?=e(status_labels()[$r['status']]??$r['status'])?></td><td><strong><?=e(workflow_next_label($r))?></strong></td><td class="table-actions">
<a class="btn icon-btn" href="<?=$base?>/admin/case.php?id=<?=rawurlencode($r['public_id'])?>" title="View" aria-label="View case"><?=icon_view()?></a>
<?php if(can_edit()):?><a class="btn btn-green icon-btn" href="<?=$base?>/admin/case.php?id=<?=rawurlencode($r['public_id'])?>#case-details" title="Edit" aria-label="Edit case"><?=icon_edit()?></a><?php endif;?>
<?php if(can_close() && !is_terminal_status((string)$r['status'])):?><a class="btn btn-primary icon-btn" href="<?=$base?>/admin/case.php?id=<?=rawurlencode($r['public_id'])?>#fake-report-section" title="Close as false/invalid" aria-label="Close case as false/invalid"><?=icon_delete()?></a><?php endif;?>
</td></tr><?php endforeach;?></tbody></table></div></div>

<?php if($totalPages>1):?>
<nav class="pagination" aria-label="Case list pages">
  <a class="btn icon-btn<?=$page<=1?' disabled':''?>" href="<?=e($pageQuery(max(1,$page-1)))?>" aria-label="Previous page"<?=$page<=1?' aria-disabled="true" tabindex="-1"':''?>>‹ Prev</a>
  <span class="pagination-status">Page <?=$page?> of <?=$totalPages?> · <?=$totalRows?> case<?=$totalRows===1?'':'s'?></span>
  <a class="btn icon-btn<?=$page>=$totalPages?' disabled':''?>" href="<?=e($pageQuery(min($totalPages,$page+1)))?>" aria-label="Next page"<?=$page>=$totalPages?' aria-disabled="true" tabindex="-1"':''?>>Next ›</a>
</nav>
<?php endif;?>
<?php require dirname(__DIR__).'/includes/admin_footer.php'; ?>
