<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pet_name        = trim($_POST['pet_name']        ?? '');
    $pet_type        = trim($_POST['pet_type']         ?? '');
    $breed           = trim($_POST['breed']            ?? '');
    $color           = trim($_POST['color']            ?? '');
    $gender          = trim($_POST['gender']           ?? '');
    $last_seen_place = trim($_POST['last_seen_place']  ?? '');
    $last_seen_date  = trim($_POST['last_seen_date']   ?? '');
    $description     = trim($_POST['description']      ?? '');
    $lat             = trim($_POST['lat']              ?? '');
    $lng             = trim($_POST['lng']              ?? '');

    if (!$pet_name)        $errors[] = 'Pet name is required.';
    if (!$pet_type)        $errors[] = 'Pet type is required.';
    if (!$last_seen_place) $errors[] = 'Last seen location is required.';
    if (!$last_seen_date)  $errors[] = 'Last seen date is required.';

    $lat_val = is_numeric($lat) ? (float)$lat : null;
    $lng_val = is_numeric($lng) ? (float)$lng : null;

    $photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
            $errors[] = 'Photo must be JPG, PNG, or WebP.';
        } else {
            $photo = uniqid('lr_', true) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], "../uploads/reports/$photo");
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO lost_reports
                (user_id, pet_name, pet_type, breed, color, gender,
                 last_seen_place, last_seen_date, description, photo, lat, lng, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param(
            'isssssssssdd',
            $user_id, $pet_name, $pet_type, $breed, $color, $gender,
            $last_seen_place, $last_seen_date, $description, $photo,
            $lat_val, $lng_val
        );

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            require_once '../includes/match_engine.php';
            findMatches($conn, $new_id, 'lost');
            $success = true;
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$unreadCount = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$myMatches   = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];
$name        = $_SESSION['name'];
$initials    = pf_initials($name);

