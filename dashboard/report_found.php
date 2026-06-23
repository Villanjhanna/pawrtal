<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Collect fields ───────────────────────────────────────────
    $pet_type      = trim($_POST['pet_type']      ?? '');
    $breed         = trim($_POST['breed']          ?? '');
    $color         = trim($_POST['color']          ?? '');
    $gender        = trim($_POST['gender']         ?? '');
    $found_place   = trim($_POST['found_place']    ?? '');   // matches DB column
    $found_date    = trim($_POST['found_date']     ?? '');
    $description   = trim($_POST['description']    ?? '');
    $contact_name  = trim($_POST['contact_name']   ?? '');
    $contact_phone = trim($_POST['contact_phone']  ?? '');
    $lat           = trim($_POST['lat']            ?? '');
    $lng           = trim($_POST['lng']            ?? '');

    $lat_val = is_numeric($lat) ? (float)$lat : null;
    $lng_val = is_numeric($lng) ? (float)$lng : null;

    // ── Validate ─────────────────────────────────────────────────
    if (!$pet_type)      $errors[] = 'Pet type is required.';
    if (!$found_place)   $errors[] = 'Found location is required.';
    if (!$found_date)    $errors[] = 'Found date is required.';
    if (!$contact_name)  $errors[] = 'Your name is required.';
    if (!$contact_phone) $errors[] = 'Your contact number is required.';

    // ── Photo upload ─────────────────────────────────────────────
    $photo = '';
    if (!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
            $errors[] = 'Photo must be JPG, PNG, or WebP.';
        } else {
            $photo = uniqid('fr_', true) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], "../uploads/reports/$photo");
        }
    }

    // ── INSERT ───────────────────────────────────────────────────
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO found_reports
                (user_id, pet_type, breed, color, gender,
                 found_place, found_date, description,
                 contact_name, contact_phone, photo,
                 lat, lng, status)
            VALUES
                (?, ?, ?, ?, ?,
                 ?, ?, ?,
                 ?, ?, ?,
                 ?, ?, 'active')
        ");
        // i = user_id
        // s s s s     = pet_type breed color gender
        // s s s        = found_place found_date description
        // s s s        = contact_name contact_phone photo
        // d d          = lat lng
        // Total: 1i + 10s + 2d = 13 values → type string 'issssssssssdd'
        $stmt->bind_param(
            'issssssssssdd',
            $user_id,
            $pet_type, $breed, $color, $gender,
            $found_place, $found_date, $description,
            $contact_name, $contact_phone, $photo,
            $lat_val, $lng_val
        );

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            require_once '../includes/match_engine.php';
            findMatches($conn, $new_id, 'found');
            $success = true;
        } else {
            $errors[] = 'Something went wrong saving your report. Please try again.';
        }
    }
}

$unreadCount = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$myMatches   = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];
$name        = $_SESSION['name'];
$initials    = pf_initials($name);

