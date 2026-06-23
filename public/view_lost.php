<?php
session_start();
include '../config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { header("Location: lost.php"); exit(); }

// Load report — NO contact info in the public query
$report = $conn->query("
    SELECT l.*, u.name AS owner_name, u.id AS owner_user_id,
           u.phone AS owner_phone, u.email AS owner_email
    FROM lost_reports l
    JOIN users u ON l.user_id = u.id
    WHERE l.id = $id
")->fetch_assoc();

if (!$report) { header("Location: lost.php"); exit(); }

$conn->query("UPDATE lost_reports SET views = COALESCE(views,0)+1 WHERE id=$id");

// Is the current visitor the owner?
$is_owner     = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$report['owner_user_id'];
$is_logged_in = isset($_SESSION['user_id']);

$viewer_found_report  = null; // their most recent active found report
$existing_match       = null; // match between their found report and this lost report
 
if ($is_logged_in && !$is_owner) {
    $viewer_id = (int)$_SESSION['user_id'];
 
    // Do they have any active found report?
    $viewer_found_report = $conn->query("
        SELECT id, pet_type, found_place, created_at
        FROM found_reports
        WHERE user_id = $viewer_id AND status = 'active'
        ORDER BY created_at DESC
        LIMIT 1
    ")->fetch_assoc();
 
    // Is there already a match between their found report and this lost report?
    if ($viewer_found_report) {
        $frid = (int)$viewer_found_report['id'];
        $existing_match = $conn->query("
            SELECT m.id, m.status, m.score
            FROM matches m
            WHERE m.lost_report_id  = $id
              AND m.found_report_id = $frid
            LIMIT 1
        ")->fetch_assoc();
    }
}
// Other active reports by same owner
$moreReports = $conn->query("
    SELECT * FROM lost_reports
    WHERE user_id={$report['owner_user_id']} AND id != $id AND status='active'
    ORDER BY created_at DESC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

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
<title><?= htmlspecialchars($report['pet_name']) ?> — Lost Pet — Pawrtal</title>
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
.status-banner{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--radius);font-size:13px;margin-bottom:14px;border:.5px solid;}
.banner-lost    {background:var(--red-lt);   color:var(--red);   border-color:rgba(139,38,53,.2);}
.banner-reunited{background:var(--green-lt); color:var(--green); border-color:rgba(45,106,79,.2);}
.sidebar-card{background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px;margin-bottom:14px;}
.sidebar-title{font-size:10px;text-transform:uppercase;letter-spacing:.09em;color:var(--text3);font-weight:600;margin-bottom:12px;}
.owner-av-wrap{display:flex;align-items:center;gap:12px;margin-bottom:14px;}
.owner-av{width:42px;height:42px;border-radius:50%;background:var(--green-lt);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:600;color:var(--green);flex-shrink:0;}
.action-btn{display:flex;align-items:center;gap:9px;padding:12px 14px;border-radius:var(--radius);font-size:13px;font-weight:500;text-decoration:none;margin-bottom:8px;transition:opacity .1s;border:none;cursor:pointer;font-family:var(--font);width:100%;justify-content:center;}
.action-btn:hover{opacity:.88;}
.ab-green{background:var(--green);color:#fff;}
.ab-amber{background:var(--amber);color:#fff;}
.ab-outline{background:var(--green-lt);color:var(--green);border:.5px solid rgba(45,106,79,.2);}
.lock-box{background:var(--surface2);border:.5px solid var(--border);border-radius:var(--radius);padding:14px;text-align:center;}
.lock-box i{font-size:1.5rem;color:#ccc;display:block;margin-bottom:8px;}
.lock-box p{font-size:12px;color:var(--text3);line-height:1.6;}
.more-card{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:.5px solid var(--border);}
.more-card:last-child{border-bottom:none;}
.more-thumb{width:44px;height:44px;border-radius:var(--radius);background:var(--surface2);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;}
.more-thumb img{width:100%;height:100%;object-fit:cover;}
.more-thumb i{font-size:1.3rem;color:#ccc;}
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
      <a href="../auth/login.php"    class="btn sm">Sign in</a>
      <a href="../auth/register.php" class="btn primary sm">Join free</a>
    <?php endif; ?>
  </div>
</nav>

<div class="breadcrumb">
  <a href="index.php">Home</a>
  <i class="ti ti-chevron-right"></i>
  <a href="lost.php">Lost pets</a>
  <i class="ti ti-chevron-right"></i>
  <span><?= htmlspecialchars($report['pet_name']) ?></span>
</div>

<div class="page-wrap">

  <!-- ── Left column ─────────────────────────────────────── -->
  <div>

    <div class="status-banner <?= $report['status'] === 'reunited' ? 'banner-reunited' : 'banner-lost' ?>">
      <i class="ti ti-<?= $report['status'] === 'reunited' ? 'heart-handshake' : 'alert-circle' ?>"></i>
      <?= $report['status'] === 'reunited'
          ? 'This pet has been reunited with their owner.'
          : 'This pet is still missing. Please share to help find them.' ?>
    </div>

    <div class="detail-photo">
      <?php if (!empty($report['photo'])): ?>
        <img src="../uploads/reports/<?= htmlspecialchars($report['photo']) ?>" alt="<?= htmlspecialchars($report['pet_name']) ?>">
      <?php else: ?>
        <i class="ti ti-paw"></i>
      <?php endif; ?>
    </div>

    <div class="card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:14px;">
        <div>
          <div style="font-family:var(--display);font-size:1.5rem;color:var(--text);line-height:1.1;"><?= htmlspecialchars($report['pet_name']) ?></div>
          <div style="font-size:13px;color:var(--text3);margin-top:3px;">
            <?= ucfirst(htmlspecialchars($report['pet_type'])) ?>
            <?= !empty($report['breed']) ? ' &middot; '.htmlspecialchars($report['breed']) : '' ?>
          </div>
        </div>
        <span style="font-size:10px;font-weight:600;padding:4px 10px;border-radius:99px;background:var(--red-lt);color:var(--red);text-transform:uppercase;letter-spacing:.06em;flex-shrink:0;margin-top:4px;">Lost</span>
      </div>

      <div class="detail-grid">
        <?php foreach ([
            ['Color',     $report['color']      ?? '—'],
            ['Gender',    $gender_label],
            ['Last seen', date('M j, Y', strtotime($report['last_seen_date']))],
            ['Posted',    timeAgo($report['created_at'])],
            ['Location',  $report['last_seen_place']],
            ['Views',     ($report['views'] ?? 0) . ' views'],
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
      <div class="card-title">Help spread the word</div>
      <div style="font-size:12px;color:var(--text3);margin-bottom:12px;">Share this report so more people can help find <?= htmlspecialchars($report['pet_name']) ?>.</div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']) ?>"
           target="_blank" class="btn sm"><i class="ti ti-brand-facebook"></i> Share on Facebook</a>
        <button onclick="copyLink()" class="btn sm" id="copyBtn"><i class="ti ti-link"></i> Copy link</button>
      </div>
    </div>

    <?php if (!empty($moreReports)): ?>
    <div class="card">
      <div class="card-title">Other reports by this owner</div>
      <?php foreach ($moreReports as $m): ?>
      <a href="view_lost.php?id=<?= $m['id'] ?>" class="more-card" style="text-decoration:none;display:flex;">
        <div class="more-thumb">
          <?php if (!empty($m['photo'])): ?><img src="../uploads/reports/<?= htmlspecialchars($m['photo']) ?>" alt=""><?php else: ?><i class="ti ti-paw"></i><?php endif; ?>
        </div>
        <div>
          <div style="font-size:13px;font-weight:500;color:var(--text);"><?= htmlspecialchars($m['pet_name']) ?></div>
          <div style="font-size:11px;color:var(--text3);"><i class="ti ti-map-pin" style="font-size:11px;vertical-align:-1px;"></i> <?= htmlspecialchars($m['last_seen_place']) ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>

  <!-- ── Right sidebar ───────────────────────────────────── -->
  <div>

    <div class="sidebar-card">
      <div class="sidebar-title">Owner</div>
      <div class="owner-av-wrap">
        <div class="owner-av"><?= strtoupper(substr($report['owner_name'], 0, 1)) ?></div>
        <div>
          <?php if ($is_owner): ?>
            <!-- Owner sees their own name -->
            <div style="font-size:13px;font-weight:600;color:var(--text);"><?= htmlspecialchars($report['owner_name']) ?></div>
            <div style="font-size:11px;color:var(--text3);">You — this is your report</div>
          <?php else: ?>
            <!-- Public sees first name only -->
            <div style="font-size:13px;font-weight:600;color:var(--text);"><?= htmlspecialchars(explode(' ', $report['owner_name'])[0]) ?></div>
            <div style="font-size:11px;color:var(--text3);">Pet owner</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($is_owner): ?>
      <!-- ── Owner sees their own contact info ─────────── -->
      <?php if (!empty($report['owner_phone'])): ?>
      <div style="background:var(--green-lt);border-radius:var(--radius);padding:10px 13px;font-size:13px;color:var(--green);margin-bottom:8px;display:flex;align-items:center;gap:8px;">
        <i class="ti ti-phone" style="font-size:15px;"></i>
        <?= htmlspecialchars($report['owner_phone']) ?>
        <span style="font-size:11px;color:var(--text3);font-weight:400;">(your number — visible only to you)</span>
      </div>
      <?php endif; ?>
      <a href="../dashboard/my_reports.php" class="btn sm" style="display:inline-flex;margin-bottom:8px;">
        <i class="ti ti-edit"></i> Manage this report
      </a>

      <?php else: ?>
<!-- ── Public: contact info is locked ────────────── -->

 
<div style="margin-top:12px;">
 
  <?php if (!$is_logged_in): ?>
  <!-- Not logged in -->
  <a href="../auth/login.php" class="action-btn ab-green">
    <i class="ti ti-login" style="font-size:17px;"></i>
    Sign in to report finding this pet
  </a>
  <p style="font-size:11px;color:var(--text3);margin-top:8px;text-align:center;line-height:1.5;">
    Sign in to connect with the owner through Pawrtal's verified system.
  </p>
 
  <?php elseif ($existing_match): ?>
  <!-- They have a found report and there's already a match -->
  <?php
    $match_icon  = match($existing_match['status']) {
        'matched'  => 'ti-clock',
        'reunited' => 'ti-heart-handshake',
        'dismissed'=> 'ti-x',
        'cancelled'=> 'ti-x',
        default    => 'ti-link',
    };
    $match_color = match($existing_match['status']) {
        'matched'  => 'var(--amber)',
        'reunited' => 'var(--green)',
        'dismissed','cancelled' => 'var(--text3)',
        default    => 'var(--green)',
    };
    $match_bg = match($existing_match['status']) {
        'matched'  => '#fffbeb',
        'reunited' => 'var(--green-lt)',
        default    => 'var(--surface2)',
    };
    $match_label = match($existing_match['status']) {
        'pending'   => 'Match pending — owner will review',
        'matched'   => 'Match confirmed — coordinating',
        'reunited'  => 'Reunited',
        'dismissed' => 'You dismissed this match',
        'cancelled' => 'Match was cancelled',
        default     => 'Match exists',
    };
  ?>
  <div style="background:<?= $match_bg ?>;border:.5px solid <?= $match_color ?>;border-radius:var(--radius);padding:12px 14px;display:flex;align-items:center;gap:10px;margin-bottom:10px;">
    <i class="ti <?= $match_icon ?>" style="font-size:1.2rem;color:<?= $match_color ?>;flex-shrink:0;"></i>
    <div>
      <div style="font-size:13px;font-weight:600;color:var(--text);"><?= $match_label ?></div>
      <div style="font-size:11px;color:var(--text3);margin-top:2px;">
        Match score: <?= $existing_match['score'] ?>%
      </div>
    </div>
  </div>
  <a href="../dashboard/matches.php" class="action-btn ab-outline" style="display:flex;">
    <i class="ti ti-link" style="font-size:17px;"></i>
    View in Match alerts
  </a>
 
  <?php elseif ($viewer_found_report): ?>
  <!-- They have a found report but no match yet with this lost report -->
  <div style="background:var(--surface2);border:.5px solid var(--border);border-radius:var(--radius);padding:12px 14px;display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;">
    <i class="ti ti-info-circle" style="font-size:1.1rem;color:var(--text3);flex-shrink:0;margin-top:1px;"></i>
    <div style="font-size:12px;color:var(--text2);line-height:1.6;">
      You already have an active found report for a
      <strong><?= htmlspecialchars(ucfirst($viewer_found_report['pet_type'])) ?></strong>
      found at <strong><?= htmlspecialchars($viewer_found_report['found_place']) ?></strong>.
      <br>
      Our system compares it automatically — if it matches this lost report, the owner will be alerted.
    </div>
  </div>
  <a href="../public/view_found.php?id=<?= $viewer_found_report['id'] ?>" class="action-btn ab-outline" style="display:flex;margin-bottom:6px;">
    <i class="ti ti-eye" style="font-size:16px;"></i>
    View your found report
  </a>
  <a href="../dashboard/report_found.php" class="action-btn" style="display:flex;background:var(--surface2);color:var(--text3);border:.5px solid var(--border);">
    <i class="ti ti-plus" style="font-size:16px;"></i>
    Post a different found report
  </a>
 
  <?php else: ?>
  <!-- Logged in, no found report at all -->
  <a href="../dashboard/report_found.php" class="action-btn ab-green">
    <i class="ti ti-circle-check" style="font-size:17px;"></i>
    I found this pet — post a report
  </a>
  <p style="font-size:11px;color:var(--text3);margin-top:8px;text-align:center;line-height:1.5;">
    Submitting a found report lets the owner review it. Contact details are only shared after they confirm the match.
  </p>
  <?php endif; ?>
 
</div>
<?php endif; ?>

    <!-- Quick facts -->
    <div class="sidebar-card">
      <div class="sidebar-title">Quick facts</div>
      <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
        <?php foreach ([
            ['Pet name', $report['pet_name']],
            ['Type',     ucfirst($report['pet_type'])],
            ['Breed',    !empty($report['breed']) ? $report['breed'] : null],
            ['Color',    $report['color'] ?? '—'],
            ['Gender',   $gender_label],
        ] as [$label, $val]):
            if ($val === null) continue;
        ?>
        <div style="display:flex;justify-content:space-between;padding-bottom:8px;border-bottom:.5px solid var(--border);">
          <span style="color:var(--text3);"><?= $label ?></span>
          <span style="font-weight:500;"><?= htmlspecialchars($val) ?></span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;">
          <span style="color:var(--text3);">Last seen</span>
          <span style="font-weight:500;"><?= date('M j, Y', strtotime($report['last_seen_date'])) ?></span>
        </div>
      </div>
    </div>

    <!-- How contact works — info box for non-owners -->
    <?php if (!$is_owner): ?>
    <div class="sidebar-card" style="background:var(--amber-lt);border-color:rgba(146,64,14,.2);">
      <div style="font-size:12px;font-weight:500;color:var(--amber);margin-bottom:6px;"><i class="ti ti-info-circle" style="font-size:14px;vertical-align:-1px;"></i> How contact works</div>
      <div style="font-size:12px;color:var(--text2);line-height:1.7;">
        To protect privacy, contact details are not shown publicly. If you found this pet:
        <ol style="padding-left:16px;margin-top:6px;display:flex;flex-direction:column;gap:4px;">
          <li>Post a found report describing the pet.</li>
          <li>Our system notifies the owner.</li>
          <li>The owner reviews and confirms the match.</li>
          <li>Both parties receive each other's contact details.</li>
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
