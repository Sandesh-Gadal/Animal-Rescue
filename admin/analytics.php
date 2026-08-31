<?php
require dirname(__DIR__).'/includes/bootstrap.php';require_admin();
$title='Analytics';

$type=$_GET['type']??'';$status=$_GET['status']??'';$q=trim((string)($_GET['q']??''));
$dateFrom=(string)($_GET['date_from']??'');$dateTo=(string)($_GET['date_to']??'');
if($dateFrom!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)) $dateFrom='';
if($dateTo!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)) $dateTo='';

$where=[];$params=[];
if(in_array($type,['human','animal','unsure'],true)){$where[]='body_type=?';$params[]=$type;}
if(array_key_exists($status,status_labels())){$where[]='status=?';$params[]=$status;}
if($q!==''){$where[]='(location_text LIKE ? OR landmark LIKE ?)';$params[]='%'.$q.'%';$params[]='%'.$q.'%';}
if($dateFrom!==''){$where[]='created_at >= ?';$params[]=$dateFrom.' 00:00:00';}
if($dateTo!==''){$where[]='created_at <= ?';$params[]=$dateTo.' 23:59:59';}
$whereSql=$where?(' WHERE '.implode(' AND ',$where)):'';
$andOrWhere=$where?' AND ':' WHERE ';
$filtered=(bool)$where;

function agg(PDO $db, string $sql, array $params): array {
    $s=$db->prepare($sql);$s->execute($params);return $s->fetchAll();
}

// Single-choice animal-assessment field -> pie chart data (label already mapped, sorted by count).
function breakdown(PDO $db, string $whereSql, string $andOrWhere, array $params, string $column, array $labels): array {
    $rows=agg($db,"SELECT $column c2,COUNT(*) c FROM body_reports$whereSql{$andOrWhere}body_type='animal' AND $column IS NOT NULL GROUP BY $column ORDER BY c DESC",$params);
    $out=['labels'=>[],'values'=>[]];
    foreach($rows as $r){
        if($r['c2']===null) continue;
        $out['labels'][]=$labels[$r['c2']]??$r['c2'];
        $out['values'][]=(int)$r['c'];
    }
    return $out;
}

// Multi-select (comma-separated) animal-assessment field -> bar chart tally.
function tally(PDO $db, string $whereSql, string $andOrWhere, array $params, string $column, array $labels): array {
    $rows=agg($db,"SELECT $column FROM body_reports$whereSql{$andOrWhere}body_type='animal' AND $column IS NOT NULL AND $column<>''",$params);
    $counts=[];
    foreach($rows as $r){
        foreach(array_filter(explode(',',(string)$r[$column])) as $k){
            $counts[$k]=($counts[$k]??0)+1;
        }
    }
    arsort($counts);
    $out=['labels'=>[],'values'=>[]];
    foreach($counts as $k=>$c){ $out['labels'][]=$labels[$k]??$k; $out['values'][]=$c; }
    return $out;
}

function pie_card(string $id, string $title, array $data, string $ariaLabel, string $emptyMsg): void {
    echo '<div class="card"><h3 style="margin-top:0">'.e($title).'</h3>';
    if ($data['labels']) {
        echo '<div class="chart-canvas-wrap"><canvas id="chart-'.e($id).'" role="img" aria-label="'.e($ariaLabel).'"></canvas></div>';
        echo '<button class="btn" type="button" style="margin-top:12px" onclick="toggleTable(\'table-'.e($id).'\')">View as table</button>';
        echo '<div id="table-'.e($id).'" class="chart-table" hidden><table class="table"><thead><tr><th>Option</th><th>Count</th></tr></thead><tbody>';
        foreach ($data['labels'] as $i=>$lbl) echo '<tr><td>'.e($lbl).'</td><td>'.(int)$data['values'][$i].'</td></tr>';
        echo '</tbody></table></div>';
    } else {
        echo '<div class="info">'.e($emptyMsg).'</div>';
    }
    echo '</div>';
}

