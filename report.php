<?php
require __DIR__.'/includes/bootstrap.php';
$title='Report a Body';
$useLeaflet=true;
$hideTopbar=true;
$base=app_base($config);
$police=(string)($config['emergency']['police_control']??'100');
$reporter=[];
if (is_admin_logged_in()) {
    $s=$db->prepare('SELECT name,phone,office_name FROM admin_users WHERE id=?');
    $s->execute([admin_user()['id']]);
    $reporter=$s->fetch() ?: [];
}
require __DIR__.'/includes/public_header.php';
?>
<section class="section"><div class="container" style="max-width:860px">
<h1>Report Dead Body / मृत शरीरको सूचना</h1>
<div class="warning" role="note"><strong>Human remains:</strong> Do not touch, move, clean or disturb the body or nearby objects. Keep a safe distance and inform Nepal Police / responsible authorities. मानिसको शव भेटिएमा नछुनुहोस्, नसार्नुहोस् र घटनास्थल नबिगार्नुहोस्।</div>

<nav class="report-step-nav" aria-label="Report form sections">
  <a href="#step-type" aria-current="step">1 · Type</a>
  <a href="#step-location">2 · Location</a>
  <a href="#step-photos">3 · Photos</a>
  <a href="#step-contact">4 · Contact</a>
</nav>

<div id="location-permission-gate" class="location-gate" role="dialog" aria-modal="true" aria-labelledby="location-gate-title" aria-describedby="location-gate-description">
  <div class="location-gate-card">
    <div class="location-icon" aria-hidden="true">📍</div>
    <h2 id="location-gate-title">Share Exact Location / ठ्याक्कै स्थान दिनुहोस्</h2>
    <p id="location-gate-description">Tap once below, then choose <strong>Allow</strong> in the browser prompt. This is the fastest and most accurate way to report the location.</p>
    <button class="btn btn-primary btn-block" type="button" onclick="requestExactLocation()">Use My Exact Location / मेरो ठ्याक्कै स्थान</button>
    <button class="btn btn-block" type="button" style="margin-top:9px" onclick="dismissLocationGate()">Choose Location on Map Instead</button>
    <div class="small muted" style="margin-top:10px">A website cannot override a browser-level location denial. If permission is blocked, the app will guide you to re-enable it.</div>
  </div>
</div>

<form class="card" method="post" action="<?=$base?>/submit" enctype="multipart/form-data" style="margin-top:14px" id="body-report-form">
<input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
<div class="mobile-submit-bar"><button class="btn btn-primary btn-block" type="submit">Send Report / सूचना पठाउनुहोस्</button></div>

<section id="step-type" class="report-step" aria-labelledby="step-type-title">
  <h2 id="step-type-title" class="report-section-title"><span class="step-number" aria-hidden="true">1</span> What did you find? / के भेटियो?</h2>
  <div class="form-group"><label for="body_type">Body Type / प्रकार *</label><select class="select" id="body_type" name="body_type" required aria-required="true"><option value="">Select / छान्नुहोस्</option><option value="human">Human / मानिस</option><option value="animal">Animal / जनावर</option><option value="unsure">Unsure / यकिन छैन</option></select></div>
  <div id="human-warning" class="danger" style="display:none;margin-bottom:16px" role="alert"><strong>Do not approach unnecessarily.</strong> Photograph only from a safe and respectful distance. Exact photos and coordinates will not be publicly exposed.</div>
</section>

