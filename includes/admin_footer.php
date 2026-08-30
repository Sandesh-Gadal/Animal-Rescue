<?php /** @var array $config supplied by the including page via bootstrap.php */ ?>
</main></div>
<nav class="mobile-bottom-nav no-print" aria-label="Admin mobile navigation">
  <a href="<?=app_base($config)?>/" data-path="<?=app_base($config)?>"><span aria-hidden="true">⌂</span><span>Home</span></a>
  <a href="<?=app_base($config)?>/admin" data-path="<?=app_base($config)?>/admin"><span aria-hidden="true">▦</span><span>Dashboard</span></a>
  <?php if(can_edit()):?><a href="<?=app_base($config)?>/report" data-path="<?=app_base($config)?>/report"><span aria-hidden="true">＋</span><span>Report</span></a><?php endif;?>
  <a href="<?=app_base($config)?>/admin/logout"><span aria-hidden="true">↪</span><span>Logout</span></a>
</nav>
<button id="a11y-fab" class="a11y-fab no-print" type="button" aria-label="Open accessibility settings" aria-controls="a11y-panel" aria-expanded="false" onclick="toggleA11yPanel()">♿</button>
<section id="a11y-panel" class="a11y-panel no-print" hidden aria-label="Accessibility settings">
  <div class="a11y-panel-head"><h2>Accessibility / पहुँच</h2><button class="a11y-close" type="button" aria-label="Close accessibility settings" onclick="toggleA11yPanel(false)">×</button></div>
  <div class="a11y-buttons" role="group" aria-label="Text size"><button class="btn" type="button" data-font-choice="normal" onclick="setTextSize('normal')">A Normal</button><button class="btn" type="button" data-font-choice="large" onclick="setTextSize('large')">A+ Large</button><button class="btn" type="button" data-font-choice="xlarge" onclick="setTextSize('xlarge')">A++ XL</button></div>
  <div class="a11y-toggle-row"><label for="a11y-high-contrast">High contrast</label><input class="a11y-switch" id="a11y-high-contrast" type="checkbox" onchange="setHighContrast(this.checked)"></div>
  <div class="a11y-toggle-row"><label for="a11y-reduce-motion">Reduce motion</label><input class="a11y-switch" id="a11y-reduce-motion" type="checkbox" onchange="setReduceMotion(this.checked)"></div>
  <button class="btn btn-block" type="button" onclick="resetAccessibility()">Reset settings</button>
</section>
<div id="connectivity-banner" class="connectivity-banner" role="status" aria-live="assertive" hidden>You are offline — updates cannot be saved yet.</div>
<div id="global-live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>
<?php if(!empty($useLeaflet)):?><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><?php endif;?>
<script src="<?=app_base($config)?>/assets/js/app.js?v=<?=e((string)@filemtime(__DIR__.'/../assets/js/app.js'))?>"></script>
</body></html>
