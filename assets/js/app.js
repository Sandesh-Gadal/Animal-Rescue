let reportLocationMap = null;
let reportLocationMarker = null;
let locationWatchId = null;
let locationWatchStopTimer = null;
let lastFocusedBeforeDialog = null;

function setGpsUi(type, text) {
  const status = document.getElementById('gps-status');
  const msg = document.getElementById('gps-message');
  if (msg) msg.textContent = text;
  if (status) status.className = 'gps-status gps-' + type;
}

function showPermissionHelp(show = true) {
  const el = document.getElementById('permission-help');
  if (el) el.style.display = show ? 'block' : 'none';
  const retry = document.getElementById('retry-location');
  if (retry) retry.style.display = show ? 'inline-flex' : 'none';
}

function showLocationGate(show = true) {
  const gate = document.getElementById('location-permission-gate');
  if (!gate) return;
  gate.classList.toggle('is-hidden', !show);
  if (show) {
    lastFocusedBeforeDialog = document.activeElement;
    document.body.style.overflow = 'hidden';
    window.setTimeout(() => gate.querySelector('button')?.focus(), 40);
  } else {
    document.body.style.overflow = '';
    if (lastFocusedBeforeDialog instanceof HTMLElement && document.contains(lastFocusedBeforeDialog)) {
      lastFocusedBeforeDialog.focus({ preventScroll: true });
    }
  }
}

function dismissLocationGate() {
  showLocationGate(false);
  setGpsUi('manual', 'GPS not selected. Tap the map or enter coordinates manually.');
  document.getElementById('report-location-map')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function requestExactLocation() {
  showLocationGate(false);
  fetchLocation(true);
}

function updateLocationFields(coords, source = 'gps') {
  const lat = document.getElementById('latitude');
  const lng = document.getElementById('longitude');
  const acc = document.getElementById('gps_accuracy');
  const alt = document.getElementById('altitude');
  const src = document.getElementById('location_source');
  if (!lat || !lng) return;

  lat.value = Number(coords.latitude).toFixed(7);
  lng.value = Number(coords.longitude).toFixed(7);
  if (acc) acc.value = coords.accuracy != null && Number.isFinite(Number(coords.accuracy)) ? Number(coords.accuracy).toFixed(1) : '';
  if (alt) alt.value = coords.altitude != null && Number.isFinite(Number(coords.altitude)) ? Number(coords.altitude).toFixed(1) : '';
  if (src) src.value = source;

  syncReportMap(Number(coords.latitude), Number(coords.longitude), source === 'gps');
}

function syncReportMap(lat, lng, zoomTo = true) {
  if (!reportLocationMap || !Number.isFinite(lat) || !Number.isFinite(lng)) return;
  if (!reportLocationMarker) {
    reportLocationMarker = L.marker([lat, lng], { draggable: true, title: 'Selected report location' }).addTo(reportLocationMap);
    reportLocationMarker.on('dragend', function (e) {
      const p = e.target.getLatLng();
      setManualMapLocation(p.lat, p.lng);
    });
  } else {
    reportLocationMarker.setLatLng([lat, lng]);
  }
  if (zoomTo) reportLocationMap.setView([lat, lng], 17);
}

function setManualMapLocation(lat, lng) {
  updateLocationFields({ latitude: lat, longitude: lng, accuracy: null, altitude: null }, 'map');
  setGpsUi('manual', 'Map location selected. Drag the marker if you need to correct it.');
}

function initReportLocationMap() {
  const mapEl = document.getElementById('report-location-map');
  if (!mapEl || typeof L === 'undefined') return;
  reportLocationMap = L.map(mapEl, { tap: true }).setView([28.3949, 84.1240], 7);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap'
  }).addTo(reportLocationMap);
  reportLocationMap.on('click', function (e) {
    setManualMapLocation(e.latlng.lat, e.latlng.lng);
  });

  const lat = parseFloat(document.getElementById('latitude')?.value || '');
  const lng = parseFloat(document.getElementById('longitude')?.value || '');
  if (Number.isFinite(lat) && Number.isFinite(lng)) syncReportMap(lat, lng, true);
}

function stopLocationWatch() {
  if (locationWatchId !== null && navigator.geolocation) {
    navigator.geolocation.clearWatch(locationWatchId);
    locationWatchId = null;
  }
  if (locationWatchStopTimer) {
    clearTimeout(locationWatchStopTimer);
    locationWatchStopTimer = null;
  }
}