pf_head('Report Lost Pet');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
<style>
#lost-map{height:260px;border-radius:var(--radius);border:.5px solid var(--border-md);overflow:hidden;margin-top:8px;}
.map-search-row{display:flex;gap:8px;margin-bottom:4px;}
.map-search-row input{flex:1;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;}
.map-search-row input:focus{border-color:var(--green);}
.map-search-btn{padding:8px 13px;border-radius:var(--radius);border:none;background:var(--green);color:#fff;font-size:13px;font-family:var(--font);font-weight:500;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:5px;}
.map-search-btn:hover{background:var(--green-dk);}
.map-hint{font-size:11px;color:var(--text3);margin-top:5px;}
.map-coords{font-size:11px;color:var(--green);margin-top:4px;display:none;}
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
    <a href="my_reports.php" class="sb-item active"><i class="ti ti-clipboard-list"></i> My reports</a>
    <a href="matches.php"    class="sb-item">
      <i class="ti ti-link"></i> Match alerts
      <?php if ($myMatches > 0): ?><span class="sb-badge"><?= $myMatches ?></span><?php endif; ?>
    </a>
    <a href="my_pets.php"    class="sb-item"><i class="ti ti-paw"></i> My pets</a>
    <div class="sb-sec">Community</div>
    <a href="map.php"        class="sb-item"><i class="ti ti-map"></i> Map</a>
    <a href="../public/lost.php" class="sb-item"><i class="ti ti-search"></i> Browse reports</a>
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
      <div class="topbar-title">Report a lost pet</div>
      <div class="topbar-sub">Fill in the details below to alert the community</div>
    </div>
    <a href="my_reports.php" class="btn"><i class="ti ti-arrow-left"></i> My reports</a>
  </div>

  <div class="content">

    <?php if ($success): ?>
    <div class="alert success">
      <i class="ti ti-circle-check"></i>
      Your lost pet report has been posted. We'll notify you if a found report matches your pet.
      <a href="my_reports.php" style="margin-left:10px;font-weight:500;color:inherit;">View my reports &rarr;</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert" style="background:var(--danger-bg);color:var(--danger-text);border-color:rgba(153,27,27,.2);border-radius:var(--radius);display:flex;align-items:flex-start;gap:8px;padding:10px 14px;font-size:13px;">
      <i class="ti ti-alert-circle" style="flex-shrink:0;margin-top:1px;"></i>
      <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start;">

      <div class="card">
        <div class="card-header"><div class="card-title">Pet details</div></div>
        <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:16px;" id="lostForm">

          <!-- Hidden lat/lng fields populated by the map -->
          <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($_POST['lat'] ?? '') ?>">
          <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($_POST['lng'] ?? '') ?>">

          <!-- Name + Type -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Pet name <span style="color:var(--red);">*</span></label>
              <input type="text" name="pet_name" placeholder="e.g. Brownie"
                     value="<?= htmlspecialchars($_POST['pet_name'] ?? '') ?>"
                     style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Pet type <span style="color:var(--red);">*</span></label>
              <select name="pet_type" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
                <option value="">Select type…</option>
                <?php foreach (['dog','cat','bird','rabbit','other'] as $t): ?>
                <option value="<?= $t ?>" <?= ($_POST['pet_type'] ?? '')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Breed + Color -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Breed</label>
              <input type="text" name="breed" placeholder="e.g. Aspin"
                     value="<?= htmlspecialchars($_POST['breed'] ?? '') ?>"
                     style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Color / markings</label>
              <input type="text" name="color" placeholder="e.g. Brown with white spots"
                     value="<?= htmlspecialchars($_POST['color'] ?? '') ?>"
                     style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;">
            </div>
          </div>

          <!-- Gender + Date -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Gender</label>
              <select name="gender" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
<option value="unknown">Unknown</option>
                <option value="male"   <?= ($_POST['gender'] ?? '')==='male'  ?'selected':'' ?>>Male</option>
                <option value="female" <?= ($_POST['gender'] ?? '')==='female'?'selected':'' ?>>Female</option>
              </select>
            </div>
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Date last seen <span style="color:var(--red);">*</span></label>
              <input type="date" name="last_seen_date"
                     value="<?= htmlspecialchars($_POST['last_seen_date'] ?? date('Y-m-d')) ?>"
                     style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;">
            </div>
          </div>

          <!-- Description -->
          <div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Description</label>
            <textarea name="description" rows="3" placeholder="Collar color, tags, microchip, distinctive features…"
                      style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);resize:vertical;outline:none;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
          </div>

          <!-- ── Location + Map ── -->
          <div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">
              Last seen location <span style="color:var(--red);">*</span>
            </label>
            <div class="map-search-row">
              <input type="text" id="placeSearch" name="last_seen_place"
                     placeholder="e.g. Burgos Street, Bacolod City"
                     value="<?= htmlspecialchars($_POST['last_seen_place'] ?? '') ?>">
              <button type="button" class="map-search-btn" onclick="geocode()">
                <i class="ti ti-search"></i> Find on map
              </button>
            </div>
            <div class="map-hint">Type a location then click "Find on map", then drag the pin to fine-tune.</div>
            <div class="map-coords" id="coordsDisplay">
              <i class="ti ti-map-pin" style="font-size:12px;vertical-align:-1px;"></i>
              Pin set: <span id="coordsText"></span>
            </div>
            <div id="lost-map"></div>
          </div>

          <!-- Photo -->
          <div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Photo</label>
            <input type="file" name="photo" accept="image/*" style="font-size:13px;color:var(--text2);">
            <div style="font-size:11px;color:var(--text3);margin-top:4px;">JPG, PNG or WebP. A clear photo greatly improves matching.</div>
          </div>

          <div style="border-top:.5px solid var(--border);padding-top:14px;display:flex;gap:8px;">
            <button type="submit" class="btn primary"><i class="ti ti-send"></i> Submit lost report</button>
            <a href="my_reports.php" class="btn">Cancel</a>
          </div>

        </form>
      </div>

      <!-- Sidebar tips -->
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="card">
          <div class="card-title" style="margin-bottom:10px;">Tips for a better report</div>
          <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;color:var(--text2);">
            <div style="display:flex;gap:8px;"><i class="ti ti-camera" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Upload a recent, clear photo — it greatly helps community members identify your pet.</div></div>
            <div style="display:flex;gap:8px;"><i class="ti ti-map-pin" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Drop a pin on the map so nearby people know exactly where to look.</div></div>
            <div style="display:flex;gap:8px;"><i class="ti ti-id" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Include unique markings, collar color, or tag details in the description.</div></div>
            <div style="display:flex;gap:8px;"><i class="ti ti-link" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Our system will automatically match your report against found pet reports.</div></div>
          </div>
        </div>
        <div class="card" style="background:var(--amber-lt);border-color:rgba(146,64,14,.2);">
          <div style="font-size:12px;color:var(--amber);font-weight:500;margin-bottom:6px;"><i class="ti ti-bell" style="font-size:14px;vertical-align:-1px;"></i> You'll be notified</div>
          <div style="font-size:12px;color:var(--text2);">When a found pet matches your report, you'll receive a notification with the finder's contact details.</div>
        </div>
        <div class="card" style="background:var(--green-lt);border-color:rgba(45,106,79,.2);">
          <div style="font-size:12px;color:var(--green);font-weight:500;margin-bottom:6px;"><i class="ti ti-map" style="font-size:14px;vertical-align:-1px;"></i> Map pin is optional</div>
          <div style="font-size:12px;color:var(--text2);">Dropping a pin lets your report appear on the community map, making it easier for locals to help search.</div>
        </div>
      </div>

    </div>
  </div>

  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script>
