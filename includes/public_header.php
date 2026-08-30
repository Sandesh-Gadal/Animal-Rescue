<?php
/** @var array $config supplied by the including page via bootstrap.php */
$base=app_base($config);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0f172a">
<meta name="color-scheme" content="light">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<title><?=e($title ?? $config['app_name'])?></title>
<link rel="stylesheet" href="<?=$base?>/assets/css/app.css?v=<?=e((string)@filemtime(__DIR__.'/../assets/css/app.css'))?>">
<?php if (!empty($useLeaflet)): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""><?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<?php if (empty($hideTopbar)): ?>
<header class="topbar">
  <div class="container row">
    <?php if (empty($simpleNav)): ?><button id="public-menu-toggle" class="btn public-menu-btn no-print" type="button" aria-label="Open menu" aria-controls="public-nav" aria-expanded="false" onclick="togglePublicMenu()">☰</button><?php endif; ?>
    <a class="brand" href="<?=$base?>/" aria-label="Home">Dead Body Reporting</a>
    <?php if (empty($simpleNav)): ?><div id="public-menu-backdrop" class="public-menu-backdrop no-print" hidden onclick="togglePublicMenu(false)"></div><?php endif; ?>
    <nav class="nav<?=!empty($simpleNav) ? ' nav-simple' : ''?>" id="public-nav" aria-label="Main navigation">
      <?php if (empty($simpleNav)): ?><button class="public-menu-close" type="button" aria-label="Close menu" onclick="togglePublicMenu(false)">×</button><?php endif; ?>
      <a class="<?=!empty($simpleNav) ? 'btn' : ''?>" href="<?=$base?>/">Home / गृहपृष्ठ</a>
      <?php if (is_admin_logged_in() && can_edit()): ?><a class="<?=!empty($simpleNav) ? 'btn' : ''?>" href="<?=$base?>/report">Report / सूचना</a><?php endif; ?>
      <?php if (is_admin_logged_in()): ?>
      <a class="<?=!empty($simpleNav) ? 'btn btn-primary' : ''?>" href="<?=$base?>/admin">Dashboard</a>
      <?php else: ?>
      <a class="<?=!empty($simpleNav) ? 'btn btn-primary' : ''?>" href="<?=$base?>/admin/login.php">Login / लगइन</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php endif; ?>
<main id="main-content" tabindex="-1">