<section id="step-location" class="report-step" aria-labelledby="step-location-title">
<h2 id="step-location-title" class="report-section-title"><span class="step-number" aria-hidden="true">2</span> Exact Location / ठ्याक्कै स्थान</h2>
<div class="location-card">
  <div class="location-actions">
    <button class="btn btn-dark" type="button" id="fetch-location" onclick="fetchLocation(true)">📍 Use / Refresh Exact Location</button>
    <button class="btn" type="button" id="retry-location" onclick="fetchLocation(true)" style="display:none">Try Again / पुनः प्रयास</button>
  </div>
  <div id="gps-status" class="gps-status gps-waiting" role="status" aria-live="polite">
    <span class="gps-dot" aria-hidden="true"></span><span id="gps-message">Exact GPS has not been captured yet.</span>
  </div>
  <div id="permission-help" class="danger permission-help" style="display:none" role="alert">
    <strong>Location permission is blocked.</strong>
    <div>Allow Location for this website and try again, or select the exact point on the map.</div>
    <div class="small" style="margin-top:6px"><strong>Chrome/Edge:</strong> site controls/padlock → Site settings → Location → Allow → reload.</div>
    <div class="small"><strong>Android:</strong> also turn on phone Location/GPS.</div>
    <div class="small"><strong>iPhone/iPad:</strong> Settings → Privacy &amp; Security → Location Services → Safari Websites → While Using.</div>
    <button class="btn btn-dark" type="button" onclick="fetchLocation(true)" style="margin-top:10px">I Enabled It — Try Again</button>
  </div>
</div>

<div class="grid grid-2" style="margin-top:14px">
<div class="form-group"><label for="latitude">Latitude *</label><input class="input" id="latitude" name="latitude" inputmode="decimal" enterkeyhint="next" placeholder="e.g. 27.717245" required aria-required="true"></div>
<div class="form-group"><label for="longitude">Longitude *</label><input class="input" id="longitude" name="longitude" inputmode="decimal" enterkeyhint="next" placeholder="e.g. 85.323959" required aria-required="true"></div>
</div>
<input type="hidden" id="gps_accuracy" name="gps_accuracy"><input type="hidden" id="altitude" name="altitude">
<input type="hidden" id="location_source" name="location_source" value="unknown">

<div class="form-group">
  <label id="map-location-label">GPS unavailable? Tap or drag the map / GPS नचलेमा नक्सामा स्थान छान्नुहोस्</label>
  <div id="report-location-map" class="map report-location-map" role="application" aria-labelledby="map-location-label"></div>
  <div class="small muted" style="margin-top:7px">A manually selected map point is accepted. GPS is preferred because it also provides an accuracy estimate.</div>
</div>

<div class="form-group"><label for="location_text">Location / Landmark / स्थान विवरण</label><input class="input" id="location_text" name="location_text" maxlength="255" enterkeyhint="next" autocomplete="street-address" placeholder="River bank, road, village, bridge, ward, etc."></div>
<div class="form-group"><label for="landmark">Additional landmark / नजिकको चिनारी</label><input class="input" id="landmark" name="landmark" maxlength="255" enterkeyhint="next"></div>
<div class="form-group"><label for="description">Description / थप विवरण</label><textarea class="textarea" id="description" name="description" maxlength="3000" placeholder="Where exactly is it visible? Any immediate hazard?"></textarea></div>

<div id="human-police-section" class="card-lite" style="display:none">
  <h3 style="margin-top:0">Nepal Police notification / नेपाल प्रहरीलाई जानकारी</h3>
  <a class="btn btn-primary btn-block" href="tel:<?=e($police)?>" style="margin-bottom:12px">☎ Call Nepal Police <?=e($police)?></a>
  <label class="privacy" for="police_informed"><input type="checkbox" id="police_informed" name="police_informed" value="1"><span>I have already informed Nepal Police / मैले नेपाल प्रहरीलाई जानकारी गराइसकेको छु</span></label>
  <div class="grid grid-2" style="margin-top:14px">
    <div class="form-group"><label for="police_unit">Police unit/station (optional)</label><input class="input" id="police_unit" name="police_unit" maxlength="160"></div>
    <div class="form-group"><label for="police_reference">Police reference/contact (optional)</label><input class="input" id="police_reference" name="police_reference" maxlength="120"></div>
  </div>
  <div class="small muted">If police have not yet been informed, you can still submit the report first.</div>
</div>