// ── Default center: Bacolod City ──────────────────────────────────
const DEFAULT_LAT = 10.6713;
const DEFAULT_LNG = 122.9511;

const map = L.map('lost-map').setView([DEFAULT_LAT, DEFAULT_LNG], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 19
}).addTo(map);

// ── Red marker ────────────────────────────────────────────────────
const redIcon = L.divIcon({
  className: '',
  html: `<div style="width:28px;height:28px;border-radius:50% 50% 50% 0;background:#8b2635;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);transform:rotate(-45deg);"></div>`,
  iconSize:   [28, 28],
  iconAnchor: [14, 28],
  popupAnchor:[0, -30]
});

let marker = null;

// Restore pin if user came back from a validation error
<?php if (!empty($_POST['lat']) && !empty($_POST['lng'])): ?>
placePinAt(<?= (float)$_POST['lat'] ?>, <?= (float)$_POST['lng'] ?>);
<?php endif; ?>

function placePinAt(lat, lng) {
  if (marker) map.removeLayer(marker);
  marker = L.marker([lat, lng], { icon: redIcon, draggable: true }).addTo(map);
  map.setView([lat, lng], 15);
  updateCoords(lat, lng);

  marker.on('dragend', function(e) {
    const pos = e.target.getLatLng();
    updateCoords(pos.lat, pos.lng);
  });
}

function updateCoords(lat, lng) {
  document.getElementById('lat').value = lat.toFixed(6);
  document.getElementById('lng').value = lng.toFixed(6);
  document.getElementById('coordsText').textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
  document.getElementById('coordsDisplay').style.display = 'block';
}

// Click map to place/move pin
map.on('click', function(e) { placePinAt(e.latlng.lat, e.latlng.lng); });

// ── Geocode via Nominatim (free, no key) ─────────────────────────
async function geocode() {
  const q = document.getElementById('placeSearch').value.trim();
  if (!q) return;

  const btn = document.querySelector('.map-search-btn');
  btn.innerHTML = '<i class="ti ti-loader-2"></i> Searching…';
  btn.disabled  = true;

  try {
    const res  = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`, {
      headers: { 'Accept-Language': 'en' }
    });
    const data = await res.json();

    if (data.length > 0) {
      placePinAt(parseFloat(data[0].lat), parseFloat(data[0].lon));
    } else {
      alert('Location not found. Try a more specific address or click the map to drop a pin manually.');
    }
  } catch (e) {
    alert('Could not reach the geocoding service. Click the map to drop a pin manually.');
  } finally {
    btn.innerHTML = '<i class="ti ti-search"></i> Find on map';
    btn.disabled  = false;
  }
}

// Allow Enter key in the location field to trigger geocode
document.getElementById('placeSearch').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') { e.preventDefault(); geocode(); }
});
</script>

</body>
</html>