pf_head('Report Found Pet');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
<style>
#found-map{height:260px;border-radius:var(--radius);border:.5px solid var(--border-md);overflow:hidden;margin-top:8px;}
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
    <a href="my_reports.php" class="sb-item"><i class="ti ti-clipboard-list"></i> My reports</a>
    <a href="matches.php"    class="sb-item">
      <i class="ti ti-link"></i> Match alerts
      <?php if ($myMatches > 0): ?><span class="sb-badge"><?= $myMatches ?></span><?php endif; ?>
    </a>
    <a href="my_pets.php"    class="sb-item"><i class="ti ti-paw"></i> My pets</a>
    <div class="sb-sec">Community</div>
    <a href="map.php"        class="sb-item"><i class="ti ti-map"></i> Map</a>
    <a href="../public/found.php" class="sb-item"><i class="ti ti-search"></i> Browse reports</a>
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
      <div class="topbar-title">Report a found pet</div>
      <div class="topbar-sub">Help reunite a stray with their owner</div>
    </div>
    <a href="my_reports.php" class="btn"><i class="ti ti-arrow-left"></i> My reports</a>
  </div>

  <div class="content">

    <?php if ($success): ?>
    <div class="alert success">
      <i class="ti ti-circle-check"></i>
      Your found pet report has been posted and matched against active lost reports.
      <a href="my_reports.php?tab=found" style="margin-left:10px;font-weight:500;color:inherit;">View my reports &rarr;</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert" style="background:var(--danger-bg);color:var(--danger-text);border:.5px solid rgba(153,27,27,.2);border-radius:var(--radius);display:flex;align-items:flex-start;gap:8px;padding:10px 14px;font-size:13px;">
      <i class="ti ti-alert-circle" style="flex-shrink:0;margin-top:1px;"></i>
      <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start;">

      <div class="card">
        <div class="card-header"><div class="card-title">Found pet details</div></div>
        <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:16px;">

          <!-- Hidden lat/lng fields populated by the map -->
          <input type="hidden" name="lat" id="lat" value="<?= htmlspecialchars($_POST['lat'] ?? '') ?>">
          <input type="hidden" name="lng" id="lng" value="<?= htmlspecialchars($_POST['lng'] ?? '') ?>">

          <!-- Pet type + Breed -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Pet type <span style="color:var(--red);">*</span></label>
              <select name="pet_type" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
                <option value="">Select type…</option>
                <?php foreach (['dog','cat','bird','rabbit','other'] as $t): ?>
                <option value="<?= $t ?>" <?= ($_POST['pet_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Breed (if known)</label>
              <input type="text" name="breed" placeholder="e.g. Aspin"
                     value="<?= htmlspecialchars($_POST['breed'] ?? '') ?>"
                     style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;">
            </div>
          </div>

          <!-- Color + Gender -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Color / markings</label>
              <input type="text" name="color" placeholder="e.g. Black and white"
                     value="<?= htmlspecialchars($_POST['color'] ?? '') ?>"
                     style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;">
            </div>
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Gender (if known)</label>
              <select name="gender" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
<option value="unknown">Unknown</option>
                <option value="male"   <?= ($_POST['gender'] ?? '') === 'male'   ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= ($_POST['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
              </select>
            </div>
          </div>

          <!-- Date found -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Date found <span style="color:var(--red);">*</span></label>
              <input type="date" name="found_date"
                     value="<?= htmlspecialchars($_POST['found_date'] ?? date('Y-m-d')) ?>"
                     style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;">
            </div>
          </div>

          <!-- Description -->
          <div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Description</label>
            <textarea name="description" rows="3" placeholder="Collar, tags, condition, behavior, etc."
                      style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);resize:vertical;outline:none;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
          </div>

          <!-- ── Location + Map ─────────────────────────────── -->
          <div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">
              Where found <span style="color:var(--red);">*</span>
            </label>
            <div class="map-search-row">
              <input type="text" id="placeSearch" name="found_place"
                     placeholder="e.g. Burgos Street, Bacolod City"
                     value="<?= htmlspecialchars($_POST['found_place'] ?? '') ?>">
              <button type="button" class="map-search-btn" onclick="geocode()">
                <i class="ti ti-search"></i> Find on map
              </button>
            </div>
            <div class="map-hint">Type the location and click "Find on map", or click the map to drop a pin.</div>
            <div class="map-coords" id="coordsDisplay">
              <i class="ti ti-map-pin" style="font-size:12px;vertical-align:-1px;"></i>
              Pin set: <span id="coordsText"></span>
            </div>
            <div id="found-map"></div>
          </div>

          <!-- Contact details -->
          <div style="border-top:.5px solid var(--border);padding-top:14px;">
            <div style="font-size:12px;font-weight:600;color:var(--text2);margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em;">Your contact details</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div>
                <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Your name <span style="color:var(--red);">*</span></label>
                <input type="text" name="contact_name"
                       value="<?= htmlspecialchars($_POST['contact_name'] ?? $name) ?>"
                       style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;">
              </div>
              <div>
                <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Phone number <span style="color:var(--red);">*</span></label>
                <input type="tel" name="contact_phone" placeholder="e.g. 09XX XXX XXXX"
                       value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>"
                       style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;">
              </div>
            </div>
          </div>

          <!-- Photo -->
          <div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Photo</label>
            <input type="file" name="photo" accept="image/*" style="font-size:13px;color:var(--text2);">
            <div style="font-size:11px;color:var(--text3);margin-top:4px;">JPG, PNG or WebP. A clear photo greatly improves matching.</div>
          </div>

          <div style="border-top:.5px solid var(--border);padding-top:14px;display:flex;gap:8px;">
            <button type="submit" class="btn success"><i class="ti ti-send"></i> Submit found report</button>
            <a href="my_reports.php" class="btn">Cancel</a>
          </div>

        </form>
      </div>

      <!-- Sidebar tips -->
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="card">
          <div class="card-title" style="margin-bottom:10px;">How this works</div>
          <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;color:var(--text2);">
            <div style="display:flex;gap:8px;"><i class="ti ti-number-1" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Submit the found pet report with a photo and your contact details.</div></div>
            <div style="display:flex;gap:8px;"><i class="ti ti-number-2" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Our system automatically compares it against active lost pet reports.</div></div>
            <div style="display:flex;gap:8px;"><i class="ti ti-number-3" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>If a match is found, the owner gets alerted with your contact details.</div></div>
          </div>
        </div>
        <div class="card" style="background:var(--green-lt);border-color:rgba(45,106,79,.2);">
          <div style="font-size:12px;color:var(--green);font-weight:500;margin-bottom:6px;"><i class="ti ti-shield-check" style="font-size:14px;vertical-align:-1px;"></i> Your contact is protected</div>
          <div style="font-size:12px;color:var(--text2);">Your phone number is only revealed to the pet owner after they confirm the match — not publicly visible.</div>
        </div>
        <div class="card" style="background:var(--green-lt);border-color:rgba(45,106,79,.2);">
          <div style="font-size:12px;color:var(--green);font-weight:500;margin-bottom:6px;"><i class="ti ti-map" style="font-size:14px;vertical-align:-1px;"></i> Map pin is optional</div>
          <div style="font-size:12px;color:var(--text2);">Dropping a pin lets your report appear on the community map so locals can help search nearby.</div>
        </div>
      </div>

    </div>
  </div>

  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script>
const DEFAULT_LAT = 10.6713;
const DEFAULT_LNG = 122.9511;

const map = L.map('found-map').setView([DEFAULT_LAT, DEFAULT_LNG], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 19
}).addTo(map);

// Green teardrop marker for found reports
const greenIcon = L.divIcon({
  className: '',
  html: `<div style="width:28px;height:28px;border-radius:50% 50% 50% 0;background:#2d6a4f;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);transform:rotate(-45deg);"></div>`,
  iconSize:   [28, 28],
  iconAnchor: [14, 28],
  popupAnchor:[0, -30]
});

let marker = null;

// Restore pin after validation error
<?php if (!empty($_POST['lat']) && !empty($_POST['lng'])): ?>
placePinAt(<?= (float)$_POST['lat'] ?>, <?= (float)$_POST['lng'] ?>);
<?php endif; ?>

function placePinAt(lat, lng) {
  if (marker) map.removeLayer(marker);
  marker = L.marker([lat, lng], { icon: greenIcon, draggable: true }).addTo(map);
  marker.bindPopup('Drag to fine-tune the location').openPopup();
  map.setView([lat, lng], 15);
  updateCoords(lat, lng);
  marker.on('dragend', function(e) {
    const pos = e.target.getLatLng();
    updateCoords(pos.lat, pos.lng);
  });
}

function updateCoords(lat, lng) {
  document.getElementById('lat').value        = lat.toFixed(6);
  document.getElementById('lng').value        = lng.toFixed(6);
  document.getElementById('coordsText').textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
  document.getElementById('coordsDisplay').style.display = 'block';
}

// Click map to place/move pin
map.on('click', function(e) { placePinAt(e.latlng.lat, e.latlng.lng); });

// Geocode via Nominatim (free, no API key)
async function geocode() {
  const q   = document.getElementById('placeSearch').value.trim();
  if (!q) return;
  const btn = document.querySelector('.map-search-btn');
  btn.innerHTML = '<i class="ti ti-loader-2"></i> Searching…';
  btn.disabled  = true;
  try {
    const res  = await fetch(
      `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`,
      { headers: { 'Accept-Language': 'en' } }
    );
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

document.getElementById('placeSearch').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') { e.preventDefault(); geocode(); }
});
</script>

</body>
</html>