<div id="animal-carcass-section" class="card-lite" style="display:none">
  <h3 style="margin-top:0">Animal Carcass Assessment / जनावरको शव मूल्यांकन</h3>

  <div class="grid grid-2">
    <div class="form-group"><label for="observed_at">Date/time observed / मिति र समय</label>
      <input class="input" type="datetime-local" id="observed_at" name="observed_at"></div>
    <div class="form-group"><label for="weather_condition">Weather conditions / मौसमको अवस्था</label>
      <select class="select" id="weather_condition" name="weather_condition">
        <option value="">Select / छान्नुहोस्</option>
        <?php foreach(weather_condition_labels() as $k=>$v): ?><option value="<?=$k?>"><?=e($v)?></option><?php endforeach; ?>
      </select></div>
  </div>

  <div class="grid grid-2">
    <div class="form-group"><label for="animal_species">Animal species / पशुको प्रजाति</label>
      <select class="select" id="animal_species" name="animal_species">
        <option value="">Select / छान्नुहोस्</option>
        <?php foreach(animal_species_labels() as $k=>$v): ?><option value="<?=$k?>"><?=e($v)?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label for="animal_species_other">If Other, specify / अन्य भए उल्लेख गर्नुहोस्</label>
      <input class="input" id="animal_species_other" name="animal_species_other" maxlength="120"></div>
  </div>

  <div class="grid grid-3">
    <div class="form-group"><label for="estimated_size">Estimated size/weight / अनुमानित तौल वा साइज</label>
      <select class="select" id="estimated_size" name="estimated_size">
        <option value="">Select / छान्नुहोस्</option>
        <?php foreach(estimated_size_labels() as $k=>$v): ?><option value="<?=$k?>"><?=e($v)?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label for="carcass_count">Number of carcasses found / कति वटा शव भेटियो?</label>
      <input class="input" type="number" min="1" id="carcass_count" name="carcass_count" value="1"></div>
    <div class="form-group"><label for="decomposition_state">Decomposition state / शवको अवस्था</label>
      <select class="select" id="decomposition_state" name="decomposition_state">
        <option value="">Select / छान्नुहोस्</option>
        <?php foreach(decomposition_state_labels() as $k=>$v): ?><option value="<?=$k?>"><?=e($v)?></option><?php endforeach; ?>
      </select></div>
  </div>

  <div class="grid grid-2">
    <div class="form-group"><label for="distance_water_source">Distance from water source / पानीको स्रोतबाट दूरी</label>
      <select class="select" id="distance_water_source" name="distance_water_source">
        <option value="">Select / छान्नुहोस्</option>
        <?php foreach(distance_water_source_labels() as $k=>$v): ?><option value="<?=$k?>"><?=e($v)?></option><?php endforeach; ?>
      </select>
      <div class="warning" style="margin-top:8px">Carcasses should be kept 200m+ from water sources to prevent epidemic/pollution risk. / भावी महामारी र जल प्रदूषण रोक्न शव व्यवस्थापन स्थल पानीको मुहानबाट कम्तीमा २०० मिटर टाढा हुनुपर्छ।</div>
    </div>
    <div class="form-group"><label for="distance_settlement">Distance from human settlement / मानव बस्तीबाट दूरी</label>
      <select class="select" id="distance_settlement" name="distance_settlement">
        <option value="">Select / छान्नुहोस्</option>
        <?php foreach(distance_settlement_labels() as $k=>$v): ?><option value="<?=$k?>"><?=e($v)?></option><?php endforeach; ?>
      </select></div>
  </div>

  <div class="grid grid-2">
    <div class="form-group"><label for="disposal_method">Proposed disposal method / प्रस्तावित व्यवस्थापन विधि</label>
      <select class="select" id="disposal_method" name="disposal_method">
        <option value="">Select / छान्नुहोस्</option>
        <?php foreach(disposal_method_labels() as $k=>$v): ?><option value="<?=$k?>"><?=e($v)?></option><?php endforeach; ?>
      </select></div>
    <div class="form-group"><label for="disposal_method_notes">If Other, specify / अन्य भए उल्लेख गर्नुहोस्</label>
      <input class="input" id="disposal_method_notes" name="disposal_method_notes" maxlength="255"></div>
  </div>

  <div class="form-group">
    <label>Equipment needed / आवश्यक पर्ने उपकरणहरू</label>
    <div class="check-grid">
    <?php foreach(equipment_needed_labels() as $k=>$v): ?>
      <label class="check-item"><input type="checkbox" name="equipment_needed[]" value="<?=$k?>"><span><?=e($v)?></span></label>
    <?php endforeach; ?>
    </div>
    <input class="input" name="equipment_needed_other" maxlength="120" placeholder="Other equipment / अन्य उपकरण" style="margin-top:8px">
  </div>

  <div class="form-group">
    <label>Disinfection materials needed / निशंक्रमण सामग्री</label>
    <div class="check-grid">
    <?php foreach(disinfection_materials_labels() as $k=>$v): ?>
      <label class="check-item"><input type="checkbox" name="disinfection_materials[]" value="<?=$k?>"><span><?=e($v)?></span></label>
    <?php endforeach; ?>
    </div>
  </div>
