<?php
require dirname(__DIR__).'/includes/bootstrap.php';
$count=(int)$db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
$key=(string)($_GET['key']??$_POST['key']??'');
if($count>0 || !hash_equals((string)$config['security']['setup_key'],$key)){http_response_code(403);exit('Setup unavailable or invalid setup key.');}
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();
 $name=trim((string)($_POST['name']??''));$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
 if($name!==''&&preg_match('/^[A-Za-z0-9_.-]{4,80}$/',$username)&&strlen($password)>=10){
  $s=$db->prepare("INSERT INTO admin_users(name,phone,office_name,post_title,username,password_hash,role,can_close) VALUES(?,?,?,?,?,?,'admin',1)");
  $s->execute([$name,trim((string)($_POST['phone']??'')),trim((string)($_POST['office_name']??'')),trim((string)($_POST['post_title']??'')),$username,password_hash($password,PASSWORD_DEFAULT)]);
  $msg='Admin created. Delete or rename the setup folder now.';
 } else $msg='Use a valid name, username (4+ chars) and password of at least 10 characters.';
}
$base=app_base($config);
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="<?=$base?>/assets/css/app.css"><title>Create Admin</title></head><body><div class="container" style="max-width:620px;padding:40px 0"><form class="card" method="post"><h1>Create first admin</h1><?php if($msg):?><div class="info"><?=e($msg)?></div><?php endif;?><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="key" value="<?=e($key)?>"><div class="form-group"><label>Name</label><input class="input" name="name" required></div><div class="grid grid-2"><div class="form-group"><label>Post</label><input class="input" name="post_title"></div><div class="form-group"><label>Office</label><input class="input" name="office_name"></div><div class="form-group"><label>Phone</label><input class="input" name="phone"></div><div class="form-group"><label>Username</label><input class="input" name="username" required></div></div><div class="form-group"><label>Password (10+ characters)</label><input class="input" type="password" name="password" required></div><button class="btn btn-dark">Create Admin</button></form></div></body></html>