function bar_card(string $id, string $title, array $data, string $ariaLabel, string $emptyMsg): void {
    echo '<div class="card"><h3 style="margin-top:0">'.e($title).'</h3>';
    if ($data['labels']) {
        $h=max(220,count($data['labels'])*30);
        echo '<div class="chart-canvas-wrap" style="height:'.$h.'px"><canvas id="chart-'.e($id).'" role="img" aria-label="'.e($ariaLabel).'"></canvas></div>';
        echo '<button class="btn" type="button" style="margin-top:12px" onclick="toggleTable(\'table-'.e($id).'\')">View as table</button>';
        echo '<div id="table-'.e($id).'" class="chart-table" hidden><table class="table"><thead><tr><th>Option</th><th>Count</th></tr></thead><tbody>';
        foreach ($data['labels'] as $i=>$lbl) echo '<tr><td>'.e($lbl).'</td><td>'.(int)$data['values'][$i].'</td></tr>';
        echo '</tbody></table></div>';
    } else {
        echo '<div class="info">'.e($emptyMsg).'</div>';
    }
    echo '</div>';
}

$totalStmt=$db->prepare("SELECT COUNT(*) FROM body_reports$whereSql");
$totalStmt->execute($params);
$total=(int)$totalStmt->fetchColumn();

$byType=agg($db,"SELECT body_type,COUNT(*) c FROM body_reports$whereSql GROUP BY body_type",$params);
$byStatus=agg($db,"SELECT status,COUNT(*) c FROM body_reports$whereSql GROUP BY status ORDER BY c DESC",$params);
$byPolice=agg($db,"SELECT police_informed,COUNT(*) c FROM body_reports$whereSql{$andOrWhere}body_type='human' GROUP BY police_informed",$params);
$topLocations=agg($db,"SELECT location_text,COUNT(*) c FROM body_reports$whereSql{$andOrWhere}location_text IS NOT NULL AND location_text<>'' GROUP BY location_text ORDER BY c DESC LIMIT 10",$params);
$bySpecies=agg($db,"SELECT animal_species,COUNT(*) c FROM body_reports$whereSql{$andOrWhere}body_type='animal' AND animal_species IS NOT NULL GROUP BY animal_species ORDER BY c DESC",$params);
$overTime=agg($db,"SELECT DATE(created_at) d,COUNT(*) c FROM body_reports$whereSql GROUP BY DATE(created_at) ORDER BY d",$params);

$weatherData=breakdown($db,$whereSql,$andOrWhere,$params,'weather_condition',weather_condition_labels());
$sizeData=breakdown($db,$whereSql,$andOrWhere,$params,'estimated_size',estimated_size_labels());
$decompData=breakdown($db,$whereSql,$andOrWhere,$params,'decomposition_state',decomposition_state_labels());
$waterData=breakdown($db,$whereSql,$andOrWhere,$params,'distance_water_source',distance_water_source_labels());
$settlementData=breakdown($db,$whereSql,$andOrWhere,$params,'distance_settlement',distance_settlement_labels());
$disposalData=breakdown($db,$whereSql,$andOrWhere,$params,'disposal_method',disposal_method_labels());
$equipmentData=tally($db,$whereSql,$andOrWhere,$params,'equipment_needed',equipment_needed_labels());
$disinfectData=tally($db,$whereSql,$andOrWhere,$params,'disinfection_materials',disinfection_materials_labels());

$typeLbls=body_type_labels();
$typeCounts=['human'=>0,'animal'=>0,'unsure'=>0];
$typeData=['labels'=>[],'values'=>[]];
foreach($byType as $r){
    $typeCounts[$r['body_type']]=(int)$r['c'];
    $typeData['labels'][]=$typeLbls[$r['body_type']]??$r['body_type'];
    $typeData['values'][]=(int)$r['c'];
}

