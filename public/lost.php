<?php
session_start();
include '../config/db.php';

// ── Filters ──────────────────────────────────────────────────────
$search    = trim($_GET['search']   ?? '');
$type      = trim($_GET['type']     ?? '');
$gender    = trim($_GET['gender']   ?? '');
$sort      = trim($_GET['sort']     ?? 'newest');

$where  = ["status='active'"];
$params = [];
$types  = '';

if ($search) {
    $where[]  = "(pet_name LIKE ? OR breed LIKE ? OR last_seen_place LIKE ? OR description LIKE ?)";
    $s = "%$search%";
    $params   = array_merge($params, [$s, $s, $s, $s]);
    $types   .= 'ssss';
}
if ($type) {
    $where[]  = "pet_type = ?";
    $params[] = $type;
    $types   .= 's';
}
if ($gender) {
    $where[]  = "gender = ?";
    $params[] = $gender;
    $types   .= 's';
}

$orderBy = match($sort) {
    'oldest'   => 'created_at ASC',
    'date_asc' => 'last_seen_date ASC',
    'date_desc'=> 'last_seen_date DESC',
    default    => 'created_at DESC',
};

$sql = "SELECT * FROM lost_reports WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$totalActive   = $conn->query("SELECT COUNT(*) AS c FROM lost_reports WHERE status='active'")->fetch_assoc()['c'];
$totalReunited = $conn->query("SELECT COUNT(*) AS c FROM lost_reports WHERE status='reunited'")->fetch_assoc()['c'];

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
<title>Lost Pets — Pawrtal</title>
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
.nav-links a{color:var(--text3);font-size:13px;font-weight:500;transition:color .12s;}
.nav-links a:hover,.nav-links a.active{color:var(--green);}
.nav-actions{display:flex;align-items:center;gap:8px;}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:var(--radius);border:.5px solid var(--border-md);background:var(--surface);color:var(--text);font-size:13px;font-family:var(--font);cursor:pointer;font-weight:500;white-space:nowrap;transition:all .12s;text-decoration:none;}
.btn:hover{background:var(--surface2);}
.btn.primary{background:var(--green);color:#fff;border-color:var(--green);}
.btn.primary:hover{background:var(--green-dk);}
.btn.sm{padding:5px 11px;font-size:12px;}

/* Page header */
.page-header{background:var(--surface);border-bottom:.5px solid var(--border);padding:2rem 2rem 1.5rem;}
.page-header-inner{max-width:1100px;margin:0 auto;}
.page-header-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;}
.page-title{font-family:var(--display);font-size:1.5rem;color:var(--text);}
.page-stats{display:flex;gap:16px;}
.page-stat{font-size:12px;color:var(--text3);}
.page-stat strong{color:var(--text);font-weight:600;}