function captureBestLocation(position) {
  const currentAccuracy = parseFloat(document.getElementById('gps_accuracy')?.value || '999999');
  const newAccuracy = Number(position.coords.accuracy || 999999);
  const source = document.getElementById('location_source')?.value || 'unknown';
  if (!document.getElementById('latitude')?.value || source !== 'gps' || newAccuracy <= currentAccuracy) {
    updateLocationFields(position.coords, 'gps');
  }
  const accuracyText = Number.isFinite(newAccuracy) && newAccuracy < 999999 ? Math.round(newAccuracy) + ' m' : 'unknown';
  setGpsUi('success', 'Exact device location captured. Reported accuracy: ' + accuracyText + '.');
  showPermissionHelp(false);
  showLocationGate(false);

  const btn = document.getElementById('fetch-location');
  if (btn) {
    btn.disabled = false;
    btn.textContent = '📍 Refresh Exact Location';
  }
}

function startAccuracyImprovementWatch() {
  if (!navigator.geolocation) return;
  stopLocationWatch();
  locationWatchId = navigator.geolocation.watchPosition(
    captureBestLocation,
    function () {},
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
  locationWatchStopTimer = setTimeout(stopLocationWatch, 15000);
}

function locationErrorMessage(error) {
  if (!error) return 'Location could not be obtained.';
  switch (error.code) {
    case 1: return 'Location permission was denied or blocked.';
    case 2: return 'Your device could not determine its location. Turn on GPS/Location and try again.';
    case 3: return 'Location request timed out. Trying a network-assisted location…';
    default: return 'Location error: ' + (error.message || 'unknown error');
  }
}

function lowAccuracyFallback(btn) {
  navigator.geolocation.getCurrentPosition(
    function (position) {
      captureBestLocation(position);
      startAccuracyImprovementWatch();
    },
    function (error) {
      if (error.code === 1) {
        setGpsUi('error', 'Location permission is blocked. Enable it in browser/site settings, then tap Try Again.');
        showPermissionHelp(true);
      } else {
        setGpsUi('error', locationErrorMessage(error) + ' Select the point on the map if GPS remains unavailable.');
      }
      if (btn) {
        btn.disabled = false;
        btn.textContent = '📍 Use Exact Location';
      }
    },
    { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 }
  );
}

async function fetchLocation(force = false) {
  const btn = document.getElementById('fetch-location');
  if (!document.getElementById('latitude')) return;

  if (!window.isSecureContext && location.hostname !== 'localhost') {
    setGpsUi('error', 'Browser GPS requires HTTPS. Open this page using https:// and try again.');
    showPermissionHelp(false);
    return;
  }

  if (!navigator.geolocation) {
    setGpsUi('error', 'This browser does not support GPS location. Select the point on the map.');
    return;
  }

  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Requesting location…';
  }
  setGpsUi('waiting', 'Allow location access in the browser prompt.');
  showPermissionHelp(false);

  try {
    if (navigator.permissions && navigator.permissions.query) {
      const permission = await navigator.permissions.query({ name: 'geolocation' });
      if (permission.state === 'denied') {
        setGpsUi('error', 'Location permission is blocked for this website.');
        showPermissionHelp(true);
        if (btn) {
          btn.disabled = false;
          btn.textContent = '📍 Try Exact Location Again';
        }
        return;
      }
      permission.onchange = function () {
        if (permission.state === 'granted' && document.getElementById('location_source')?.value !== 'gps') fetchLocation(true);
      };
    }
  } catch (_) {}

  navigator.geolocation.getCurrentPosition(
    function (position) {
      captureBestLocation(position);
      startAccuracyImprovementWatch();
    },
    function (error) {
      if (error.code === 1) {
        setGpsUi('error', 'Location permission was denied/blocked. Enable Location for this website and try again.');
        showPermissionHelp(true);
        if (btn) {
          btn.disabled = false;
          btn.textContent = '📍 Try Exact Location Again';
        }
        return;
      }
      setGpsUi('waiting', locationErrorMessage(error));
      lowAccuracyFallback(btn);
    },
    { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
  );
}

async function prepareLocationPermission() {
  if (!document.getElementById('latitude')) return;
  if (!navigator.geolocation || !window.isSecureContext) {
    showLocationGate(false);
    return;
  }
  try {
    if (navigator.permissions && navigator.permissions.query) {
      const permission = await navigator.permissions.query({ name: 'geolocation' });
      if (permission.state === 'granted') {
        showLocationGate(false);
        fetchLocation(true);
        return;
      }
      if (permission.state === 'denied') {
        showLocationGate(false);
        setGpsUi('error', 'Location is blocked for this website. Re-enable it in site settings or use the map.');
        showPermissionHelp(true);
        return;
      }
    }
  } catch (_) {}
  showLocationGate(true);
}

function shareText(text, url) {
  if (navigator.share) {
    navigator.share({ text, url }).catch(() => {});
    return;
  }
  navigator.clipboard?.writeText((text + '\n' + url).trim()).then(() => announce('Share information copied to clipboard.'));
}

function announce(message) {
  let live = document.getElementById('global-live-region');
  if (!live) {
    live = document.createElement('div');
    live.id = 'global-live-region';
    live.className = 'sr-only';
    live.setAttribute('aria-live', 'polite');
    live.setAttribute('aria-atomic', 'true');
    document.body.appendChild(live);
  }
  live.textContent = '';
  window.setTimeout(() => { live.textContent = message; }, 30);
}

/* Accessibility preferences */
const A11Y_KEY = 'dbmap_a11y_v1';
function getA11yPrefs() {
  try { return JSON.parse(localStorage.getItem(A11Y_KEY) || '{}'); } catch (_) { return {}; }
}
function saveA11yPrefs(prefs) {
  try { localStorage.setItem(A11Y_KEY, JSON.stringify(prefs)); } catch (_) {}
}
function applyA11yPrefs() {
  const prefs = getA11yPrefs();
  document.body.dataset.fontScale = prefs.fontScale || 'normal';
  document.body.classList.toggle('a11y-high-contrast', !!prefs.highContrast);
  document.body.classList.toggle('a11y-reduce-motion', !!prefs.reduceMotion);
  const hc = document.getElementById('a11y-high-contrast');
  const rm = document.getElementById('a11y-reduce-motion');
  if (hc) hc.checked = !!prefs.highContrast;
  if (rm) rm.checked = !!prefs.reduceMotion;
  document.querySelectorAll('[data-font-choice]').forEach(btn => {
    btn.setAttribute('aria-pressed', String((prefs.fontScale || 'normal') === btn.dataset.fontChoice));
  });
}
function setTextSize(size) {
  if (!['normal','large','xlarge'].includes(size)) size = 'normal';
  const prefs = getA11yPrefs(); prefs.fontScale = size; saveA11yPrefs(prefs); applyA11yPrefs(); announce('Text size changed.');
}
function setHighContrast(enabled) {
  const prefs = getA11yPrefs(); prefs.highContrast = !!enabled; saveA11yPrefs(prefs); applyA11yPrefs(); announce(enabled ? 'High contrast enabled.' : 'High contrast disabled.');
}
function setReduceMotion(enabled) {
  const prefs = getA11yPrefs(); prefs.reduceMotion = !!enabled; saveA11yPrefs(prefs); applyA11yPrefs(); announce(enabled ? 'Reduced motion enabled.' : 'Reduced motion disabled.');
}
function resetAccessibility() {
  saveA11yPrefs({fontScale:'normal',highContrast:false,reduceMotion:false}); applyA11yPrefs(); announce('Accessibility settings reset.');
}
function toggleA11yPanel(force) {
  const panel = document.getElementById('a11y-panel');
  const trigger = document.getElementById('a11y-fab');
  if (!panel) return;
  const open = typeof force === 'boolean' ? force : panel.hidden;
  panel.hidden = !open;
  if (trigger) trigger.setAttribute('aria-expanded', String(open));
  if (open) panel.querySelector('button, input')?.focus(); else trigger?.focus();
}

function togglePublicMenu(force) {
  const menu = document.getElementById('public-nav');
  const backdrop = document.getElementById('public-menu-backdrop');
  const trigger = document.getElementById('public-menu-toggle');
  if (!menu) return;
  const open = typeof force === 'boolean' ? force : !menu.classList.contains('mobile-open');
  menu.classList.toggle('mobile-open', open);
  if (backdrop) backdrop.hidden = !open;
  if (trigger) trigger.setAttribute('aria-expanded', String(open));
  document.body.style.overflow = open ? 'hidden' : '';
  if (open) menu.querySelector('a, button')?.focus(); else trigger?.focus();
}

function toggleAdminMobileMenu(force) {
  const menu = document.getElementById('admin-mobile-menu');
  const backdrop = document.getElementById('admin-mobile-menu-backdrop');
  const trigger = document.getElementById('admin-menu-toggle');
  if (!menu) return;
  const open = typeof force === 'boolean' ? force : !menu.classList.contains('mobile-open');
  menu.classList.toggle('mobile-open', open);
  if (backdrop) backdrop.hidden = !open;
  if (trigger) trigger.setAttribute('aria-expanded', String(open));
  document.body.style.overflow = open ? 'hidden' : '';
  if (open) menu.querySelector('a, button')?.focus(); else trigger?.focus();
}

function initConnectivity() {
  const banner = document.getElementById('connectivity-banner');
  if (!banner) return;
  const sync = () => {
    banner.hidden = navigator.onLine;
    if (!navigator.onLine) announce('You are offline. Keep this page open and reconnect before submitting.');
  };
  window.addEventListener('online', sync);
  window.addEventListener('offline', sync);
  sync();
}

function initMobileNav() {
  const path = window.location.pathname.replace(/\/+$/, '') || '/';
  document.querySelectorAll('.mobile-bottom-nav a[data-path]').forEach(a => {
    const target = (a.dataset.path || '').replace(/\/+$/, '') || '/';
    const active = target === '/' ? path === target : path === target || path.startsWith(target + '/');
    a.classList.toggle('active', active);
    if (active) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
  });
}

function initReportSteps() {
  const sections = [...document.querySelectorAll('.report-step[id]')];
  const links = [...document.querySelectorAll('.report-step-nav a[href^="#"]')];
  if (!sections.length || !links.length) return;
  const setActive = id => links.forEach(a => a.setAttribute('aria-current', a.getAttribute('href') === '#' + id ? 'step' : 'false'));
  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver(entries => {
      const visible = entries.filter(e => e.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];
      if (visible) setActive(visible.target.id);
    }, { rootMargin: '-25% 0px -60% 0px', threshold: [0,.2,.5] });
    sections.forEach(s => obs.observe(s));
  }
}