$statusLbls=status_labels();
$statusData=['labels'=>[],'values'=>[]];
foreach($byStatus as $r){
    $statusData['labels'][]=$statusLbls[$r['status']]??$r['status'];
    $statusData['values'][]=(int)$r['c'];
}

$policeCounts=['1'=>0,'0'=>0];
foreach($byPolice as $r){ $policeCounts[(string)$r['police_informed']]=(int)$r['c']; }
$policeData=['labels'=>['Informed','Not informed'],'values'=>[$policeCounts['1'],$policeCounts['0']]];

$locData=['labels'=>[],'values'=>[]];
foreach($topLocations as $r){
    $locData['labels'][]=mb_strimwidth((string)$r['location_text'],0,40,'…');
    $locData['values'][]=(int)$r['c'];
}

$speciesLbls=animal_species_labels();
$speciesData=['labels'=>[],'values'=>[]];
foreach($bySpecies as $r){
    $speciesData['labels'][]=$speciesLbls[$r['animal_species']]??$r['animal_species'];
    $speciesData['values'][]=(int)$r['c'];
}

$timeData=['labels'=>[],'values'=>[]];
foreach($overTime as $r){
    $timeData['labels'][]=$r['d'];
    $timeData['values'][]=(int)$r['c'];
}

require dirname(__DIR__).'/includes/admin_header.php';
$base=app_base($config);
?>
<h1>Analytics</h1>

<div class="card" style="margin-top:14px"><form class="filters" method="get" role="search">
<div class="form-group"><label for="an-q">Location contains</label><input class="input" id="an-q" name="q" value="<?=e($q)?>" placeholder="Ward, village, road..."></div>
<div class="form-group"><label for="an-type">Category (body type)</label><select class="select" id="an-type" name="type"><option value="">All</option><?php foreach(body_type_labels() as $k=>$v):?><option value="<?=$k?>" <?=$type===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label for="an-status">Status</label><select class="select" id="an-status" name="status"><option value="">All</option><?php foreach(status_labels() as $k=>$v):?><option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div>
<div class="form-group"><label for="an-date-from">Date from</label><input class="input" type="date" id="an-date-from" name="date_from" value="<?=e($dateFrom)?>"></div>
<div class="form-group"><label for="an-date-to">Date to</label><input class="input" type="date" id="an-date-to" name="date_to" value="<?=e($dateTo)?>"></div>
<button class="btn btn-dark">Apply Filters</button>
<?php if($filtered):?><a class="btn" style="margin-top:0" href="<?=$base?>/admin/analytics.php">Clear filters</a><?php endif;?>
</form></div>

<div class="analytics-stats" style="margin-top:14px" aria-label="Filtered totals">
<div class="card"><div class="muted">Total Matching</div><div class="stats"><?=number_format($total)?></div></div>
<div class="card"><div class="muted">Human</div><div class="stats"><?=number_format($typeCounts['human'])?></div></div>
<div class="card"><div class="muted">Animal</div><div class="stats"><?=number_format($typeCounts['animal'])?></div></div>
<div class="card"><div class="muted">Unsure</div><div class="stats"><?=number_format($typeCounts['unsure'])?></div></div>
</div>

