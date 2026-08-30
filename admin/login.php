<?php
require dirname(__DIR__).'/includes/bootstrap.php';
if(is_admin_logged_in()){header('Location: index.php');exit;}
$return=(string)($_GET['return'] ?? $_POST['return'] ?? '');
if($return==='' || $return[0]!=='/') $return='';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();
 $username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
 $s=$db->prepare('SELECT * FROM admin_users WHERE username=? AND active=1 LIMIT 1');$s->execute([$username]);$u=$s->fetch();
 if($u && password_verify($password,$u['password_hash'])){
   session_regenerate_id(true);
   $_SESSION['admin_user']=['id'=>(int)$u['id'],'name'=>$u['name'],'username'=>$u['username'],'role'=>$u['role'],'can_close'=>(int)$u['can_close']];
   audit_log($db,'login','admin_user',(string)$u['id']);
   header('Location: ' . ($return!==''?app_base($config).$return:'index.php'));exit;
 }
 $error='Invalid username or password.';
}
$base=app_base($config);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#0f172a"><title>Login</title><link rel="stylesheet" href="<?=$base?>/assets/css/app.css?v=<?=e((string)@filemtime(dirname(__DIR__).'/assets/css/app.css'))?>"></head><body>
<main class="container" style="max-width:460px;padding-top:clamp(24px,10vh,70px)" id="main-content"><form class="card" method="post"><div class="badge">Secure login</div><h1>Login</h1><p class="small muted">Admin and Operator staff log in here to manage cases and file reports.</p><?php if($error):?><div class="danger" role="alert"><?=e($error)?></div><?php endif;?><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="return" value="<?=e($return)?>"><div class="form-group"><label for="login-username">Username</label><input class="input" id="login-username" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required></div><div class="form-group"><label for="login-password">Password</label><input class="input" id="login-password" type="password" name="password" autocomplete="current-password" required></div><label class="privacy" for="show-password"><input type="checkbox" id="show-password" onchange="document.getElementById('login-password').type=this.checked?'text':'password'"><span>Show password</span></label><button class="btn btn-dark btn-block btn-lg" style="margin-top:14px">Login</button><a class="btn btn-block" style="margin-top:8px" href="<?=$base?>/">← Public Site</a></form></main>
<div id="global-live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div><script src="<?=$base?>/assets/js/app.js?v=<?=e((string)@filemtime(dirname(__DIR__).'/assets/js/app.js'))?>"></script></body></html>
