<?php
require_admin();
/** @var array $config supplied by the including page via bootstrap.php */
$base=app_base($config);
$u=admin_user();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#0f172a"><meta name="color-scheme" content="light"><title><?=e($title ?? 'Admin')?></title>
<link rel="stylesheet" href="<?=$base?>/assets/css/app.css?v=<?=e((string)@filemtime(__DIR__.'/../assets/css/app.css'))?>"><?php if(!empty($useLeaflet)):?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><?php endif;?></head><body>
<a class="skip-link" href="#admin-main-content">Skip to main content</a>
<div class="admin-mobile-top no-print"><button id="admin-menu-toggle" class="btn admin-menu-btn" type="button" aria-label="Open menu" aria-controls="admin-mobile-menu" aria-expanded="false" onclick="toggleAdminMobileMenu()">☰</button><strong><?=e($title ?? 'Dashboard')?></strong></div>
<div id="admin-mobile-menu-backdrop" class="admin-mobile-menu-backdrop no-print" hidden onclick="toggleAdminMobileMenu(false)"></div>
<div class="admin-shell"><aside class="sidebar no-print" id="admin-mobile-menu" aria-label="Admin navigation"><button class="admin-menu-close" type="button" aria-label="Close menu" onclick="toggleAdminMobileMenu(false)">×</button><div class="small" style="color:#9fb0c8;margin-bottom:18px"><?=e($u['name'] ?? '')?> · <?=e($u['role'] ?? '')?></div>
<a href="<?=$base?>/admin">Dashboard</a><?php if(($u['role']??'')==='admin'):?><a href="<?=$base?>/admin/users.php">Users</a><?php endif;?><?php if(can_edit()):?><a href="<?=$base?>/report">File Report</a><?php endif;?><a href="<?=$base?>/" target="_blank" rel="noopener">Public Map</a><a href="<?=$base?>/admin/export/csv">Export CSV</a><a href="<?=$base?>/admin/export/xlsx">Export Excel</a><a href="<?=$base?>/admin/export/json">Export JSON</a><a href="<?=$base?>/admin/export/kml">Export KML</a><a href="<?=$base?>/admin/logout">Logout</a></aside><main class="admin-main" id="admin-main-content" tabindex="-1">