<div class="grid grid-2" style="margin-top:18px">
  <div class="card">
    <h3 style="margin-top:0">Body Type Breakdown</h3>
    <?php if($typeData['labels']):?>
    <div class="chart-canvas-wrap"><canvas id="chart-type" role="img" aria-label="Doughnut chart of report counts by body type"></canvas></div>
    <button class="btn" type="button" style="margin-top:12px" onclick="toggleTable('table-type')">View as table</button>
    <div id="table-type" class="chart-table" hidden><table class="table"><thead><tr><th>Type</th><th>Count</th></tr></thead><tbody>
      <?php foreach($typeData['labels'] as $i=>$lbl):?><tr><td><?=e($lbl)?></td><td><?=(int)$typeData['values'][$i]?></td></tr><?php endforeach;?>
    </tbody></table></div>
    <?php else:?><div class="info">No matching reports in this range.</div><?php endif;?>
  </div>
  <div class="card">
    <h3 style="margin-top:0">Nepal Police Notification (Human Reports)</h3>
    <?php if(array_sum($policeData['values'])>0):?>
    <div class="chart-canvas-wrap"><canvas id="chart-police" role="img" aria-label="Doughnut chart of Nepal Police notification status for human reports"></canvas></div>
    <button class="btn" type="button" style="margin-top:12px" onclick="toggleTable('table-police')">View as table</button>
    <div id="table-police" class="chart-table" hidden><table class="table"><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>
      <?php foreach($policeData['labels'] as $i=>$lbl):?><tr><td><?=e($lbl)?></td><td><?=(int)$policeData['values'][$i]?></td></tr><?php endforeach;?>
    </tbody></table></div>
    <?php else:?><div class="info">No human reports in this range.</div><?php endif;?>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <h3 style="margin-top:0">Reports Over Time</h3>
  <?php if($timeData['labels']):?>
  <div class="chart-canvas-wrap" style="height:260px"><canvas id="chart-time" role="img" aria-label="Line chart of report counts per day"></canvas></div>
  <button class="btn" type="button" style="margin-top:12px" onclick="toggleTable('table-time')">View as table</button>
  <div id="table-time" class="chart-table" hidden><table class="table"><thead><tr><th>Date</th><th>Count</th></tr></thead><tbody>
    <?php foreach($timeData['labels'] as $i=>$lbl):?><tr><td><?=e($lbl)?></td><td><?=(int)$timeData['values'][$i]?></td></tr><?php endforeach;?>
  </tbody></table></div>
  <?php else:?><div class="info">No matching reports in this range.</div><?php endif;?>
</div>

<div class="grid grid-2" style="margin-top:18px">
  <div class="card">
    <h3 style="margin-top:0">Status Breakdown</h3>
    <?php if($statusData['labels']):?>
    <div class="chart-canvas-wrap" style="height:<?=max(220,count($statusData['labels'])*28)?>px"><canvas id="chart-status" role="img" aria-label="Bar chart of report counts by status"></canvas></div>
    <button class="btn" type="button" style="margin-top:12px" onclick="toggleTable('table-status')">View as table</button>
    <div id="table-status" class="chart-table" hidden><table class="table"><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>
      <?php foreach($statusData['labels'] as $i=>$lbl):?><tr><td><?=e($lbl)?></td><td><?=(int)$statusData['values'][$i]?></td></tr><?php endforeach;?>
    </tbody></table></div>
    <?php else:?><div class="info">No matching reports in this range.</div><?php endif;?>
  </div>
  <div class="card">
    <h3 style="margin-top:0">Top Locations</h3>
    <?php if($locData['labels']):?>
    <div class="chart-canvas-wrap" style="height:<?=max(220,count($locData['labels'])*28)?>px"><canvas id="chart-loc" role="img" aria-label="Bar chart of the ten most frequently reported locations"></canvas></div>
    <button class="btn" type="button" style="margin-top:12px" onclick="toggleTable('table-loc')">View as table</button>
    <div id="table-loc" class="chart-table" hidden><table class="table"><thead><tr><th>Location</th><th>Count</th></tr></thead><tbody>
      <?php foreach($locData['labels'] as $i=>$lbl):?><tr><td><?=e($lbl)?></td><td><?=(int)$locData['values'][$i]?></td></tr><?php endforeach;?>
    </tbody></table></div>
    <?php else:?><div class="info">No location text recorded for matching reports.</div><?php endif;?>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <h3 style="margin-top:0">Animal Species Breakdown</h3>
  <?php if($speciesData['labels']):?>
  <div class="chart-canvas-wrap" style="height:<?=max(220,count($speciesData['labels'])*30)?>px"><canvas id="chart-species" role="img" aria-label="Bar chart of animal reports by species"></canvas></div>
  <button class="btn" type="button" style="margin-top:12px" onclick="toggleTable('table-species')">View as table</button>
  <div id="table-species" class="chart-table" hidden><table class="table"><thead><tr><th>Species</th><th>Count</th></tr></thead><tbody>
    <?php foreach($speciesData['labels'] as $i=>$lbl):?><tr><td><?=e($lbl)?></td><td><?=(int)$speciesData['values'][$i]?></td></tr><?php endforeach;?>
  </tbody></table></div>
  <?php else:?><div class="info">No animal reports with species recorded in this range.</div><?php endif;?>