</div>
</section>

<section id="step-photos" class="report-step" aria-labelledby="step-photos-title">
<h2 id="step-photos-title" class="report-section-title"><span class="step-number" aria-hidden="true">3</span> Photos / फोटो</h2>
<div class="form-group">
  <label id="report-photos-label">Photos / फोटो</label>
  <div class="photo-source-actions">
    <label class="btn" for="report-photos-camera">📷 Take Photo / फोटो खिच्नुहोस्</label>
    <input class="sr-only" id="report-photos-camera" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple aria-describedby="photo-help photo-preview-status">
    <label class="btn" for="report-photos-gallery">🖼️ Choose from Gallery / ग्यालेरीबाट छान्नुहोस्</label>
    <input class="sr-only" id="report-photos-gallery" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="photo-help photo-preview-status">
  </div>
  <div id="photo-help" class="small muted">JPG/PNG/WEBP. Human-body photos are restricted to authorized staff.</div>
  <div id="photo-preview-status" class="small muted" role="status" aria-live="polite">No photos selected.</div>
  <div id="photo-preview-grid" class="photo-preview-grid" aria-hidden="true"></div>
</div>
</section>

<section id="step-contact" class="report-step" aria-labelledby="step-contact-title">
<h2 id="step-contact-title" class="report-section-title"><span class="step-number" aria-hidden="true">4</span> Your Contact / तपाईंको सम्पर्क</h2>
<div class="grid grid-2">
<div class="form-group"><label for="reporter_name">Name / नाम *</label><input class="input" id="reporter_name" name="reporter_name" maxlength="120" autocomplete="name" enterkeyhint="next" required aria-required="true" value="<?=e($reporter['name'] ?? '')?>"></div>
<div class="form-group"><label for="reporter_phone">Phone / फोन *</label><input class="input" id="reporter_phone" name="reporter_phone" inputmode="tel" autocomplete="tel" enterkeyhint="next" maxlength="30" required aria-required="true" value="<?=e($reporter['phone'] ?? '')?>"></div>
</div>
<div class="grid grid-2">
<div class="form-group"><label for="alternate_phone">Alternative phone</label><input class="input" id="alternate_phone" name="alternate_phone" inputmode="tel" autocomplete="tel" maxlength="30"></div>
<div class="form-group"><label for="reporter_organization">Organization (optional)</label><input class="input" id="reporter_organization" name="reporter_organization" maxlength="160" autocomplete="organization" value="<?=e($reporter['office_name'] ?? '')?>"></div>
</div>
<label class="privacy" for="reporter_private"><input type="checkbox" id="reporter_private" name="reporter_private" value="1" checked><span>Keep my identity/contact private from the public / मेरो नाम र फोन सार्वजनिक नदेखाउनुहोस्</span></label>
<div class="info" style="margin-top:14px">Before sending, confirm that the location marker is where the body was seen. You can correct the location on the map above.</div>
<div style="margin-top:20px"><button class="btn btn-primary btn-block btn-lg" type="submit">Submit Report / सूचना पठाउनुहोस्</button></div>
</section>
</form></div></section>
<?php require __DIR__.'/includes/public_footer.php'; ?>
