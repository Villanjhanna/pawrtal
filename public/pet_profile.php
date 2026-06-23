<?php
session_start();
include '../config/db.php';

$token = trim($_GET['token'] ?? '');
if (!$token) { http_response_code(404); die('Pet not found.'); }

// ── Look up pet ───────────────────────────────────────────────────
if (strlen($token) === 8) {
    $safe = $conn->real_escape_string(strtolower($token));
    $pet  = $conn->query("
        SELECT p.*, u.name AS owner_name, u.phone AS owner_phone, u.id AS owner_user_id
        FROM pets p JOIN users u ON p.user_id = u.id
        WHERE LOWER(LEFT(p.qr_token, 8)) = '$safe'
        LIMIT 1
    ")->fetch_assoc();
} else {
    $stmt = $conn->prepare("
        SELECT p.*, u.name AS owner_name, u.phone AS owner_phone, u.id AS owner_user_id
        FROM pets p JOIN users u ON p.user_id = u.id
        WHERE p.qr_token = ?
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $pet = $stmt->get_result()->fetch_assoc();
}

if (!$pet) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="en"><head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet not found — Pawrtal</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
    <style>*{box-sizing:border-box;margin:0;padding:0}body{background:#f7f5f2;font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}.card{background:#fff;border-radius:16px;padding:32px 28px;max-width:360px;width:100%;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.08);}.icon{font-size:3rem;color:#ccc;margin-bottom:14px;}.title{font-family:'DM Serif Display',serif;font-size:1.3rem;color:#1a1208;margin-bottom:8px;}.sub{font-size:13px;color:#8a7060;line-height:1.6;margin-bottom:20px;}.btn{display:flex;align-items:center;justify-content:center;gap:6px;padding:11px 18px;border-radius:9px;background:#2d6a4f;color:#fff;font-size:13px;font-weight:500;text-decoration:none;}</style>
    </head><body><div class="card">
    <div class="icon"><i class="ti ti-paw-off"></i></div>
    <div class="title">Pet not found</div>
    <div class="sub">We couldn't find a pet registered with that QR code or ID. The tag may be outdated or the pet may have been removed.</div>
    <a href="index.php" class="btn"><i class="ti ti-home"></i> Go to Pawrtal</a>
    </div></body></html><?php
    exit();
}

// ── Handle "I think this is my pet" POST ──────────────────────────
$claimSent  = false;
$claimError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'i_think_its_mine' && isset($_SESSION['user_id'])) {
        $claimant_id  = (int)$_SESSION['user_id'];
        $owner_id     = (int)$pet['owner_user_id'];
        $pet_name     = $conn->real_escape_string($pet['name']);
        $claimant_name= $conn->real_escape_string($_SESSION['name']);

        // Don't notify yourself
        if ($claimant_id === $owner_id) {
            $claimError = "You are the registered owner of this pet.";
        } else {
            // Check if the claimant has any active found reports to link
            $found = $conn->query("
                SELECT id FROM found_reports
                WHERE user_id=$claimant_id AND status='active'
                ORDER BY created_at DESC LIMIT 1
            ")->fetch_assoc();
            $found_link = $found
                ? $conn->real_escape_string("../public/view_found.php?id={$found['id']}")
                : $conn->real_escape_string("../public/index.php");

            $msg = $conn->real_escape_string(
                "$claimant_name scanned the QR tag on \"$pet_name\" and believes it may be their pet. " .
                ($found ? "They have an active found report you can review." : "They do not have an active found report yet.")
            );

            // Insert notification for the owner
            $conn->query("
                INSERT INTO notifications (user_id, type, message, link)
                VALUES ($owner_id, 'qr_claim', '$msg', '$found_link')
            ");
            $claimSent = true;
        }
    }

    if ($action === 'i_found_this_pet' && isset($_SESSION['user_id'])) {
        $finder_id  = (int)$_SESSION['user_id'];
        $owner_id   = (int)$pet['owner_user_id'];
        $pet_name   = $conn->real_escape_string($pet['name']);
        $finder_name= $conn->real_escape_string($_SESSION['name']);

        if ($finder_id !== $owner_id) {
            // Check for an existing found report by this user
            $found = $conn->query("
                SELECT id FROM found_reports
                WHERE user_id=$finder_id AND status='active'
                ORDER BY created_at DESC LIMIT 1
            ")->fetch_assoc();
            $found_link = $found
                ? $conn->real_escape_string("../public/view_found.php?id={$found['id']}")
                : $conn->real_escape_string("../public/index.php");

            $msg = $conn->real_escape_string(
                "$finder_name scanned \"$pet_name\"'s QR tag and says they have found your pet. " .
                ($found ? "They have an active found report linked below." : "They haven't posted a found report yet — check their profile or wait for them to post one.")
            );
            $conn->query("
                INSERT INTO notifications (user_id, type, message, link)
                VALUES ($owner_id, 'qr_found', '$msg', '$found_link')
            ");
        }
        // Redirect to post found report, pre-filling context isn't possible across pages
        // so we just send them to report_found
        header("Location: ../dashboard/report_found.php"); exit();
    }
}

$first_name   = explode(' ', trim($pet['owner_name']))[0];
$is_owner     = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$pet['owner_user_id'];
$is_logged_in = isset($_SESSION['user_id']);

$gender_label = match($pet['gender']) {
    'male'   => 'Male',
    'female' => 'Female',
    default  => 'Unknown',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pet['name']) ?> — Pawrtal Pet Profile</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;}
body{
  background:#f7f5f2;font-family:'DM Sans',sans-serif;
  font-size:14px;color:#1a1208;
  min-height:100vh;display:flex;flex-direction:column;
  align-items:center;padding:24px 16px 100px;
}

/* ── Card ──────────────────────────────────── */
.card{background:#fff;border-radius:20px;overflow:hidden;width:100%;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,.1);}
.card-top{background:#2d6a4f;padding:20px 22px;display:flex;align-items:center;justify-content:space-between;}
.brand{font-family:'DM Serif Display',serif;font-size:1.1rem;color:#fff;}
.brand-sub{font-size:10px;color:rgba(255,255,255,.7);letter-spacing:.08em;text-transform:uppercase;margin-top:2px;}
.brand-icon{width:36px;height:36px;border-radius:9px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;}
.brand-icon i{color:#fff;font-size:20px;}
.pet-body{padding:22px;}

/* ── Pet info ──────────────────────────────── */
.pet-hero{display:flex;gap:16px;align-items:flex-start;margin-bottom:18px;}
.pet-photo{width:100px;height:100px;border-radius:12px;object-fit:cover;border:2px solid #e5e7eb;flex-shrink:0;}
.pet-photo-placeholder{width:100px;height:100px;border-radius:12px;background:#f0fdf4;border:2px solid #e5e7eb;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.pet-photo-placeholder i{font-size:2.5rem;color:#2d6a4f;}
.pet-name{font-family:'DM Serif Display',serif;font-size:1.7rem;color:#1a1208;line-height:1.1;margin-bottom:4px;}
.pet-type{font-size:13px;color:#44352a;margin-bottom:10px;}
.chip{display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:500;margin-right:4px;margin-bottom:4px;}
.chip-green{background:#f0fdf4;color:#2d6a4f;}
.chip-blue{background:#eff6ff;color:#1e40af;}
.chip-amber{background:#fffbeb;color:#92400e;}
.section-title{font-size:10px;text-transform:uppercase;letter-spacing:.09em;font-weight:600;color:#8a7060;margin-bottom:8px;}
hr{border:none;border-top:.5px solid #e5e7eb;margin:16px 0;}

/* ── Owner row ─────────────────────────────── */
.owner-row{display:flex;align-items:center;gap:14px;margin-bottom:16px;}
.owner-av{width:48px;height:48px;border-radius:50%;background:#f0fdf4;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:600;color:#2d6a4f;flex-shrink:0;}
.owner-name{font-size:15px;font-weight:600;color:#1a1208;}
.owner-sub{font-size:11px;color:#8a7060;}

/* ── Buttons ───────────────────────────────── */
.action-btn{
  display:flex;align-items:center;justify-content:center;gap:9px;
  width:100%;padding:14px;border-radius:10px;
  font-size:14px;font-weight:500;
  text-decoration:none;cursor:pointer;
  border:none;font-family:'DM Sans',sans-serif;
  transition:opacity .12s;
  margin-bottom:8px;
}
.action-btn:hover{opacity:.88;}
.btn-primary  {background:#2d6a4f;color:#fff;}
.btn-amber    {background:#92400e;color:#fff;}
.btn-outline  {background:#f0fdf4;color:#2d6a4f;border:.5px solid rgba(45,106,79,.25);}
.btn-danger   {background:#fef2f2;color:#8b2635;border:.5px solid rgba(139,38,53,.2);}
.btn-disabled {background:#f2efeb;color:#8a7060;cursor:default;}
.btn-sm{font-size:12px;padding:9px 14px;width:auto;display:inline-flex;}

/* ── Banners ───────────────────────────────── */
.found-banner{background:#fffbeb;border:.5px solid rgba(146,64,14,.2);border-radius:10px;padding:12px 14px;display:flex;gap:10px;align-items:flex-start;margin-bottom:16px;font-size:12px;color:#44352a;}
.found-banner i{color:#92400e;font-size:18px;flex-shrink:0;margin-top:1px;}
.success-banner{background:#f0fdf4;border:.5px solid rgba(45,106,79,.2);border-radius:10px;padding:12px 14px;display:flex;gap:10px;align-items:flex-start;margin-bottom:12px;font-size:13px;color:#2d6a4f;}
.success-banner i{font-size:18px;flex-shrink:0;}
.error-banner{background:#fef2f2;border:.5px solid rgba(139,38,53,.2);border-radius:10px;padding:10px 14px;display:flex;gap:8px;align-items:center;margin-bottom:12px;font-size:13px;color:#8b2635;}
.privacy-note{font-size:11px;color:#8a7060;text-align:center;margin-top:8px;line-height:1.5;}

/* ── Slide-up contact reveal ───────────────── */
#contactReveal{
  position:fixed;bottom:0;left:0;right:0;
  background:#fff;
  border-radius:20px 20px 0 0;
  box-shadow:0 -4px 24px rgba(0,0,0,.12);
  padding:24px 22px 36px;
  transform:translateY(100%);
  transition:transform .35s cubic-bezier(.34,1.1,.64,1);
  z-index:100;
  max-width:480px;
  margin:0 auto;
}
#contactReveal.open{transform:translateY(0);}
.reveal-handle{width:36px;height:4px;border-radius:2px;background:#e5e7eb;margin:0 auto 18px;}
.reveal-title{font-family:'DM Serif Display',serif;font-size:1.1rem;color:#1a1208;margin-bottom:4px;text-align:center;}
.reveal-sub{font-size:12px;color:#8a7060;text-align:center;margin-bottom:20px;line-height:1.6;}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.3);z-index:99;display:none;}
.overlay.show{display:block;}

/* ── No-contact ────────────────────────────── */
.no-contact{font-size:13px;color:#8a7060;background:#f7f5f2;border-radius:8px;padding:14px;text-align:center;line-height:1.6;}

/* ── Card footer ───────────────────────────── */
.card-foot{background:#f7f5f2;border-top:.5px solid #e5e7eb;padding:10px 22px;display:flex;align-items:center;justify-content:space-between;}
.foot-id{font-size:10px;color:#8a7060;}
.foot-brand{font-size:10px;color:#8a7060;}

/* ── Owner view ────────────────────────────── */
.owner-banner{background:#f0fdf4;border:.5px solid rgba(45,106,79,.2);border-radius:10px;padding:12px 14px;font-size:13px;color:#2d6a4f;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
</style>
</head>
<body>

<!-- Overlay for contact slide-up -->
<div class="overlay" id="overlay" onclick="closeReveal()"></div>

<div class="card">
  <div class="card-top">
    <div>
      <div class="brand">Pawrtal</div>
      <div class="brand-sub">Pet Identification</div>
    </div>
    <div class="brand-icon"><i class="ti ti-paw"></i></div>
  </div>

  <div class="pet-body">

    <!-- Pet hero -->
    <div class="pet-hero">
      <?php if (!empty($pet['photo'])): ?>
        <img src="../uploads/pets/<?= htmlspecialchars($pet['photo']) ?>"
             alt="<?= htmlspecialchars($pet['name']) ?>" class="pet-photo">
      <?php else: ?>
        <div class="pet-photo-placeholder"><i class="ti ti-paw"></i></div>
      <?php endif; ?>
      <div>
        <div class="pet-name"><?= htmlspecialchars($pet['name']) ?></div>
        <div class="pet-type">
          <?= ucfirst(htmlspecialchars($pet['type'])) ?>
          <?= !empty($pet['breed']) ? ' &middot; ' . htmlspecialchars($pet['breed']) : '' ?>
        </div>
        <?php if ($pet['gender'] !== 'unknown'): ?>
          <span class="chip chip-blue"><i class="ti ti-gender-male-female" style="font-size:12px;"></i> <?= $gender_label ?></span>
        <?php endif; ?>
        <?php if (!empty($pet['age_years'])): ?>
          <span class="chip chip-amber"><i class="ti ti-clock" style="font-size:12px;"></i> <?= $pet['age_years'] ?> <?= $pet['age_years'] == 1 ? 'yr' : 'yrs' ?></span>
        <?php endif; ?>
        <?php if (!empty($pet['color'])): ?>
          <span class="chip chip-green"><i class="ti ti-color-swatch" style="font-size:12px;"></i> <?= htmlspecialchars($pet['color']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($pet['description'])): ?>
    <div style="margin-bottom:16px;">
      <div class="section-title">About <?= htmlspecialchars($pet['name']) ?></div>
      <div style="font-size:13px;color:#44352a;line-height:1.6;background:#f7f5f2;border-radius:8px;padding:10px 12px;">
        <?= nl2br(htmlspecialchars($pet['description'])) ?>
      </div>
    </div>
    <?php endif; ?>

    <hr>

    <?php if ($is_owner): ?>
    <!-- ── OWNER VIEW ─────────────────────────────────────── -->
    <div class="owner-banner">
      <i class="ti ti-shield-check" style="font-size:18px;flex-shrink:0;"></i>
      This is your pet's public QR profile. This is what others see when they scan the tag.
    </div>
    <div class="section-title">What others see</div>
    <div class="owner-row">
      <div class="owner-av"><?= strtoupper(substr($first_name, 0, 1)) ?></div>
      <div>
        <div class="owner-name"><?= htmlspecialchars($first_name) ?></div>
        <div class="owner-sub">Registered owner of <?= htmlspecialchars($pet['name']) ?></div>
      </div>
    </div>
    <?php if (!empty($pet['owner_phone'])): ?>
    <div style="background:#f0fdf4;border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;font-size:14px;font-weight:500;color:#2d6a4f;margin-bottom:8px;">
      <i class="ti ti-phone" style="font-size:18px;"></i>
      <?= htmlspecialchars($pet['owner_phone']) ?> <span style="font-size:11px;color:#8a7060;font-weight:400;">(visible to finders)</span>
    </div>
    <?php else: ?>
    <div class="no-contact">
      <i class="ti ti-phone-off" style="font-size:1.4rem;display:block;margin-bottom:6px;color:#ccc;"></i>
      No contact number set. <a href="../dashboard/my_pets.php?action=edit&id=<?= $pet['id'] ?>" style="color:#2d6a4f;font-weight:500;">Add one →</a>
    </div>
    <?php endif; ?>
    <a href="../dashboard/my_pets.php?action=edit&id=<?= $pet['id'] ?>" class="action-btn btn-outline" style="margin-top:12px;">
      <i class="ti ti-edit" style="font-size:16px;"></i> Edit pet details
    </a>

    <?php else: ?>
    <!-- ── FINDER / PUBLIC VIEW ───────────────────────────── -->

    <?php if ($claimSent): ?>
    <div class="success-banner">
      <i class="ti ti-circle-check"></i>
      <div><strong>Owner notified.</strong> The owner has been alerted that you believe this may be your pet. They'll review your report and reach out.</div>
    </div>
    <?php endif; ?>

    <?php if ($claimError): ?>
    <div class="error-banner"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($claimError) ?></div>
    <?php endif; ?>

    <!-- Found banner — shown before confirming -->
    <div class="found-banner" id="foundBanner">
      <i class="ti ti-alert-triangle"></i>
      <div><strong>Did you find <?= htmlspecialchars($pet['name']) ?>?</strong> Tap a button below to let the owner know or get contact details.</div>
    </div>

    <!-- ── Primary action: "I saw this pet" ───────────────── -->
    <button class="action-btn btn-amber" onclick="openReveal()" id="sawBtn">
      <i class="ti ti-eye" style="font-size:18px;"></i>
      I found / I see this pet — contact owner
    </button>

    <div class="privacy-note">
      <i class="ti ti-shield" style="font-size:12px;vertical-align:-1px;"></i>
      Contact details are shown after confirmation to reduce misuse.
    </div>

    <hr>

    <!-- ── Secondary: logged-in user actions ──────────────── -->
    <?php if ($is_logged_in && !$claimSent): ?>
    <div class="section-title" style="margin-top:2px;">Is this your lost pet?</div>

    <form method="POST" style="margin-bottom:8px;">
      <input type="hidden" name="action" value="i_think_its_mine">
      <button type="submit" class="action-btn btn-danger">
        <i class="ti ti-search-heart" style="font-size:18px;"></i>
        I think this is my missing pet
      </button>
    </form>
    <p style="font-size:11px;color:#8a7060;margin-bottom:12px;line-height:1.5;text-align:center;">
      This sends a notification to the owner with your name and links to your most recent found report.
    </p>

    <?php elseif (!$is_logged_in): ?>
    <div style="background:#f7f5f2;border-radius:10px;padding:14px;text-align:center;margin-top:4px;">
      <div style="font-size:13px;color:#44352a;margin-bottom:10px;">
        <strong>Is this your missing pet?</strong><br>
        <span style="font-size:12px;color:#8a7060;">Sign in to notify the owner directly.</span>
      </div>
      <a href="../auth/login.php" class="action-btn btn-outline btn-sm" style="display:inline-flex;">
        <i class="ti ti-login" style="font-size:15px;"></i> Sign in to Pawrtal
      </a>
    </div>
    <?php endif; ?>

    <?php endif; ?><!-- end finder view -->

  </div><!-- /pet-body -->

  <div class="card-foot">
    <div class="foot-id">ID: <?= strtoupper(substr($pet['qr_token'], 0, 8)) ?></div>
    <div class="foot-brand">pawrtal.com</div>
  </div>
</div>

<div style="margin-top:20px;text-align:center;font-size:11px;color:#8a7060;">
  Powered by <a href="index.php" style="color:#2d6a4f;font-weight:500;text-decoration:none;">Pawrtal</a> &middot; Community-based pet recovery
</div>

<!-- ── Contact reveal panel ──────────────────────────────────── -->
<div id="contactReveal">
  <div class="reveal-handle"></div>
  <div class="reveal-title">Contact the owner</div>
  <div class="reveal-sub">
    You're about to contact <strong><?= htmlspecialchars($first_name) ?></strong>,
    the registered owner of <strong><?= htmlspecialchars($pet['name']) ?></strong>.<br>
    Please only proceed if you genuinely have this pet.
  </div>

  <?php if (!empty($pet['owner_phone'])): ?>
  <a href="tel:<?= htmlspecialchars($pet['owner_phone']) ?>" class="action-btn btn-primary">
    <i class="ti ti-phone" style="font-size:20px;"></i>
    Call <?= htmlspecialchars($pet['owner_phone']) ?>
  </a>
  <?php else: ?>
  <div class="no-contact">
    <i class="ti ti-phone-off" style="font-size:1.4rem;display:block;margin-bottom:8px;color:#ccc;"></i>
    No phone number on file.<br>
    <a href="index.php" style="color:#2d6a4f;font-weight:500;">Report this pet as found on Pawrtal</a> so the owner is notified.
  </div>
  <?php endif; ?>

  <!-- Also post a found report -->
  <?php if ($is_logged_in): ?>
  <form method="POST" style="margin-top:4px;">
    <input type="hidden" name="action" value="i_found_this_pet">
    <button type="submit" class="action-btn btn-outline">
      <i class="ti ti-circle-check" style="font-size:18px;"></i>
      Also post a found report
    </button>
  </form>
  <?php else: ?>
  <a href="../auth/login.php" class="action-btn btn-outline">
    <i class="ti ti-circle-check" style="font-size:18px;"></i>
    Sign in to post a found report
  </a>
  <?php endif; ?>

  <button class="action-btn" onclick="closeReveal()" style="background:none;color:#8a7060;font-size:13px;margin-top:4px;border:none;width:100%;cursor:pointer;padding:8px;">
    Cancel
  </button>
</div>

<script>
function openReveal() {
  document.getElementById('contactReveal').classList.add('open');
  document.getElementById('overlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function closeReveal() {
  document.getElementById('contactReveal').classList.remove('open');
  document.getElementById('overlay').classList.remove('show');
  document.body.style.overflow = '';
}
// Close on ESC
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeReveal(); });
</script>

</body>
</html>