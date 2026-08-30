<?php
require dirname(__DIR__).'/includes/bootstrap.php';
require_admin();
$id=(int)($_GET['id']??0);
$s=$db->prepare('SELECT p.* FROM report_photos p WHERE p.id=? LIMIT 1');
$s->execute([$id]);$p=$s->fetch();
if(!$p){http_response_code(404);exit('Photo not found');}
$file=dirname(__DIR__).'/uploads/'.$p['stored_name'];
if(!is_file($file)){http_response_code(404);exit('File missing');}
header('Content-Type: '.$p['mime_type']);
header('Content-Length: '.filesize($file));
header('Content-Disposition: inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',($p['original_name']?:'evidence')).'"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($file);