</div>

<h2 style="margin:26px 0 4px">Animal Carcass Assessment — Response Breakdown</h2>
<p class="small muted" style="margin:0 0 14px">From the animal-report assessment form fields, matching the filters above.</p>

<div class="grid grid-2">
<?php
pie_card('weather','Weather Conditions',$weatherData,'Pie chart of weather conditions at time of observation','No weather data recorded in this range.');
pie_card('size','Estimated Size/Weight',$sizeData,'Pie chart of estimated carcass size','No size data recorded in this range.');
?>
</div>
<div class="grid grid-2" style="margin-top:18px">
<?php
pie_card('decomp','Decomposition State',$decompData,'Pie chart of decomposition state','No decomposition data recorded in this range.');
pie_card('water','Distance from Water Source',$waterData,'Pie chart of distance from water source','No distance-from-water data recorded in this range.');
?>
</div>
<div class="grid grid-2" style="margin-top:18px">
<?php
pie_card('settlement','Distance from Human Settlement',$settlementData,'Pie chart of distance from human settlement','No distance-from-settlement data recorded in this range.');
pie_card('disposal','Proposed Disposal Method',$disposalData,'Pie chart of proposed disposal method','No disposal method recorded in this range.');
?>
</div>
<div class="grid grid-2" style="margin-top:18px">
<?php
bar_card('equipment','Equipment Needed',$equipmentData,'Bar chart tally of equipment needed across animal reports','No equipment selections recorded in this range.');
bar_card('disinfect','Disinfection Materials Needed',$disinfectData,'Bar chart tally of disinfection materials needed','No disinfection material selections recorded in this range.');
?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.5.1/chart.umd.min.js"></script>
<script>
const D = <?=json_encode([
    'type'=>$typeData,'police'=>$policeData,'time'=>$timeData,'status'=>$statusData,'loc'=>$locData,'species'=>$speciesData,
    'weather'=>$weatherData,'size'=>$sizeData,'decomp'=>$decompData,'water'=>$waterData,'settlement'=>$settlementData,
    'disposal'=>$disposalData,'equipment'=>$equipmentData,'disinfect'=>$disinfectData,
], JSON_UNESCAPED_UNICODE)?>;
const PALETTE = { blue:'#2a78d6', blueLight:'#b7d3f6', orange:'#eb6834', aqua:'#1baf7a', yellow:'#eda100', good:'#0ca30c', warning:'#fab219', secondary:'#52514e', grid:'#e1e0d9' };
Chart.defaults.font.family = "system-ui, -apple-system, 'Segoe UI', sans-serif";
Chart.defaults.color = PALETTE.secondary;
Chart.defaults.responsive = true;
Chart.defaults.maintainAspectRatio = false;

function toggleTable(id){ const el=document.getElementById(id); if(el) el.hidden=!el.hidden; }

function pctTooltip(ctx){
  const total = ctx.dataset.data.reduce((a,b)=>a+b,0);
  const pct = total ? Math.round(ctx.parsed/total*100) : 0;
  return `${ctx.label}: ${ctx.parsed} (${pct}%)`;
}
const barOptions = {
  indexAxis:'y',
  plugins:{legend:{display:false}},
  scales:{x:{beginAtZero:true,ticks:{precision:0},grid:{color:PALETTE.grid}},y:{grid:{display:false}}}
};