/* Filter bar */
.filter-bar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
.filter-input{padding:8px 12px;border-radius:var(--radius);border:.5px solid var(--border-md);background:var(--surface);color:var(--text);font-size:13px;font-family:var(--font);outline:none;transition:border-color .12s;}
.filter-input:focus{border-color:var(--green);}
.filter-search{width:260px;}
.filter-select{min-width:120px;cursor:pointer;}
.filter-btn{padding:8px 14px;border-radius:var(--radius);border:.5px solid var(--border-md);background:var(--green);color:#fff;font-size:13px;font-family:var(--font);cursor:pointer;font-weight:500;display:flex;align-items:center;gap:5px;}
.filter-btn:hover{background:var(--green-dk);}
.filter-clear{font-size:12px;color:var(--text3);text-decoration:none;display:flex;align-items:center;gap:4px;}
.filter-clear:hover{color:var(--red);}

/* Results bar */
.results-bar{max-width:1100px;margin:1rem auto 0;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;}
.results-count{font-size:13px;color:var(--text3);}
.results-count strong{color:var(--text);}

/* Grid */
.grid-wrap{max-width:1100px;margin:0 auto;padding:1.25rem 2rem 3rem;}
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:12px;}
.report-card{background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .15s,transform .15s;}
.report-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.08);transform:translateY(-2px);}
.card-img{width:100%;height:160px;background:var(--surface2);overflow:hidden;display:flex;align-items:center;justify-content:center;position:relative;flex-shrink:0;}
.card-img img{width:100%;height:100%;object-fit:cover;}
.card-img i{font-size:2.8rem;color:#ccc;}
.card-pill{position:absolute;top:10px;left:10px;font-size:10px;font-weight:600;padding:3px 9px;border-radius:99px;text-transform:uppercase;letter-spacing:.06em;background:rgba(139,38,53,.9);color:#fff;}
.card-body{padding:12px 14px;flex:1;display:flex;flex-direction:column;}
.card-name{font-family:var(--display);font-size:1rem;color:var(--text);margin-bottom:4px;}
.card-meta{display:flex;flex-direction:column;gap:2px;font-size:11px;color:var(--text3);margin-bottom:8px;}
.card-meta i{font-size:12px;vertical-align:-1px;margin-right:3px;}
.card-desc{font-size:12px;color:var(--text2);line-height:1.6;flex:1;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}
.card-footer{padding:9px 14px;border-top:.5px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.card-time{font-size:11px;color:var(--text3);}
.card-cta{font-size:11px;color:var(--red);font-weight:500;display:flex;align-items:center;gap:4px;}

.empty{text-align:center;padding:4rem 1rem;color:var(--text3);font-size:13px;}
.empty i{font-size:2.5rem;display:block;margin-bottom:10px;color:#ddd;}

footer{text-align:center;padding:1.5rem;border-top:.5px solid var(--border);font-size:12px;color:var(--text3);background:var(--surface);}
footer a{color:var(--green);font-weight:500;}

@media(max-width:640px){.nav-links{display:none;}.filter-search{width:100%;}.filter-bar{flex-direction:column;align-items:stretch;}}
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
    <a href="lost.php" class="active">Lost pets</a>
    <a href="found.php">Found pets</a>
  </div>
  <div class="nav-actions">
    <?php if (isset($_SESSION['user_id'])): ?>
      <a href="../dashboard/index.php" class="btn sm"><i class="ti ti-layout-dashboard"></i> Dashboard</a>
      <a href="../auth/logout.php"     class="btn sm"><i class="ti ti-logout"></i> Logout</a>
    <?php else: ?>
      <a href="../auth/login.php"    class="btn sm">Log in</a>
      <a href="../auth/register.php" class="btn primary sm">Join free</a>
    <?php endif; ?>
  </div>
</nav>

<!-- Page header + filters -->
<div class="page-header">
  <div class="page-header-inner">
    <div class="page-header-top">
      <div>
        <div class="page-title">Lost pets</div>
      </div>
      <div class="page-stats">
        <div class="page-stat"><strong><?= $totalActive ?></strong> active reports</div>
        <div class="page-stat"><strong><?= $totalReunited ?></strong> reunited</div>
      </div>
    </div>
    <form method="GET" class="filter-bar">
      <input type="text" name="search" class="filter-input filter-search"
             placeholder="Search by name, breed, location…"
             value="<?= htmlspecialchars($search) ?>">
      <select name="type" class="filter-input filter-select">
        <option value="">All types</option>
        <?php foreach (['dog','cat','bird','rabbit','other'] as $t): ?>
        <option value="<?= $t ?>" <?= $type===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="gender" class="filter-input filter-select">
        <option value="">Any gender</option>
        <option value="male"    <?= $gender==='male'   ?'selected':'' ?>>Male</option>
        <option value="female"  <?= $gender==='female' ?'selected':'' ?>>Female</option>
        <option value="unknown" <?= $gender==='unknown'?'selected':'' ?>>Unknown</option>
      </select>
      <select name="sort" class="filter-input filter-select">
        <option value="newest"    <?= $sort==='newest'   ?'selected':'' ?>>Newest first</option>
        <option value="oldest"    <?= $sort==='oldest'   ?'selected':'' ?>>Oldest first</option>
        <option value="date_desc" <?= $sort==='date_desc'?'selected':'' ?>>Last seen (recent)</option>
        <option value="date_asc"  <?= $sort==='date_asc' ?'selected':'' ?>>Last seen (oldest)</option>
      </select>
      <button type="submit" class="filter-btn"><i class="ti ti-search"></i> Search</button>
      <?php if ($search || $type || $gender || $sort !== 'newest'): ?>
        <a href="lost.php" class="filter-clear"><i class="ti ti-x"></i> Clear</a>
      <?php endif; ?>
    </form>
  </div>
</div>

<div class="results-bar">
  <div class="results-count">
    Showing <strong><?= count($reports) ?></strong> lost pet<?= count($reports) !== 1 ? 's' : '' ?>
    <?= $search ? ' matching <strong>'.htmlspecialchars($search).'</strong>' : '' ?>
  </div>
  <?php if (isset($_SESSION['user_id'])): ?>
  <a href="../dashboard/report_lost.php" class="btn sm primary"><i class="ti ti-plus"></i> Report a lost pet</a>
  <?php endif; ?>
</div>

<div class="grid-wrap">
  <?php if (empty($reports)): ?>
  <div class="empty">
    <i class="ti ti-paw"></i>
    No lost pet reports found<?= $search ? ' for "'.htmlspecialchars($search).'"' : '' ?>.<br>
    <?php if ($search || $type || $gender): ?>
      <a href="lost.php" style="color:var(--green);font-weight:500;margin-top:8px;display:inline-block;">Clear filters</a>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <div class="cards-grid">
    <?php foreach ($reports as $r): ?>
    <a href="view_lost.php?id=<?= $r['id'] ?>" class="report-card">
      <div class="card-img">
        <?php if (!empty($r['photo'])): ?>
          <img src="../uploads/reports/<?= htmlspecialchars($r['photo']) ?>" alt="<?= htmlspecialchars($r['pet_name']) ?>">
        <?php else: ?>
          <i class="ti ti-paw"></i>
        <?php endif; ?>
        <span class="card-pill">Lost</span>
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

<footer>
  &copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery &middot;
  <a href="../auth/login.php">Log in</a> &middot; <a href="../auth/register.php">Register</a>
</footer>
</body>
</html>