<?php
require dirname(__DIR__).'/includes/bootstrap.php';require_admin();
if((admin_user()['role']??'')!=='admin'){http_response_code(403);exit('Admin permission required.');}
$msg='';$msgType='info';
if($_SERVER['REQUEST_METHOD']==='POST'){
 csrf_check();
 $action=$_POST['action']??'create';
 if($action==='create'){
   $name=trim((string)($_POST['name']??''));$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');$passwordConfirm=(string)($_POST['password_confirm']??'');
   $role=in_array($_POST['role']??'viewer',['admin','operator','viewer'],true)?$_POST['role']:'viewer';
   if($name===''){$msg='Name is required.';$msgType='danger';}
   elseif(!preg_match('/^[A-Za-z0-9_.-]{4,80}$/',$username)){$msg='Username must be 4-80 characters using only letters, numbers, underscore, dot or hyphen (no spaces).';$msgType='danger';}
   elseif(strlen($password)<10){$msg='Password must be at least 10 characters.';$msgType='danger';}
   elseif($password!==$passwordConfirm){$msg='Password and confirmation do not match.';$msgType='danger';}
   else{
     try{$s=$db->prepare('INSERT INTO admin_users(name,phone,office_name,post_title,username,password_hash,role,can_close) VALUES(?,?,?,?,?,?,?,?)');
     $s->execute([$name,trim((string)($_POST['phone']??'')),trim((string)($_POST['office_name']??'')),trim((string)($_POST['post_title']??'')),$username,password_hash($password,PASSWORD_DEFAULT),$role,isset($_POST['can_close'])?1:0]);
     audit_log($db,'create_admin_user','admin_user',(string)$db->lastInsertId(),'Role '.$role);$msg='User created.';$msgType='success';}catch(Throwable $e){$msg='Could not create user. Username may already exist.';$msgType='danger';}
   }
 } elseif($action==='toggle'){
   $uid=(int)($_POST['user_id']??0);if($uid===(int)admin_user()['id']){$msg='You cannot deactivate your own account here.';$msgType='danger';}
   else{$s=$db->prepare('UPDATE admin_users SET active=IF(active=1,0,1) WHERE id=?');$s->execute([$uid]);audit_log($db,'toggle_admin_user','admin_user',(string)$uid);$msg='User status updated.';$msgType='success';}
 }
}
$users=$db->query('SELECT id,name,phone,office_name,post_title,username,role,can_close,active,created_at FROM admin_users ORDER BY created_at DESC')->fetchAll();
$title='Users';require dirname(__DIR__).'/includes/admin_header.php';
?>
<h1>Admin Users</h1><?php if($msg):?><div class="<?=e($msgType)?>" role="<?=$msgType==='danger'?'alert':'status'?>"><?=e($msg)?></div><?php endif;?>
<div class="grid grid-2" style="margin-top:18px"><form class="card" method="post"><h2>Create user</h2><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="create">
<div class="form-group"><label>Name</label><input class="input" name="name" autocomplete="name" required></div><div class="grid grid-2"><div class="form-group"><label>Post</label><input class="input" name="post_title"></div><div class="form-group"><label>Office</label><input class="input" name="office_name"></div><div class="form-group"><label>Phone</label><input class="input" name="phone" inputmode="tel" autocomplete="tel"></div><div class="form-group"><label>Role</label><select class="select" name="role"><option value="viewer">Viewer</option><option value="operator">Operator</option><option value="admin">Admin</option></select></div></div>
<div class="form-group"><label>Username</label><input class="input" name="username" autocomplete="username" required></div>
<div class="grid grid-2"><div class="form-group"><label for="new-user-password">Password (10+ characters)</label><input class="input" id="new-user-password" type="password" name="password" autocomplete="new-password" required></div><div class="form-group"><label for="new-user-password-confirm">Confirm password</label><input class="input" id="new-user-password-confirm" type="password" name="password_confirm" autocomplete="new-password" required></div></div>
<label class="privacy" for="show-new-user-password"><input type="checkbox" id="show-new-user-password" onchange="document.getElementById('new-user-password').type=this.checked?'text':'password';document.getElementById('new-user-password-confirm').type=this.checked?'text':'password'"><span>Show password</span></label>
<label style="margin-top:10px;display:block"><input type="checkbox" name="can_close" value="1"> Can close cases</label><div style="margin-top:16px"><button class="btn btn-dark">Create User</button></div></form>
<div class="card"><h2>Permissions</h2><p><strong>Admin:</strong> full case updates, user management, closure and exports.</p><p><strong>Operator:</strong> case updates and exports; closure only when explicitly allowed.</p><p><strong>Viewer:</strong> read-only dashboard, evidence and exports.</p></div></div>
<div class="card" style="margin-top:18px"><h2>Existing users</h2>
<div class="mobile-case-list"><?php foreach($users as $u):?><article class="mobile-case-card"><div class="mobile-case-head"><div><strong><?=e($u['name'])?></strong><div class="small muted">@<?=e($u['username'])?></div></div><span class="badge"><?=e($u['role'])?></span></div><div class="mobile-case-meta"><div><small>Office</small><strong><?=e($u['office_name']?:'—')?></strong></div><div><small>Can close</small><strong><?=$u['can_close']?'Yes':'No'?></strong></div><div><small>Active</small><strong><?=$u['active']?'Yes':'No'?></strong></div><div><small>Phone</small><strong><?=e($u['phone']?:'—')?></strong></div></div><?php if((int)$u['id']!==(int)admin_user()['id']):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?=(int)$u['id']?>"><button class="btn btn-block"><?=$u['active']?'Deactivate':'Activate'?></button></form><?php endif;?></article><?php endforeach;?></div>
<div class="table-wrap desktop-case-table"><table class="table"><tr><th>Name</th><th>Username</th><th>Role</th><th>Office</th><th>Close</th><th>Active</th><th></th></tr><?php foreach($users as $u):?><tr><td><?=e($u['name'])?></td><td><?=e($u['username'])?></td><td><?=e($u['role'])?></td><td><?=e($u['office_name'])?></td><td><?=$u['can_close']?'Yes':'No'?></td><td><?=$u['active']?'Yes':'No'?></td><td><?php if((int)$u['id']!==(int)admin_user()['id']):?><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="user_id" value="<?=(int)$u['id']?>"><button class="btn"><?=$u['active']?'Deactivate':'Activate'?></button></form><?php endif;?></td></tr><?php endforeach;?></table></div></div>
<?php require dirname(__DIR__).'/includes/admin_footer.php'; ?>