function initPhotoPreview() {
  const inputs = ['report-photos-camera','report-photos-gallery','report-photos']
    .map(id => document.getElementById(id)).filter(Boolean);
  const grid = document.getElementById('photo-preview-grid');
  const status = document.getElementById('photo-preview-status');
  if (!inputs.length || !grid) return;
  const render = () => {
    grid.innerHTML = '';
    const files = inputs.flatMap(inp => [...inp.files]).filter(f => f.type.startsWith('image/'));
    files.forEach(file => {
      const wrap = document.createElement('div'); wrap.className = 'photo-preview';
      const img = document.createElement('img'); img.alt = 'Selected photo preview';
      const label = document.createElement('span'); label.textContent = file.name;
      const url = URL.createObjectURL(file); img.src = url; img.onload = () => URL.revokeObjectURL(url);
      wrap.append(img,label); grid.appendChild(wrap);
    });
    if (status) status.textContent = files.length ? `${files.length} photo${files.length===1?'':'s'} selected.` : 'No photos selected.';
  };
  inputs.forEach(inp => inp.addEventListener('change', render));
}

function initFormAccessibility() {
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('invalid', e => {
      if (!(e.target instanceof HTMLElement)) return;
      window.setTimeout(() => {
        e.target.scrollIntoView({behavior:'smooth',block:'center'});
        e.target.focus({preventScroll:true});
        announce('Please complete the highlighted required field.');
      }, 20);
    }, true);
  });
}

