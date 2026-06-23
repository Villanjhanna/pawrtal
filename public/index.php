<?php
session_start();
include '../config/db.php';

$recentLost = $conn->query("
    SELECT * FROM lost_reports WHERE status='active' ORDER BY created_at DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$recentFound = $conn->query("
    SELECT * FROM found_reports WHERE status='active' ORDER BY created_at DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

$totalLost     = $conn->query("SELECT COUNT(*) AS c FROM lost_reports  WHERE status='active'")->fetch_assoc()['c'];
$totalFound    = $conn->query("SELECT COUNT(*) AS c FROM found_reports WHERE status='active'")->fetch_assoc()['c'];
$totalReunited = $conn->query("SELECT COUNT(*) AS c FROM lost_reports  WHERE status='reunited'")->fetch_assoc()['c'];

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
<title>Pawrtal — Lost & Found Pet Recovery</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f7f5f2;--surface:#fff;--surface2:#f2efeb;
  --border:rgba(0,0,0,.08);--border-md:rgba(0,0,0,.14);
  --green:#2d6a4f;--green-dk:#1b4332;--green-lt:#f0fdf4;
  --red:#8b2635;--red-lt:#fef2f2;
  --text:#1a1208;--text2:#44352a;--text3:#8a7060;
  --radius:8px;--radius-lg:12px;--radius-xl:18px;
  --font:'DM Sans',system-ui,sans-serif;
  --display:'DM Serif Display',Georgia,serif;
}
body{background:var(--bg);color:var(--text);font-family:var(--font);font-size:14px;line-height:1.6;}
a{text-decoration:none;color:inherit;}

/* ── Nav ─────────────────────────────────────────── */
nav{
  background:var(--surface);
  border-bottom:.5px solid var(--border);
  padding:0 2rem;
  height:58px;
  display:flex;align-items:center;justify-content:space-between;
  position:sticky;top:0;z-index:100;
  box-shadow:0 1px 6px rgba(0,0,0,.04);
}
.nav-logo{display:flex;align-items:center;gap:10px;}
.nav-logo-icon{width:32px;height:32px;border-radius:9px;background:var(--green);display:flex;align-items:center;justify-content:center;}
.nav-logo-icon i{color:#fff;font-size:17px;}
.nav-logo-name{font-family:var(--display);font-size:1.05rem;color:var(--text);}
.nav-logo-sub{font-size:10px;color:var(--text3);margin-top:-2px;}
.nav-links{display:flex;align-items:center;gap:1.5rem;}
.nav-links a{color:var(--text3);font-size:13px;font-weight:500;transition:color .12s;}
.nav-links a:hover{color:var(--green);}
.nav-actions{display:flex;align-items:center;gap:8px;}

/* ── Buttons ─────────────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius);border:.5px solid var(--border-md);background:var(--surface);color:var(--text);font-size:13px;font-family:var(--font);cursor:pointer;font-weight:500;white-space:nowrap;transition:all .12s;}
.btn:hover{background:var(--surface2);}
.btn.primary{background:var(--green);color:#fff;border-color:var(--green);}
.btn.primary:hover{background:var(--green-dk);}
.btn.outline-red{background:transparent;color:var(--red);border-color:var(--red);}
.btn.outline-red:hover{background:var(--red-lt);}
.btn.sm{padding:5px 11px;font-size:12px;}
.btn.lg{padding:11px 22px;font-size:14px;border-radius:var(--radius-lg);}

/* ── Hero ────────────────────────────────────────── */
.hero{
  padding:5rem 2rem 4rem;
  max-width:900px;
  margin:0 auto;
  text-align:center;
}
.hero-eyebrow{
  display:inline-flex;align-items:center;gap:7px;
  padding:5px 14px;border-radius:99px;
  border:.5px solid rgba(45,106,79,.25);
  background:var(--green-lt);
  color:var(--green);font-size:12px;font-weight:500;
  margin-bottom:1.5rem;
}
.hero h1{
  font-family:var(--display);
  font-size:clamp(2.2rem,6vw,3.6rem);
  line-height:1.1;
  color:var(--text);
  margin-bottom:1rem;
}
.hero h1 em{color:var(--green);font-style:normal;}
.hero-sub{font-size:1.05rem;color:var(--text3);max-width:460px;margin:0 auto 2.5rem;line-height:1.8;}
.hero-actions{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-bottom:3.5rem;}

/* Stats row */
.hero-stats{
  display:inline-flex;gap:0;
  background:var(--surface);
  border:.5px solid var(--border);
  border-radius:var(--radius-lg);
  overflow:hidden;
}
.hero-stat{
  padding:14px 28px;
  text-align:center;
  border-right:.5px solid var(--border);
}
.hero-stat:last-child{border-right:none;}
.hero-stat-num{
  font-family:var(--display);
  font-size:1.8rem;
  color:var(--green);
  display:block;line-height:1;
}
.hero-stat-label{font-size:11px;color:var(--text3);margin-top:4px;text-transform:uppercase;letter-spacing:.06em;}

/* ── How it works ────────────────────────────────── */
.how{
  background:var(--surface);
  border-top:.5px solid var(--border);
  border-bottom:.5px solid var(--border);
  padding:3.5rem 2rem;
}
.how-inner{max-width:1000px;margin:0 auto;}
.how-header{text-align:center;margin-bottom:2.5rem;}
.how-title{font-family:var(--display);font-size:1.5rem;color:var(--text);margin-bottom:6px;}
.how-sub{font-size:13px;color:var(--text3);}
.how-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
.how-step{
  background:var(--bg);
  border:.5px solid var(--border);
  border-radius:var(--radius-lg);
  padding:20px 18px;
  position:relative;
}
.how-step-num{
  width:28px;height:28px;border-radius:50%;
  background:var(--green);color:#fff;
  font-size:12px;font-weight:600;
  display:flex;align-items:center;justify-content:center;
  margin-bottom:12px;
}
.how-step-icon{
  width:40px;height:40px;border-radius:10px;
  background:var(--green-lt);
  display:flex;align-items:center;justify-content:center;
  margin-bottom:12px;
}
.how-step-icon i{font-size:20px;color:var(--green);}
.how-step-title{font-weight:600;font-size:13px;color:var(--text);margin-bottom:5px;}
.how-step-desc{font-size:12px;color:var(--text3);line-height:1.7;}

/* ── Sections ────────────────────────────────────── */
.section{padding:3rem 2rem;max-width:1100px;margin:0 auto;}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;}
.section-title{font-family:var(--display);font-size:1.15rem;color:var(--text);}
.section-label{
  display:inline-flex;align-items:center;gap:5px;
  font-size:11px;font-weight:600;
  padding:3px 10px;border-radius:99px;
  text-transform:uppercase;letter-spacing:.06em;
  margin-left:10px;vertical-align:middle;
}
.label-lost{background:var(--red-lt);color:var(--red);}
.label-found{background:var(--green-lt);color:var(--green);}

/* ── Report cards ────────────────────────────────── */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:12px;}
.report-card{
  background:var(--surface);
  border:.5px solid var(--border);
  border-radius:var(--radius-lg);
  overflow:hidden;
  display:flex;flex-direction:column;
  transition:box-shadow .15s,transform .15s;
}
.report-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.08);transform:translateY(-2px);}
.card-img{
  width:100%;height:155px;
  background:var(--surface2);
  overflow:hidden;
  display:flex;align-items:center;justify-content:center;
  position:relative;
}
.card-img img{width:100%;height:100%;object-fit:cover;}
.card-img-placeholder{font-size:2.8rem;color:var(--border-md);}
.card-img-placeholder i{font-size:2.8rem;color:#ccc;}
.card-type-pill{
  position:absolute;top:10px;left:10px;
  font-size:10px;font-weight:600;
  padding:3px 9px;border-radius:99px;
  text-transform:uppercase;letter-spacing:.06em;
  backdrop-filter:blur(4px);
}
.pill-lost{background:rgba(139,38,53,.9);color:#fff;}
.pill-found{background:rgba(45,106,79,.9);color:#fff;}
.card-body{padding:12px 14px;flex:1;display:flex;flex-direction:column;}
.card-name{font-family:var(--display);font-size:1rem;color:var(--text);margin-bottom:4px;}
.card-meta{display:flex;flex-direction:column;gap:2px;font-size:11px;color:var(--text3);margin-bottom:8px;}
.card-meta i{font-size:12px;vertical-align:-1px;margin-right:3px;}
.card-desc{font-size:12px;color:var(--text2);line-height:1.6;flex:1;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.card-footer{
  padding:9px 14px;
  border-top:.5px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
}
.card-time{font-size:11px;color:var(--text3);}
.card-cta{font-size:11px;color:var(--green);font-weight:500;display:flex;align-items:center;gap:4px;}

/* ── Empty state ─────────────────────────────────── */
.empty{text-align:center;padding:3rem 1rem;color:var(--text3);font-size:13px;}
.empty i{font-size:2.5rem;display:block;margin-bottom:8px;color:#ddd;}

/* ── CTA banner ──────────────────────────────────── */
.cta-wrap{padding:0 2rem 3rem;max-width:1100px;margin:0 auto;}
.cta-banner{
  background:var(--green);
  border-radius:var(--radius-xl);
  padding:2.5rem 2rem;
  text-align:center;
  position:relative;
  overflow:hidden;
}
.cta-banner::before{
  content:'';
  position:absolute;inset:0;
  background:radial-gradient(circle at 80% 50%, rgba(255,255,255,.06) 0%, transparent 60%);
}
.cta-banner h2{font-family:var(--display);font-size:1.6rem;color:#fff;margin-bottom:6px;}
.cta-banner p{font-size:13px;color:rgba(255,255,255,.75);margin-bottom:1.5rem;}
.cta-banner .btn{background:#fff;color:var(--green);border-color:#fff;}
.cta-banner .btn:hover{background:rgba(255,255,255,.9);}
.cta-banner .btn.outline{background:transparent;color:#fff;border-color:rgba(255,255,255,.4);}
.cta-banner .btn.outline:hover{background:rgba(255,255,255,.1);}

/* ── Footer ──────────────────────────────────────── */
footer{
  text-align:center;padding:1.5rem;
  border-top:.5px solid var(--border);
  font-size:12px;color:var(--text3);
  background:var(--surface);
}
footer a{color:var(--green);font-weight:500;}

@media(max-width:700px){
  .how-steps{grid-template-columns:1fr 1fr;}
  .nav-links{display:none;}
  .hero-stats{flex-direction:column;width:100%;}
  .hero-stat{border-right:none;border-bottom:.5px solid var(--border);}
  .hero-stat:last-child{border-bottom:none;}
}
</style>
</head>
<body>

<!-- ── Nav ────────────────────────────────────────────────────── -->
<nav>
  <div class="nav-logo">
    <div class="nav-logo-icon"><i class="ti ti-paw"></i></div>
    <div>
      <div class="nav-logo-name">Pawrtal</div>
      <div class="nav-logo-sub">Lost &amp; Found Pet Recovery</div>
    </div>
  </div>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <a href="lost.php">Lost pets</a>
    <a href="found.php">Found pets</a>
  </div>
  <div class="nav-actions">
  <a href="../qr/scan.php" class="btn sm">
    <i class="ti ti-qrcode"></i> Scan QR
  </a>

  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="../dashboard/index.php" class="btn sm">
      <i class="ti ti-layout-dashboard"></i> Dashboard
    </a>
    <a href="../auth/logout.php" class="btn sm">
      <i class="ti ti-logout"></i> Logout
    </a>
  <?php else: ?>
    <a href="../auth/login.php" class="btn sm">Log in</a>
    <a href="../auth/register.php" class="btn primary sm">Join free</a>
  <?php endif; ?>
</div>
</nav>

<!-- ── Hero ───────────────────────────────────────────────────── -->
<div class="hero">
  <div class="hero-eyebrow">
    <i class="ti ti-map-pin" style="font-size:13px;"></i>
    Community pet recovery &middot; Bacolod City
  </div>
  <h1>Reuniting <em>lost pets</em><br>with their families</h1>
  <p class="hero-sub">Post a missing pet, report one you found, and let the community help bring them home.</p>
  <div class="hero-actions">
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="../dashboard/report_lost.php"  class="btn outline-red lg"><i class="ti ti-alert-circle"></i> Report a lost pet</a>
      <a href="../dashboard/report_found.php" class="btn primary lg"><i class="ti ti-circle-check"></i> Report a found pet</a>
    <?php else: ?>
      <a href="../auth/register.php" class="btn primary lg"><i class="ti ti-user-plus"></i> Get started free</a>
      <a href="lost.php"             class="btn lg"><i class="ti ti-search"></i> Browse lost pets</a>
    <?php endif; ?>
  </div>
  <div class="hero-stats">
    <div class="hero-stat">
      <span class="hero-stat-num"><?= $totalLost ?></span>
      <div class="hero-stat-label">Active lost</div>
    </div>
    <div class="hero-stat">
      <span class="hero-stat-num"><?= $totalFound ?></span>
      <div class="hero-stat-label">Active found</div>
    </div>
    <div class="hero-stat">
      <span class="hero-stat-num"><?= $totalReunited ?></span>
      <div class="hero-stat-label">Pets reunited</div>
    </div>
  </div>
</div>

<!-- ── How it works ───────────────────────────────────────────── -->
<div class="how">
  <div class="how-inner">
    <div class="how-header">
      <div class="how-title">How Pawrtal works</div>
      <div class="how-sub">Four simple steps to help bring a pet home</div>
    </div>
    <div class="how-steps">
      <div class="how-step">
        <div class="how-step-num">1</div>
        <div class="how-step-icon"><i class="ti ti-paw"></i></div>
        <div class="how-step-title">Register your pet</div>
        <div class="how-step-desc">Add your pet's photo and details. Get a printable QR tag for their collar.</div>
      </div>
      <div class="how-step">
        <div class="how-step-num">2</div>
        <div class="how-step-icon"><i class="ti ti-alert-circle" style="color:var(--red);"></i></div>
        <div class="how-step-title">Report if lost</div>
        <div class="how-step-desc">Post a lost report with last seen location. The community gets notified right away.</div>
      </div>
      <div class="how-step">
        <div class="how-step-num">3</div>
        <div class="how-step-icon"><i class="ti ti-circle-check"></i></div>
        <div class="how-step-title">Report if found</div>
        <div class="how-step-desc">Found a stray? Our system automatically matches your report to nearby lost pets.</div>
      </div>
      <div class="how-step">
        <div class="how-step-num">4</div>
        <div class="how-step-icon"><i class="ti ti-heart-handshake"></i></div>
        <div class="how-step-title">Get reunited</div>
        <div class="how-step-desc">Connect with the owner directly, arrange a handoff, and close the case.</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Recent lost reports ────────────────────────────────────── -->
<div class="section">
  <div class="section-header">
    <div>
      <span class="section-title">Recently lost</span>
      <span class="section-label label-lost"><i class="ti ti-alert-circle" style="font-size:11px;"></i> Lost</span>
    </div>
    <a href="lost.php" class="btn sm">View all</a>
  </div>

  <?php if (empty($recentLost)): ?>
  <div class="empty"><i class="ti ti-paw"></i>No active lost reports yet.</div>
  <?php else: ?>
  <div class="cards-grid">
    <?php foreach ($recentLost as $r): ?>
    <a href="view_lost.php?id=<?= $r['id'] ?>" class="report-card">
      <div class="card-img">
        <?php if (!empty($r['photo'])): ?>
          <img src="../uploads/reports/<?= htmlspecialchars($r['photo']) ?>" alt="<?= htmlspecialchars($r['pet_name']) ?>">
        <?php else: ?>
          <div class="card-img-placeholder"><i class="ti ti-paw"></i></div>
        <?php endif; ?>
        <span class="card-type-pill pill-lost">Lost</span>
      </div>
      <div class="card-body">
        <div class="card-name"><?= htmlspecialchars($r['pet_name']) ?></div>
        <div class="card-meta">
          <span><i class="ti ti-paw"></i><?= htmlspecialchars($r['pet_type']) ?><?= $r['breed'] ? ' &middot; '.htmlspecialchars($r['breed']) : '' ?></span>
          <span><i class="ti ti-map-pin"></i><?= htmlspecialchars($r['last_seen_place']) ?></span>
          <span><i class="ti ti-calendar"></i>Last seen <?= date('M j, Y', strtotime($r['last_seen_date'])) ?></span>
        </div>
        <?php if (!empty($r['description'])): ?>
        <div class="card-desc"><?= htmlspecialchars($r['description']) ?></div>
        <?php endif; ?>
      </div>
      <div class="card-footer">
        <span class="card-time"><?= timeAgo($r['created_at']) ?></span>
        <span class="card-cta"><i class="ti ti-phone" style="font-size:12px;"></i> Contact owner</span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Recent found reports ───────────────────────────────────── -->
<div class="section" style="padding-top:0;">
  <div class="section-header">
    <div>
      <span class="section-title">Recently found</span>
      <span class="section-label label-found"><i class="ti ti-circle-check" style="font-size:11px;"></i> Found</span>
    </div>
    <a href="found.php" class="btn sm">View all</a>
  </div>

  <?php if (empty($recentFound)): ?>
  <div class="empty"><i class="ti ti-paw"></i>No active found reports yet.</div>
  <?php else: ?>
  <div class="cards-grid">
    <?php foreach ($recentFound as $r): ?>
    <a href="view_found.php?id=<?= $r['id'] ?>" class="report-card">
      <div class="card-img">
        <?php if (!empty($r['photo'])): ?>
          <img src="../uploads/reports/<?= htmlspecialchars($r['photo']) ?>" alt="Found pet">
        <?php else: ?>
          <div class="card-img-placeholder"><i class="ti ti-paw"></i></div>
        <?php endif; ?>
        <span class="card-type-pill pill-found">Found</span>
      </div>
      <div class="card-body">
        <div class="card-name">
          <?= ucfirst(htmlspecialchars($r['pet_type'])) ?>
          <?= !empty($r['breed']) ? ' &middot; '.htmlspecialchars($r['breed']) : '' ?>
        </div>
        <div class="card-meta">
          <span><i class="ti ti-color-swatch"></i><?= htmlspecialchars($r['color']) ?></span>
          <span><i class="ti ti-map-pin"></i><?= htmlspecialchars($r['found_place']) ?></span>
          <span><i class="ti ti-calendar"></i>Found <?= date('M j, Y', strtotime($r['found_date'])) ?></span>
        </div>
        <?php if (!empty($r['description'])): ?>
        <div class="card-desc"><?= htmlspecialchars($r['description']) ?></div>
        <?php endif; ?>
      </div>
      <div class="card-footer">
        <span class="card-time"><?= timeAgo($r['created_at']) ?></span>
        <span class="card-cta"><i class="ti ti-phone" style="font-size:12px;"></i> Contact finder</span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── CTA banner ─────────────────────────────────────────────── -->
<?php if (!isset($_SESSION['user_id'])): ?>
<div class="cta-wrap">
  <div class="cta-banner">
    <h2>Help bring a pet home today</h2>
    <p>Join Pawrtal for free — post reports, get match alerts, and help your community.</p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
      <a href="../auth/register.php" class="btn lg"><i class="ti ti-user-plus"></i> Create free account</a>
      <a href="lost.php"             class="btn outline lg"><i class="ti ti-search"></i> Browse reports</a>
    </div>
  </div>
</div>
<?php endif; ?>

<footer>
  &copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery &middot;
  <a href="../auth/login.php">Log in</a> &middot; <a href="../auth/register.php">Register</a>
</footer>

</body>
</html>