if (D.type.labels.length) new Chart(document.getElementById('chart-type'), {
  type:'doughnut',
  data:{ labels:D.type.labels, datasets:[{data:D.type.values, backgroundColor:[PALETTE.blue,PALETTE.orange,PALETTE.aqua], borderColor:'#fcfcfb', borderWidth:2}] },
  options:{ plugins:{legend:{position:'bottom'}, tooltip:{callbacks:{label:pctTooltip}}}, cutout:'62%' }
});

if (D.police.values.reduce((a,b)=>a+b,0) > 0) new Chart(document.getElementById('chart-police'), {
  type:'doughnut',
  data:{ labels:D.police.labels, datasets:[{data:D.police.values, backgroundColor:[PALETTE.good,PALETTE.warning], borderColor:'#fcfcfb', borderWidth:2}] },
  options:{ plugins:{legend:{position:'bottom'}, tooltip:{callbacks:{label:pctTooltip}}}, cutout:'62%' }
});

if (D.time.labels.length) new Chart(document.getElementById('chart-time'), {
  type:'line',
  data:{ labels:D.time.labels, datasets:[{data:D.time.values, borderColor:PALETTE.blue, backgroundColor:PALETTE.blueLight, fill:true, tension:0.25, pointRadius:2, pointBackgroundColor:PALETTE.blue}] },
  options:{ plugins:{legend:{display:false}}, scales:{x:{grid:{display:false}}, y:{beginAtZero:true,ticks:{precision:0},grid:{color:PALETTE.grid}}} }
});

if (D.status.labels.length) new Chart(document.getElementById('chart-status'), {
  type:'bar',
  data:{ labels:D.status.labels, datasets:[{data:D.status.values, backgroundColor:PALETTE.blue, borderRadius:4, maxBarThickness:24}] },
  options: barOptions
});

if (D.loc.labels.length) new Chart(document.getElementById('chart-loc'), {
  type:'bar',
  data:{ labels:D.loc.labels, datasets:[{data:D.loc.values, backgroundColor:PALETTE.blue, borderRadius:4, maxBarThickness:24}] },
  options: barOptions
});

if (D.species.labels.length) new Chart(document.getElementById('chart-species'), {
  type:'bar',
  data:{ labels:D.species.labels, datasets:[{data:D.species.values, backgroundColor:PALETTE.blue, borderRadius:4, maxBarThickness:26}] },
  options: barOptions
});

function renderPie(id, labels, values){
  const el = document.getElementById('chart-'+id);
  if (!el || !labels.length) return;
  new Chart(el, {
    type:'pie',
    data:{ labels, datasets:[{data:values, backgroundColor:[PALETTE.blue,PALETTE.orange,PALETTE.aqua,PALETTE.yellow], borderColor:'#fcfcfb', borderWidth:2}] },
    options:{ plugins:{legend:{position:'bottom'}, tooltip:{callbacks:{label:pctTooltip}}} }
  });
}
function renderBarChart(id, labels, values){
  const el = document.getElementById('chart-'+id);
  if (!el || !labels.length) return;
  new Chart(el, {
    type:'bar',
    data:{ labels, datasets:[{data:values, backgroundColor:PALETTE.blue, borderRadius:4, maxBarThickness:26}] },
    options: barOptions
  });
}

renderPie('weather', D.weather.labels, D.weather.values);
renderPie('size', D.size.labels, D.size.values);
renderPie('decomp', D.decomp.labels, D.decomp.values);
renderPie('water', D.water.labels, D.water.values);
renderPie('settlement', D.settlement.labels, D.settlement.values);
renderPie('disposal', D.disposal.labels, D.disposal.values);
renderBarChart('equipment', D.equipment.labels, D.equipment.values);
renderBarChart('disinfect', D.disinfect.labels, D.disinfect.values);
</script>
<?php require dirname(__DIR__).'/includes/admin_footer.php'; ?>