function initLocationDialogKeyboard() {
  const gate = document.getElementById('location-permission-gate');
  if (!gate) return;
  gate.addEventListener('keydown', e => {
    if (e.key !== 'Tab') return;
    const focusable = [...gate.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled])')];
    if (!focusable.length) return;
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    const panel = document.getElementById('a11y-panel');
    if (panel && !panel.hidden) toggleA11yPanel(false);
    const adminMenu = document.getElementById('admin-mobile-menu');
    if (adminMenu && adminMenu.classList.contains('mobile-open')) toggleAdminMobileMenu(false);
    const publicMenu = document.getElementById('public-nav');
    if (publicMenu && publicMenu.classList.contains('mobile-open')) togglePublicMenu(false);
  }
});

document.addEventListener('DOMContentLoaded', () => {
  applyA11yPrefs();
  initConnectivity();
  initMobileNav();
  initReportSteps();
  initPhotoPreview();
  initFormAccessibility();
  initLocationDialogKeyboard();

  const type = document.getElementById('body_type');
  const warning = document.getElementById('human-warning');
  const policeSection = document.getElementById('human-police-section');
  const animalSection = document.getElementById('animal-carcass-section');
  if (type) {
    const sync = () => {
      const human = type.value === 'human';
      const animal = type.value === 'animal';
      if (warning) warning.style.display = human ? 'block' : 'none';
      if (policeSection) policeSection.style.display = human ? 'block' : 'none';
      if (animalSection) animalSection.style.display = animal ? 'block' : 'none';
    };
    type.addEventListener('change', sync);
    sync();
  }

  initReportLocationMap();

  const latInput = document.getElementById('latitude');
  const lngInput = document.getElementById('longitude');
  if (latInput && lngInput) {
    const syncManualInputs = () => {
      const lat = parseFloat(latInput.value);
      const lng = parseFloat(lngInput.value);
      if (Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        const source = document.getElementById('location_source'); if (source) source.value = 'manual';
        const acc = document.getElementById('gps_accuracy'); if (acc) acc.value = '';
        syncReportMap(lat, lng, true);
      }
    };
    latInput.addEventListener('change', syncManualInputs);
    lngInput.addEventListener('change', syncManualInputs);
    prepareLocationPermission();
  }
});
