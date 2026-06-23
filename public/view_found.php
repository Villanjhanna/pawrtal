<?php
session_start();
include '../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: found.php"); exit(); }

// Load report — contact info fetched but conditionally shown
$report = $conn->query("
    SELECT f.*, u.name AS finder_name, u.id AS finder_user_id
    FROM found_reports f
    JOIN users u ON f.user_id = u.id
    WHERE f.id = $id
")->fetch_assoc();

if (!$report) { header("Location: found.php"); exit(); }

$conn->query("UPDATE found_reports SET views = COALESCE(views,0)+1 WHERE id=$id");

$is_finder    = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$report['finder_user_id'];
$is_logged_in = isset($_SESSION['user_id']);

// ── Can the current user see contact details? ─────────────────────
// Yes if: they are the finder, OR they are the owner of a confirmed/matched match for this found report
$contact_revealed = $is_finder;
$reveal_reason    = '';

if ($is_logged_in && !$is_finder) {
    $viewer_id = (int)$_SESSION['user_id'];
    $match = $conn->query("
        SELECT m.status FROM matches m
        JOIN lost_reports l ON m.lost_report_id = l.id
        WHERE m.found_report_id = $id
          AND l.user_id = $viewer_id
          AND m.status IN ('matched','reunited','confirmed')
        LIMIT 1
    ")->fetch_assoc();

    if ($match) {
        $contact_revealed = true;
        $reveal_reason    = $match['status']; // 'matched', 'confirmed', or 'reunited'
    }
}

$gender_label = match($report['gender'] ?? '') {
    'male'   => 'Male',
    'female' => 'Female',
    default  => 'Unknown',
};

function timeAgo($dt) {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff/60).'m ago';
    if ($diff < 86400)  return floor($diff/3600).'h ago';
    if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('M j', strtotime($dt));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Found <?= ucfirst(htmlspecialchars($report['pet_type'])) ?> — Pawrtal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f7f5f2;--surface:#fff;--surface2:#f2efeb;
  --border:rgba(0,0,0,.08);--border-md:rgba(0,0,0,.14);
  --green:#2d6a4f;--green-dk:#1b4332;--green-lt:#f0fdf4;
  --red:#8b2635;--red-lt:#fef2f2;
  --amber:#92400e;--amber-lt:#fffbeb;
  --text:#1a1208;--text2:#44352a;--text3:#8a7060;
  --radius:8px;--radius-lg:12px;
  --font:'DM Sans',system-ui,sans-serif;
  --display:'DM Serif Display',Georgia,serif;
}
body{background:var(--bg);color:var(--text);font-family:var(--font);font-size:14px;line-height:1.6;}
a{text-decoration:none;color:inherit;}
nav{background:var(--surface);border-bottom:.5px solid var(--border);padding:0 2rem;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 1px 6px rgba(0,0,0,.04);}
.nav-logo{display:flex;align-items:center;gap:10px;}
.nav-logo-icon{width:32px;height:32px;border-radius:9px;background:var(--green);display:flex;align-items:center;justify-content:center;}
.nav-logo-icon i{color:#fff;font-size:17px;}
.nav-logo-name{font-family:var(--display);font-size:1.05rem;color:var(--text);}
.nav-logo-sub{font-size:10px;color:var(--text3);margin-top:-2px;}
.nav-links{display:flex;align-items:center;gap:1.5rem;}
.nav-links a{color:var(--text3);font-size:13px;font-weight:500;}
.nav-links a:hover{color:var(--green);}
.nav-actions{display:flex;align-items:center;gap:8px;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius);border:.5px solid var(--border-md);background:var(--surface);color:var(--text);font-size:13px;font-family:var(--font);cursor:pointer;font-weight:500;white-space:nowrap;transition:all .12s;text-decoration:none;}
.btn:hover{background:var(--surface2);}
.btn.primary{background:var(--green);color:#fff;border-color:var(--green);}
.btn.primary:hover{background:var(--green-dk);}
.btn.sm{padding:5px 11px;font-size:12px;}
.breadcrumb{max-width:1100px;margin:0 auto;padding:1rem 2rem 0;display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text3);}
.breadcrumb a{color:var(--text3);}
.breadcrumb a:hover{color:var(--green);}
.breadcrumb i{font-size:11px;}
.page-wrap{max-width:1100px;margin:0 auto;padding:1.25rem 2rem 3rem;display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;}
.detail-photo{width:100%;border-radius:var(--radius-lg);overflow:hidden;background:var(--surface2);aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;margin-bottom:16px;}
.detail-photo img{width:100%;height:100%;object-fit:cover;}
.detail-photo i{font-size:5rem;color:#ccc;}
.card{background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;margin-bottom:14px;}
.card-title{font-family:var(--display);font-size:.95rem;color:var(--text);margin-bottom:12px;}
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.detail-item{background:var(--surface2);border-radius:var(--radius);padding:10px 12px;}
.detail-label{font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:2px;}
.detail-value{font-size:13px;font-weight:500;color:var(--text);}
.status-banner{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--radius);font-size:13px;margin-bottom:14px;background:var(--green-lt);color:var(--green);border:.5px solid rgba(45,106,79,.2);}
.sidebar-card{background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;margin-bottom:14px;}
.sidebar-title{font-size:10px;text-transform:uppercase;letter-spacing:.09em;color:var(--text3);font-weight:600;margin-bottom:12px;}
.finder-av-wrap{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
.finder-av{width:42px;height:42px;border-radius:50%;background:var(--green-lt);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:600;color:var(--green);flex-shrink:0;}
.contact-btn{display:flex;align-items:center;gap:9px;padding:11px 14px;border-radius:var(--radius);font-size:13px;font-weight:500;text-decoration:none;margin-bottom:8px;transition:opacity .1s;border:.5px solid transparent;}
.contact-btn:hover{opacity:.88;}
.contact-btn.phone{background:var(--green);color:#fff;}
.contact-btn.email{background:var(--green-lt);color:var(--green);border-color:rgba(45,106,79,.2);}
.contact-btn i{font-size:17px;}
.lock-box{background:var(--surface2);border:.5px solid var(--border);border-radius:var(--radius);padding:16px;text-align:center;}
.lock-box i{font-size:1.8rem;color:#ccc;display:block;margin-bottom:8px;}
.lock-box p{font-size:12px;color:var(--text3);line-height:1.7;}
.reveal-banner{background:var(--green-lt);border:.5px solid rgba(45,106,79,.2);border-radius:var(--radius);padding:10px 13px;font-size:12px;color:var(--green);display:flex;align-items:center;gap:7px;margin-bottom:12px;}
footer{text-align:center;padding:1.5rem;border-top:.5px solid var(--border);font-size:12px;color:var(--text3);background:var(--surface);}
footer a{color:var(--green);font-weight:500;}
@media(max-width:760px){.page-wrap{grid-template-columns:1fr;}.nav-links{display:none;}}
</style>
</head>
<body>

<nav>
  <div class="nav-logo">
    <div class="nav-logo-icon"><i class="ti ti-paw"></i></div>
    <div><div class="nav-logo-name">Pawrtal</div><div class="nav-logo-sub">Lost &amp; Found Pet Recovery</div></div>
  </div>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <a href="lost.php">Lost pets</a>
    <a href="found.php">Found pets</a>
  </div>
  <div class="nav-actions">
    <?php if ($is_logged_in): ?>
      <a href="../dashboard/index.php" class="btn sm"><i class="ti ti-layout-dashboard"></i> Dashboard</a>
      <a href="../auth/logout.php"     class="btn sm"><i class="ti ti-logout"></i> Logout</a>
    <?php else: ?>
      <a href="../auth/login.php"    class="btn sm">Log in</a>
      <a href="../auth/register.php" class="btn primary sm">Join free</a>
    <?php endif; ?>
  </div>
</nav>

<div class="breadcrumb">
  <a href="index.php">Home</a>
  <i class="ti ti-chevron-right"></i>
  <a href="found.php">Found pets</a>
  <i class="ti ti-chevron-right"></i>
  <span>Found <?= ucfirst(htmlspecialchars($report['pet_type'])) ?></span>
</div>

<div class="page-wrap">

  <!-- ── Left column ─────────────────────────────────────── -->
  <div>

    <div class="status-banner">
      <i class="ti ti-circle-check"></i>
      This pet was found. The finder is waiting to connect with the owner.
    </div>

    <div class="detail-photo">
      <?php if (!empty($report['photo'])): ?>
        <img src="../uploads/reports/<?= htmlspecialchars($report['photo']) ?>" alt="Found pet">
      <?php else: ?>
        <i class="ti ti-paw"></i>
      <?php endif; ?>
    </div>

    <div class="card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;">
        <div>
          <div style="font-family:var(--display);font-size:1.5rem;color:var(--text);line-height:1.1;">
            Found <?= ucfirst(htmlspecialchars($report['pet_type'])) ?>
            <?= !empty($report['breed']) ? ' &middot; '.htmlspecialchars($report['breed']) : '' ?>
          </div>
          <div style="font-size:13px;color:var(--text3);margin-top:3px;">Found in <?= htmlspecialchars($report['found_place']) ?></div>
        </div>
        <span style="font-size:10px;font-weight:600;padding:4px 10px;border-radius:99px;background:var(--green-lt);color:var(--green);text-transform:uppercase;letter-spacing:.06em;flex-shrink:0;margin-top:4px;">Found</span>
      </div>

      <div class="detail-grid">
        <?php foreach ([
            ['Color',    $report['color'] ?? '—'],
            ['Gender',   $gender_label],
            ['Found on', date('M j, Y', strtotime($report['found_date']))],
            ['Posted',   timeAgo($report['created_at'])],
            ['Location', $report['found_place']],
            ['Views',    ($report['views'] ?? 0) . ' views'],
        ] as [$label, $val]): ?>
        <div class="detail-item">
          <div class="detail-label"><?= $label ?></div>
          <div class="detail-value"><?= htmlspecialchars($val) ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (!empty($report['description'])): ?>
      <div style="margin-top:14px;padding-top:14px;border-top:.5px solid var(--border);">
        <div class="detail-label" style="margin-bottom:6px;">Description</div>
        <div style="font-size:13px;color:var(--text2);line-height:1.7;"><?= nl2br(htmlspecialchars($report['description'])) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Share -->
    <div class="card">
      <div class="card-title">Share this report</div>
      <div style="font-size:12px;color:var(--text3);margin-bottom:12px;">Share so the owner can find their way back to this pet.</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>"
           target="_blank" class="btn sm"><i class="ti ti-brand-facebook"></i> Share on Facebook</a>
        <button onclick="copyLink()" class="btn sm" id="copyBtn"><i class="ti ti-link"></i> Copy link</button>
      </div>
    </div>

  </div>

  <!-- ── Right sidebar ───────────────────────────────────── -->
  <div>

    <div class="sidebar-card">
      <div class="sidebar-title">Finder</div>
      <div class="finder-av-wrap">
        <div class="finder-av"><?= strtoupper(substr($report['contact_name'] ?? $report['finder_name'], 0, 1)) ?></div>
        <div>
          <?php if ($is_finder): ?>
            <div style="font-size:13px;font-weight:600;color:var(--text);"><?= htmlspecialchars($report['contact_name']) ?></div>
            <div style="font-size:11px;color:var(--text3);">You — this is your report</div>
          <?php elseif ($contact_revealed): ?>
            <div style="font-size:13px;font-weight:600;color:var(--text);"><?= htmlspecialchars($report['contact_name']) ?></div>
            <div style="font-size:11px;color:var(--text3);">Found this pet</div>
          <?php else: ?>
            <!-- Mask the name slightly for non-matched public -->
            <div style="font-size:13px;font-weight:600;color:var(--text);"><?= htmlspecialchars(explode(' ', $report['contact_name'])[0]) ?></div>
            <div style="font-size:11px;color:var(--text3);">Found this pet</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($contact_revealed): ?>
      <!-- ── Contact details revealed ──────────────────── -->
      <?php if ($reveal_reason && !$is_finder): ?>
      <div class="reveal-banner">
        <i class="ti ti-lock-open" style="font-size:15px;flex-shrink:0;"></i>
        Contact details unlocked — your match was confirmed.
      </div>
      <?php endif; ?>

      <?php if (!empty($report['contact_phone'])): ?>
      <a href="tel:<?= htmlspecialchars($report['contact_phone']) ?>" class="contact-btn phone">
        <i class="ti ti-phone"></i> Call <?= htmlspecialchars($report['contact_phone']) ?>
      </a>
      <?php endif; ?>

      <?php if ($is_finder && !empty($report['contact_phone'])): ?>
      <div style="font-size:11px;color:var(--text3);margin-top:4px;">Your number — shown to owners after match confirmation.</div>
      <?php endif; ?>

      <?php if ($is_finder): ?>
      <a href="../dashboard/my_reports.php?tab=found" class="btn sm" style="display:inline-flex;margin-top:8px;">
        <i class="ti ti-edit"></i> Manage this report
      </a>
      <?php endif; ?>

      <?php else: ?>
      <!-- ── Contact details locked ─────────────────────── -->
      <div class="lock-box">
        <i class="ti ti-lock"></i>
        <p>
          <strong>Contact details are private.</strong><br>
          The finder's phone number is only revealed to the pet owner after they confirm a match through Pawrtal's system.
        </p>
      </div>

      <?php if ($is_logged_in): ?>
<div style="margin-top:12px;display:flex;flex-direction:column;gap:8px;">
  <a href="../dashboard/report_lost.php" style="display:flex;align-items:center;gap:8px;padding:11px 14px;border-radius:var(--radius);background:#fffbeb;color:var(--amber);border:.5px solid rgba(146,64,14,.2);font-size:13px;font-weight:500;text-decoration:none;">
    <i class="ti ti-alert-circle" style="font-size:16px;"></i> Post a lost report to get matched
  </a>
  <a href="../dashboard/submit_claim.php?found_id=<?= $id ?>" style="display:flex;align-items:center;gap:8px;padding:11px 14px;border-radius:var(--radius);background:var(--green-lt);color:var(--green);border:.5px solid rgba(45,106,79,.2);font-size:13px;font-weight:500;text-decoration:none;">
    <i class="ti ti-search-heart" style="font-size:16px;"></i> This is my pet — submit proof
  </a>
  <p style="font-size:11px;color:var(--text3);text-align:center;line-height:1.5;">
    Already know this is your pet? Submit a registered pet profile and a distinguishing mark for the finder to verify.
  </p>
</div>
      <?php else: ?>
      <div style="margin-top:12px;">
        <a href="../auth/login.php" style="display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 14px;border-radius:var(--radius);background:var(--green);color:#fff;font-size:13px;font-weight:500;text-decoration:none;">
          <i class="ti ti-login" style="font-size:16px;"></i> Log in to report a lost pet
        </a>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Quick facts -->
    <div class="sidebar-card">
      <div class="sidebar-title">Quick facts</div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
        <?php foreach ([
            ['Type',   ucfirst($report['pet_type'])],
            ['Breed',  !empty($report['breed']) ? $report['breed'] : null],
            ['Color',  $report['color'] ?? '—'],
            ['Gender', $gender_label],
            ['Found',  date('M j, Y', strtotime($report['found_date']))],
        ] as [$label, $val]):
            if ($val === null) continue;
        ?>
        <div style="display:flex;justify-content:space-between;padding-bottom:8px;border-bottom:.5px solid var(--border);">
          <span style="color:var(--text3);"><?= $label ?></span>
          <span style="font-weight:500;"><?= htmlspecialchars($val) ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--text3);">Location</span>
          <span style="font-weight:500;font-size:12px;text-align:right;max-width:180px;"><?= htmlspecialchars($report['found_place']) ?></span>
        </div>
      </div>
    </div>

    <!-- Info box for how the system works -->
    <?php if (!$contact_revealed && !$is_finder): ?>
    <div class="sidebar-card" style="background:var(--amber-lt);border-color:rgba(146,64,14,.2);">
      <div style="font-size:12px;font-weight:500;color:var(--amber);margin-bottom:6px;"><i class="ti ti-info-circle" style="font-size:14px;vertical-align:-1px;"></i> How contact works</div>
      <div style="font-size:12px;color:var(--text2);line-height:1.7;">
        <ol style="padding-left:16px;display:flex;flex-direction:column;gap:4px;">
          <li>Post a lost report for your missing pet.</li>
          <li>Our system matches it to found reports like this one.</li>
          <li>You review the match in your dashboard.</li>
          <li>After you confirm, the finder's contact details are revealed.</li>
        </ol>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<footer>
  &copy; <?= date('Y') ?> Pawrtal &middot; <a href="index.php">Home</a> &middot; <a href="lost.php">Lost pets</a> &middot; <a href="found.php">Found pets</a>
</footer>

<script>
function copyLink() {
  navigator.clipboard.writeText(window.location.href).then(() => {
    const btn = document.getElementById('copyBtn');
    btn.innerHTML = '<i class="ti ti-check"></i> Copied!';
    setTimeout(() => btn.innerHTML = '<i class="ti ti-link"></i> Copy link', 2000);
  });
}
</script>
</body>
</html>
