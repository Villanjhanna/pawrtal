<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$myMatches = $conn->query("
    SELECT COUNT(*) AS c FROM matches m
    JOIN lost_reports l ON m.lost_report_id = l.id
    WHERE l.user_id=$user_id AND m.status='pending'
")->fetch_assoc()['c'];

$unreadCount = $conn->query("
    SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0
")->fetch_assoc()['c'];

// Fetch all active lost reports that have coordinates
$lostReports = $conn->query("
    SELECT l.id, l.pet_name, l.pet_type, l.breed, l.color, l.photo,
           l.last_seen_place, l.last_seen_date, l.lat, l.lng,
           u.name AS owner_name
    FROM lost_reports l
    JOIN users u ON l.user_id = u.id
    WHERE l.status='active' AND l.lat IS NOT NULL AND l.lng IS NOT NULL
")->fetch_all(MYSQLI_ASSOC);

// Fetch all active found reports that have coordinates
$foundReports = $conn->query("
    SELECT f.id, f.pet_type, f.breed, f.color, f.photo,
           f.found_place, f.found_date, f.contact_name, f.lat, f.lng
    FROM found_reports f
    WHERE f.status='active' AND f.lat IS NOT NULL AND f.lng IS NOT NULL
")->fetch_all(MYSQLI_ASSOC);

// Stats for the info bar
$totalLost    = count($lostReports);
$totalFound   = count($foundReports);
$allLostCount = $conn->query("SELECT COUNT(*) AS c FROM lost_reports WHERE status='active'")->fetch_assoc()['c'];
$allFoundCount= $conn->query("SELECT COUNT(*) AS c FROM found_reports WHERE status='active'")->fetch_assoc()['c'];

$name     = $_SESSION['name'];
$initials = pf_initials($name);

pf_head('Map');
?>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
<style>
#map { height: calc(100vh - 58px - 52px); width: 100%; }

/* Info bar above map */
.map-bar {
  background: var(--surface);
  border-bottom: .5px solid var(--border);
  padding: 10px 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}
.map-bar-title {
  font-family: var(--display);
  font-size: .95rem;
  color: var(--text);
  margin-right: 8px;
}
.map-stat {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text3);
}
.map-stat-dot {
  width: 10px; height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}
.dot-lost  { background: #8b2635; }
.dot-found { background: #2d6a4f; }
.map-stat strong { color: var(--text); }

.map-filter {
  display: flex;
  gap: 6px;
  margin-left: auto;
}
.filter-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 11px;
  border-radius: 99px;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  border: .5px solid var(--border-md);
  background: var(--surface);
  color: var(--text2);
  transition: all .12s;
  font-family: var(--font);
}
.filter-pill:hover { background: var(--surface2); }
.filter-pill.active-lost  { background: var(--red-lt);   color: var(--red);   border-color: var(--red); }
.filter-pill.active-found { background: var(--green-lt); color: var(--green); border-color: var(--green); }
.filter-pill.active-both  { background: var(--surface2); color: var(--text); }

.no-location-notice {
  background: var(--amber-lt);
  border: .5px solid rgba(146,64,14,.2);
  border-radius: var(--radius);
  padding: 8px 13px;
  font-size: 12px;
  color: var(--amber);
  display: flex;
  align-items: center;
  gap: 7px;
}

/* Leaflet popup overrides */
.leaflet-popup-content-wrapper {
  border-radius: 10px !important;
  box-shadow: 0 4px 20px rgba(0,0,0,.12) !important;
  padding: 0 !important;
  overflow: hidden;
}
.leaflet-popup-content { margin: 0 !important; width: 220px !important; }
.leaflet-popup-tip-container { display: none; }

.popup-img {
  width: 100%;
  height: 110px;
  object-fit: cover;
  background: #f2efeb;
  display: flex;
  align-items: center;
  justify-content: center;
}
.popup-img img { width: 100%; height: 100%; object-fit: cover; }
.popup-img i { font-size: 2.5rem; color: #ccc; }
.popup-body { padding: 12px 14px; }
.popup-pill {
  font-size: 10px; font-weight: 600;
  padding: 2px 8px; border-radius: 99px;
  text-transform: uppercase; letter-spacing: .06em;
  display: inline-block; margin-bottom: 6px;
}
.pill-lost  { background: #fef2f2; color: #8b2635; }
.pill-found { background: #f0fdf4; color: #2d6a4f; }
.popup-name { font-size: 14px; font-weight: 600; color: #1a1208; margin-bottom: 4px; font-family: 'DM Serif Display', Georgia, serif; }
.popup-meta { font-size: 11px; color: #8a7060; margin-bottom:3px; display: flex; align-items: center; gap: 4px; }
.popup-link {
  display: block; margin-top: 10px;
  text-align: center; padding: 7px;
  border-radius: 7px; font-size: 12px; font-weight: 500;
  text-decoration: none; transition: opacity .1s;
}
.popup-link:hover { opacity: .88; }
.link-lost  { background: #8b2635; color: #fff; }
.link-found { background: #2d6a4f; color: #fff; }
</style>
<body>

<aside class="sb">
  <div class="sb-brand">
    <div class="sb-logo"><i class="ti ti-paw"></i></div>
    <div><div class="sb-appname">Pawrtal</div><div class="sb-appsub">Dashboard</div></div>
  </div>
  <nav class="sb-nav">
    <div class="sb-sec">Overview</div>
    <a href="index.php"      class="sb-item"><i class="ti ti-home"></i> Dashboard</a>
    <div class="sb-sec">My activity</div>
    <a href="my_reports.php" class="sb-item"><i class="ti ti-clipboard-list"></i> My reports</a>
    <a href="matches.php"    class="sb-item">
      <i class="ti ti-link"></i> Match alerts
      <?php if ($myMatches > 0): ?><span class="sb-badge"><?= $myMatches ?></span><?php endif; ?>
    </a>
    <a href="my_pets.php"    class="sb-item"><i class="ti ti-paw"></i> My pets</a>
    <div class="sb-sec">Community</div>
    <a href="map.php"        class="sb-item active"><i class="ti ti-map"></i> Map</a>
    <a href="../public/lost.php"  class="sb-item"><i class="ti ti-search"></i> Browse reports</a>
    <div class="sb-sec">Account</div>
    <a href="notifications.php" class="sb-item">
      <i class="ti ti-bell"></i> Notifications
      <?php if ($unreadCount > 0): ?><span class="sb-badge"><?= $unreadCount ?></span><?php endif; ?>
    </a>
    <a href="../auth/logout.php" class="sb-item"><i class="ti ti-logout"></i> Logout</a>
  </nav>
  <div class="sb-foot">
    <div class="sb-user">
      <div class="sb-av"><?= htmlspecialchars($initials) ?></div>
      <div>
        <div class="sb-uname"><?= htmlspecialchars($name) ?></div>
        <div class="sb-uemail"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
      </div>
    </div>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div>
      <div class="topbar-title">Pet map</div>
      <div class="topbar-sub">Active lost and found reports near Bacolod City</div>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="report_lost.php"  class="btn primary"><i class="ti ti-alert-circle"></i> Report lost</a>
      <a href="report_found.php" class="btn success"><i class="ti ti-circle-check"></i> Report found</a>
    </div>
  </div>

  <!-- Map info bar -->
  <div class="map-bar">
    <span class="map-bar-title">Active reports</span>

    <div class="map-stat">
      <div class="map-stat-dot dot-lost"></div>
      <strong><?= $totalLost ?></strong> lost on map
      <?php if ($allLostCount > $totalLost): ?>
        <span style="color:var(--text3);">(<?= $allLostCount - $totalLost ?> without location)</span>
      <?php endif; ?>
    </div>

    <div class="map-stat">
      <div class="map-stat-dot dot-found"></div>
      <strong><?= $totalFound ?></strong> found on map
      <?php if ($allFoundCount > $totalFound): ?>
        <span style="color:var(--text3);">(<?= $allFoundCount - $totalFound ?> without location)</span>
      <?php endif; ?>
    </div>

    <div class="map-filter">
      <button class="filter-pill active-both" id="filterAll"   onclick="setFilter('all')">
        <i class="ti ti-map-pin" style="font-size:13px;"></i> All
      </button>
      <button class="filter-pill" id="filterLost"  onclick="setFilter('lost')">
        <i class="ti ti-alert-circle" style="font-size:13px;"></i> Lost only
      </button>
      <button class="filter-pill" id="filterFound" onclick="setFilter('found')">
        <i class="ti ti-circle-check" style="font-size:13px;"></i> Found only
      </button>
    </div>
  </div>

  <?php if ($allLostCount > $totalLost || $allFoundCount > $totalFound): ?>
  <div style="padding:8px 20px;background:var(--bg);">
    <div class="no-location-notice">
      <i class="ti ti-info-circle" style="font-size:16px;flex-shrink:0;"></i>
      Some reports don't have a pin location yet. Reports submitted before the map feature was added, or without a dropped pin, won't appear here.
    </div>
  </div>
  <?php endif; ?>

  <div id="map"></div>
</div>

<!-- Leaflet JS -->
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script>
// ── Map init ──────────────────────────────────────────────────────
const map = L.map('map', {
  center: [10.6713, 122.9511], // Bacolod City
  zoom: 13,
  zoomControl: true
});

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  maxZoom: 19
}).addTo(map);

// ── Custom markers ────────────────────────────────────────────────
function makeIcon(color) {
  return L.divIcon({
    className: '',
    html: `<div style="
      width:32px;height:32px;border-radius:50% 50% 50% 0;
      background:${color};
      border:2px solid #fff;
      box-shadow:0 2px 8px rgba(0,0,0,.25);
      transform:rotate(-45deg);
      display:flex;align-items:center;justify-content:center;
    "><div style="
      transform:rotate(45deg);
      width:12px;height:12px;
      background:rgba(255,255,255,.8);
      border-radius:50%;
    "></div></div>`,
    iconSize:   [32, 32],
    iconAnchor: [16, 32],
    popupAnchor:[0, -34]
  });
}

const lostIcon  = makeIcon('#8b2635');
const foundIcon = makeIcon('#2d6a4f');

// ── Build popup HTML ──────────────────────────────────────────────
function lostPopup(r) {
  const photo = r.photo
    ? `<div class="popup-img"><img src="../uploads/reports/${r.photo}" alt=""></div>`
    : `<div class="popup-img"><i class="ti ti-paw"></i></div>`;
  return `${photo}
  <div class="popup-body">
    <span class="popup-pill pill-lost">Lost</span>
    <div class="popup-name">${r.pet_name}</div>
    <div class="popup-meta"><i class="ti ti-paw" style="font-size:12px;"></i> ${r.pet_type}${r.breed ? ' · '+r.breed : ''}</div>
    <div class="popup-meta"><i class="ti ti-map-pin" style="font-size:12px;"></i> ${r.last_seen_place}</div>
    <div class="popup-meta"><i class="ti ti-calendar" style="font-size:12px;"></i> Last seen ${r.last_seen_date}</div>
    <a href="../public/view_lost.php?id=${r.id}" class="popup-link link-lost" target="_blank">View full report</a>
  </div>`;
}

function foundPopup(r) {
  const photo = r.photo
    ? `<div class="popup-img"><img src="../uploads/reports/${r.photo}" alt=""></div>`
    : `<div class="popup-img"><i class="ti ti-paw"></i></div>`;
  return `${photo}
  <div class="popup-body">
    <span class="popup-pill pill-found">Found</span>
    <div class="popup-name">${r.pet_type.charAt(0).toUpperCase()+r.pet_type.slice(1)}${r.breed ? ' · '+r.breed : ''}</div>
    <div class="popup-meta"><i class="ti ti-color-swatch" style="font-size:12px;"></i> ${r.color}</div>
    <div class="popup-meta"><i class="ti ti-map-pin" style="font-size:12px;"></i> ${r.found_place}</div>
    <div class="popup-meta"><i class="ti ti-calendar" style="font-size:12px;"></i> Found ${r.found_date}</div>
    <a href="../public/view_found.php?id=${r.id}" class="popup-link link-found" target="_blank">View full report</a>
  </div>`;
}

// ── Place markers ─────────────────────────────────────────────────
const lostMarkers  = [];
const foundMarkers = [];

<?php foreach ($lostReports as $r): ?>
(function(){
  const m = L.marker([<?= (float)$r['lat'] ?>, <?= (float)$r['lng'] ?>], {icon: lostIcon});
  m.bindPopup(`<?= addslashes(htmlspecialchars_decode(htmlspecialchars(
    '<div class="popup-img">' .
    (!empty($r['photo']) ? '<img src="../uploads/reports/'.htmlspecialchars($r['photo']).'" alt="">' : '<div style="width:100%;height:110px;background:#f2efeb;display:flex;align-items:center;justify-content:center;"><i class="ti ti-paw" style="font-size:2.5rem;color:#ccc;"></i></div>') .
    '</div><div class="popup-body">' .
    '<span class="popup-pill pill-lost">Lost</span>' .
    '<div class="popup-name">'.htmlspecialchars($r['pet_name']).'</div>' .
    '<div class="popup-meta"><i class="ti ti-paw" style="font-size:12px;"></i> '.htmlspecialchars($r['pet_type']).(!empty($r['breed'])?' · '.htmlspecialchars($r['breed']):'').'</div>' .
    '<div class="popup-meta"><i class="ti ti-map-pin" style="font-size:12px;"></i> '.htmlspecialchars($r['last_seen_place']).'</div>' .
    '<div class="popup-meta"><i class="ti ti-calendar" style="font-size:12px;"></i> Last seen '.date('M j, Y', strtotime($r['last_seen_date'])).'</div>' .
    '<a href="../public/view_lost.php?id='.$r['id'].'" class="popup-link link-lost" target="_blank">View full report</a>' .
    '</div>'
  ))) ?>`, {maxWidth: 220}).addTo(map);
  lostMarkers.push(m);
})();
<?php endforeach; ?>

<?php foreach ($foundReports as $r): ?>
(function(){
  const m = L.marker([<?= (float)$r['lat'] ?>, <?= (float)$r['lng'] ?>], {icon: foundIcon});
  m.bindPopup(`<?= addslashes(htmlspecialchars_decode(htmlspecialchars(
    '<div class="popup-img">' .
    (!empty($r['photo']) ? '<img src="../uploads/reports/'.htmlspecialchars($r['photo']).'" alt="">' : '<div style="width:100%;height:110px;background:#f2efeb;display:flex;align-items:center;justify-content:center;"><i class="ti ti-paw" style="font-size:2.5rem;color:#ccc;"></i></div>') .
    '</div><div class="popup-body">' .
    '<span class="popup-pill pill-found">Found</span>' .
    '<div class="popup-name">'.ucfirst(htmlspecialchars($r['pet_type'])).(!empty($r['breed'])?' · '.htmlspecialchars($r['breed']):'').'</div>' .
    '<div class="popup-meta"><i class="ti ti-color-swatch" style="font-size:12px;"></i> '.htmlspecialchars($r['color']).'</div>' .
    '<div class="popup-meta"><i class="ti ti-map-pin" style="font-size:12px;"></i> '.htmlspecialchars($r['found_place']).'</div>' .
    '<div class="popup-meta"><i class="ti ti-calendar" style="font-size:12px;"></i> Found '.date('M j, Y', strtotime($r['found_date'])).'</div>' .
    '<a href="../public/view_found.php?id='.$r['id'].'" class="popup-link link-found" target="_blank">View full report</a>' .
    '</div>'
  ))) ?>`, {maxWidth: 220}).addTo(map);
  foundMarkers.push(m);
})();
<?php endforeach; ?>

// ── Filter controls ───────────────────────────────────────────────
let currentFilter = 'all';

function setFilter(type) {
  currentFilter = type;

  lostMarkers.forEach(m  => type === 'found' ? map.removeLayer(m) : m.addTo(map));
  foundMarkers.forEach(m => type === 'lost'  ? map.removeLayer(m) : m.addTo(map));

  document.getElementById('filterAll').className   = 'filter-pill' + (type === 'all'   ? ' active-both'  : '');
  document.getElementById('filterLost').className  = 'filter-pill' + (type === 'lost'  ? ' active-lost'  : '');
  document.getElementById('filterFound').className = 'filter-pill' + (type === 'found' ? ' active-found' : '');
}

// ── If no markers, show a message ─────────────────────────────────
if (lostMarkers.length === 0 && foundMarkers.length === 0) {
  const info = L.control({position: 'topright'});
  info.onAdd = () => {
    const d = L.DomUtil.create('div');
    d.style.cssText = 'background:#fff;padding:10px 14px;border-radius:8px;font-size:12px;color:#8a7060;border:.5px solid rgba(0,0,0,.1);max-width:220px;';
    d.innerHTML = '<strong style="color:#1a1208;display:block;margin-bottom:4px;">No pins yet</strong>Reports submitted before the map feature was added don\'t have coordinates. New reports with a dropped pin will appear here.';
    return d;
  };
  info.addTo(map);
}
</script>

</body>
</html>
