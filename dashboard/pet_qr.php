<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$pet_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$pet_id) { header("Location: my_pets.php"); exit(); }

// Load pet — must belong to this user
$pet = $conn->query("SELECT * FROM pets WHERE id=$pet_id AND user_id=$user_id")->fetch_assoc();
if (!$pet) { header("Location: my_pets.php"); exit(); }

// Load owner info
$owner = $conn->query("SELECT name, email, phone FROM users WHERE id=$user_id")->fetch_assoc();

$myMatches = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];

// The public URL that the QR code will point to
// Adjust the base URL to match your actual domain/path
$base_url   = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$public_url = $base_url . '/pawrtal/public/pet_profile.php?token=' . urlencode($pet['qr_token']);

$unreadCount = $conn->query("
    SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0
")->fetch_assoc()['c'];

$name     = $_SESSION['name'];
$initials = pf_initials($name);

// Gender label
$gender_label = match($pet['gender']) {
    'male'   => 'Male',
    'female' => 'Female',
    default  => 'Unknown',
};

pf_head('Pet ID — ' . htmlspecialchars($pet['name']));
?>
<!-- Load QR code library (client-side, no server dependency) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
    <a href="my_pets.php"    class="sb-item active"><i class="ti ti-paw"></i> My pets</a>
    <div class="sb-sec">Community</div>
    <a href="map.php"        class="sb-item"><i class="ti ti-map"></i> Map</a>
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
      <div class="topbar-title">Pet ID card — <?= htmlspecialchars($pet['name']) ?></div>
      <div class="topbar-sub">Print and save this card and attach it to your pet's collar</div>
    </div>
    <div style="display:flex;gap:8px;">
      <a href="my_pets.php" class="btn"><i class="ti ti-arrow-left"></i> Back</a>
<button onclick="downloadCard()" class="btn">
    <i class="ti ti-download"></i> Download ID as PNG
</button>    </div>
  </div>

  <div class="content">

    <!-- Instructions banner -->
    <div style="background:var(--green-lt);border:.5px solid rgba(45,106,79,.2);border-radius:var(--radius);padding:11px 16px;display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text2);">
      <i class="ti ti-info-circle" style="color:var(--green);font-size:18px;flex-shrink:0;"></i>
      <div>Print this card, laminate it, and attach it to your pet's collar. Anyone who finds your pet can scan the QR code to contact you immediately.</div>
    </div>

    <!-- Two-column layout: card preview + details -->
    <div style="display:grid;grid-template-columns:auto 1fr;gap:20px;align-items:start;">

      <!-- ── THE ID CARD (printable) ──────────────────────────── -->
      <div class="pet-card" id="pet-id-card" style="
        width: 340px;
        background: #fff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 24px rgba(0,0,0,.12);
        font-family: 'DM Sans', sans-serif;
        flex-shrink: 0;
      ">
        <!-- Card header band -->
        <div style="background:#2d6a4f;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;">
          <div>
            <div style="font-family:'DM Serif Display',serif;font-size:1.1rem;color:#fff;line-height:1.1;">Pawrtal</div>
            <div style="font-size:10px;color:rgba(255,255,255,.7);letter-spacing:.08em;text-transform:uppercase;margin-top:2px;">Pet Identification</div>
          </div>
          <div style="width:32px;height:32px;border-radius:8px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;">
            <i class="ti ti-paw" style="color:#fff;font-size:18px;"></i>
          </div>
        </div>

        <!-- Card body -->
        <div style="padding:18px;display:flex;gap:14px;align-items:flex-start;">

          <!-- Pet photo -->
          <div style="flex-shrink:0;">
            <?php if (!empty($pet['photo'])): ?>
              <img src="../uploads/pets/<?= htmlspecialchars($pet['photo']) ?>"
                   alt="<?= htmlspecialchars($pet['name']) ?>"
                   style="width:90px;height:90px;border-radius:10px;object-fit:cover;border:2px solid #e5e7eb;">
            <?php else: ?>
              <div style="width:90px;height:90px;border-radius:10px;background:#f0fdf4;border:2px solid #e5e7eb;display:flex;align-items:center;justify-content:center;">
                <i class="ti ti-paw" style="font-size:2.2rem;color:#2d6a4f;"></i>
              </div>
            <?php endif; ?>
          </div>

          <!-- Pet info -->
          <div style="flex:1;min-width:0;">
            <div style="font-family:'DM Serif Display',serif;font-size:1.3rem;color:#1a1208;line-height:1.1;margin-bottom:4px;">
              <?= htmlspecialchars($pet['name']) ?>
            </div>
            <div style="font-size:12px;color:#44352a;margin-bottom:10px;">
              <?= ucfirst(htmlspecialchars($pet['type'])) ?>
              <?= !empty($pet['breed']) ? ' &middot; ' . htmlspecialchars($pet['breed']) : '' ?>
            </div>

            <div style="display:flex;flex-direction:column;gap:4px;">
              <?php if (!empty($pet['color'])): ?>
              <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#44352a;">
                <i class="ti ti-color-swatch" style="font-size:13px;color:#8a7060;width:13px;"></i>
                <?= htmlspecialchars($pet['color']) ?>
              </div>
              <?php endif; ?>

              <?php if ($pet['gender'] !== 'unknown'): ?>
              <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#44352a;">
                <i class="ti ti-gender-male-female" style="font-size:13px;color:#8a7060;width:13px;"></i>
                <?= $gender_label ?>
              </div>
              <?php endif; ?>

              <?php if (!empty($pet['age_years'])): ?>
              <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:#44352a;">
                <i class="ti ti-clock" style="font-size:13px;color:#8a7060;width:13px;"></i>
                <?= $pet['age_years'] ?> <?= $pet['age_years'] == 1 ? 'year' : 'years' ?> old
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Divider -->
        <div style="height:.5px;background:#e5e7eb;margin:0 18px;"></div>

        <!-- Owner + QR row -->
        <div style="padding:14px 18px;display:flex;align-items:center;gap:14px;">
          <div style="flex:1;min-width:0;">
            <div style="font-size:9px;letter-spacing:.1em;text-transform:uppercase;color:#8a7060;margin-bottom:6px;font-weight:600;">Owner contact</div>
            <div style="font-size:12px;font-weight:600;color:#1a1208;margin-bottom:2px;"><?= htmlspecialchars($owner['name'] ?? $name) ?></div>
            <?php if (!empty($owner['phone'])): ?>
            <div style="display:flex;align-items:center;gap:4px;font-size:11px;color:#44352a;margin-bottom:2px;">
              <i class="ti ti-phone" style="font-size:12px;color:#8a7060;"></i>
              <?= htmlspecialchars($owner['phone']) ?>
            </div>
            <?php endif; ?>
            
          </div>

          <!-- QR code -->
          <div style="flex-shrink:0;text-align:center;">
            <div id="qrcode" style="width:72px;height:72px;"></div>
            <div style="font-size:9px;color:#8a7060;margin-top:4px;text-align:center;">Scan to contact</div>
          </div>
        </div>

        <!-- Card footer -->
        <div style="background:#f7f5f2;border-top:.5px solid #e5e7eb;padding:8px 18px;display:flex;align-items:center;justify-content:space-between;">
          <div style="font-size:9px;color:#8a7060;">ID: <?= strtoupper(substr($pet['qr_token'], 0, 8)) ?></div>
          <div style="font-size:9px;color:#8a7060;">pawrtal.com</div>
        </div>
      </div><!-- /card -->

      <!-- ── Right panel: details + tips ─────────────────────── -->
      <div style="display:flex;flex-direction:column;gap:14px;">

        <div class="card">
          <div class="card-header" style="margin-bottom:12px;">
            <div class="card-title">How the QR code works</div>
          </div>
          <div style="display:flex;flex-direction:column;gap:10px;font-size:13px;color:var(--text2);">
            <div style="display:flex;gap:10px;">
              <div style="width:24px;height:24px;border-radius:50%;background:var(--green);color:#fff;font-size:11px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;">1</div>
              <div>Print the ID card and laminate it if possible, then attach it to your pet's collar with a tag holder.</div>
            </div>
            <div style="display:flex;gap:10px;">
              <div style="width:24px;height:24px;border-radius:50%;background:var(--green);color:#fff;font-size:11px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;">2</div>
              <div>If your pet is found, anyone with a smartphone can scan the QR code — no app needed.</div>
            </div>
            <div style="display:flex;gap:10px;">
              <div style="width:24px;height:24px;border-radius:50%;background:var(--green);color:#fff;font-size:11px;font-weight:600;display:flex;align-items:center;justify-content:center;flex-shrink:0;">3</div>
              <div>The scan opens a page with your contact details so they can reach you right away.</div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header" style="margin-bottom:12px;">
            <div class="card-title">Pet details on this card</div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <?php
            $details = [
              ['Name',    $pet['name']],
              ['Type',    ucfirst($pet['type'])],
              ['Breed',   $pet['breed']    ?: '—'],
              ['Color',   $pet['color']],
              ['Gender',  $gender_label],
              ['Age',     $pet['age_years'] ? $pet['age_years'].' yrs' : '—'],
            ];
            foreach ($details as [$label, $value]):
            ?>
            <div style="background:var(--surface2);border-radius:var(--radius);padding:10px 12px;">
              <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:2px;"><?= $label ?></div>
              <div style="font-size:13px;font-weight:500;color:var(--text);"><?= htmlspecialchars($value) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($pet['description'])): ?>
          <div style="margin-top:10px;background:var(--surface2);border-radius:var(--radius);padding:10px 12px;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:2px;">Description</div>
            <div style="font-size:13px;color:var(--text2);"><?= htmlspecialchars($pet['description']) ?></div>
          </div>
          <?php endif; ?>
          <div style="margin-top:12px;padding-top:12px;border-top:.5px solid var(--border);">
            <a href="my_pets.php?action=edit&id=<?= $pet['id'] ?>" class="btn sm"><i class="ti ti-edit"></i> Edit pet details</a>
          </div>
        </div>

        <div class="card" style="background:var(--amber-lt);border-color:rgba(146,64,14,.2);">
          <div style="font-size:12px;color:var(--amber);font-weight:500;margin-bottom:5px;"><i class="ti ti-bulb" style="font-size:14px;vertical-align:-1px;"></i> Printing tip</div>
          <div style="font-size:12px;color:var(--text2);">For best results, print at 100% scale on regular paper, then laminate. A standard credit-card-sized tag holder fits the card when trimmed.</div>
        </div>

      </div>
    </div>

  </div><!-- /content -->

  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

<!-- ── Print styles ────────────────────────────────────────────── -->
<style>
@media print {
  body * { visibility: hidden; }
  #pet-id-card, #pet-id-card * { visibility: visible; }
  #pet-id-card {
    position: fixed;
    top: 20mm;
    left: 50%;
    transform: translateX(-50%);
    box-shadow: none !important;
    border: 1px solid #ccc;
  }
}
</style>

<script>
// Generate QR code pointing to the public pet profile URL
var qr = new QRCode(document.getElementById('qrcode'), {
  text: <?= json_encode($public_url) ?>,
  width:  72,
  height: 72,
  colorDark:  '#1b4332',
  colorLight: '#ffffff',
  correctLevel: QRCode.CorrectLevel.M
});

async function downloadCard() {
    const card = document.getElementById('pet-id-card');

    const canvas = await html2canvas(card, {
        scale: 3,
        useCORS: true,
        backgroundColor: '#ffffff'
    });

    const link = document.createElement('a');

    link.download =
        '<?= preg_replace("/[^a-zA-Z0-9_-]/", "_", $pet["name"]) ?>_ID.png';

    link.href = canvas.toDataURL('image/png');

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</body>
</html>
