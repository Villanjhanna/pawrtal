<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$tab = $_GET['tab'] ?? 'lost';

$lostReports     = $conn->query("SELECT * FROM lost_reports  WHERE user_id=$user_id AND status='active'  ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$foundReports    = $conn->query("SELECT * FROM found_reports WHERE user_id=$user_id AND status='active'  ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$reunitedReports = $conn->query("SELECT * FROM lost_reports  WHERE user_id=$user_id AND status='reunited' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$myMatches       = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];

$unreadCount = $conn->query("
    SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0
")->fetch_assoc()['c'];

$name     = $_SESSION['name'];
$initials = pf_initials($name);

function statusPill(string $status): string {
    return match($status) {
        'active'   => '<span style="font-size:10px;font-weight:500;padding:2px 8px;border-radius:99px;background:#fef2f2;color:#991b1b;">Active</span>',
        'reunited' => '<span style="font-size:10px;font-weight:500;padding:2px 8px;border-radius:99px;background:#eff6ff;color:#1e40af;">Reunited</span>',
        'closed'   => '<span style="font-size:10px;font-weight:500;padding:2px 8px;border-radius:99px;background:#f3f4f6;color:#4b5563;">Closed</span>',
        default    => ''
    };
}

pf_head('My Reports');
?>
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
      <div class="topbar-title">My reports</div>
      <div class="topbar-sub">All your lost and found reports in one place</div>
    </div>
    <div class="topbar-right">
      <a href="report_lost.php"  class="btn primary"><i class="ti ti-alert-circle"></i> Report lost</a>
      <a href="report_found.php" class="btn success"><i class="ti ti-circle-check"></i> Report found</a>
    </div>
  </div>

  <div class="content">

    <!-- Tabs -->
    <div style="display:flex;gap:4px;background:var(--surface2);border-radius:var(--radius);padding:4px;width:fit-content;border:.5px solid var(--border);">
      <?php
        $tabs = [
          'lost'     => ['Lost pets',    count($lostReports),     '#8b2635'],
          'found'    => ['Found pets',   count($foundReports),    '#2d6a4f'],
          'reunited' => ['Reunited',     count($reunitedReports), '#1e40af'],
        ];
        foreach ($tabs as $key => [$label, $count, $color]):
          $active = $tab === $key;
      ?>
      <a href="?tab=<?= $key ?>" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:6px;font-size:13px;text-decoration:none;font-weight:<?= $active?'500':'400' ?>;background:<?= $active?'var(--surface)':'transparent' ?>;color:<?= $active?'var(--text)':'var(--text3)' ?>;border:<?= $active?'.5px solid var(--border)':'none' ?>;">
        <?= $label ?>
        <span style="font-size:11px;font-weight:600;padding:1px 6px;border-radius:99px;background:<?= $active?$color:'rgba(0,0,0,.1)' ?>;color:<?= $active?'#fff':'var(--text3)' ?>;"><?= $count ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <?php
    $rows = match($tab) {
        'found'    => $foundReports,
        'reunited' => $reunitedReports,
        default    => $lostReports,
    };
    $isLostTab  = in_array($tab, ['lost', 'reunited']);
    ?>

    <?php if (empty($rows)): ?>
    <div class="card">
      <div class="empty">
        <i class="ti ti-clipboard-list"></i>
        No <?= $tab ?> reports yet.
        
      </div>
    </div>

    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:1px;background:var(--border);border-radius:var(--radius-lg);overflow:hidden;">
      <!-- Table header -->
      <div style="display:grid;grid-template-columns:60px 1fr 1fr 1fr 100px 120px;gap:12px;padding:9px 16px;background:var(--surface2);font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase;letter-spacing:.07em;">
        <div>Photo</div>
        <div><?= $isLostTab ? 'Pet name' : 'Pet type' ?></div>
        <div>Location</div>
        <div>Date</div>
        <div>Status</div>
        <div style="text-align:right;">Actions</div>
      </div>
      <?php foreach ($rows as $r):
        $petName = $isLostTab ? $r['pet_name'] : ucfirst($r['pet_type']);
        $place   = $isLostTab ? ($r['last_seen_place'] ?? '') : ($r['found_place'] ?? '');
        $date    = $isLostTab ? ($r['last_seen_date'] ?? '') : ($r['found_date'] ?? '');
        $photo   = $r['photo'] ?? '';
        $editUrl = $isLostTab ? "edit_lost.php?id={$r['id']}" : "edit_found.php?id={$r['id']}";
      ?>
      <div style="display:grid;grid-template-columns:60px 1fr 1fr 1fr 100px 120px;gap:12px;padding:12px 16px;background:var(--surface);align-items:center;">
        <div>
          <?php if ($photo): ?>
            <img src="../uploads/reports/<?= htmlspecialchars($photo) ?>" alt="" style="width:48px;height:48px;border-radius:var(--radius);object-fit:cover;background:var(--surface2);">
          <?php else: ?>
            <div style="width:48px;height:48px;border-radius:var(--radius);background:var(--surface2);display:flex;align-items:center;justify-content:center;">
              <i class="ti ti-paw" style="color:var(--text3);font-size:1.2rem;"></i>
            </div>
          <?php endif; ?>
        </div>
        <div>
          <div style="font-size:13px;font-weight:500;color:var(--text);"><?= htmlspecialchars($petName) ?></div>
          <?php if (!empty($r['breed'])): ?><div style="font-size:11px;color:var(--text3);"><?= htmlspecialchars($r['breed']) ?></div><?php endif; ?>
        </div>
        <div style="font-size:12px;color:var(--text2);"><?= htmlspecialchars($place) ?></div>
        <div style="font-size:12px;color:var(--text2);"><?= $date ? date('M j, Y', strtotime($date)) : '—' ?></div>
        <div><?= statusPill($r['status']) ?></div>
        <div style="display:flex;gap:6px;justify-content:flex-end;">
  <a href="<?= $editUrl ?>" class="btn sm" title="Edit">
    <i class="ti ti-edit"></i>
  </a>

  <a href="view_report.php?id=<?= $r['id'] ?>&type=<?= $isLostTab?'lost':'found' ?>"
     class="btn sm"
     title="View">
    <i class="ti ti-eye"></i>
  </a>

  <a href="delete_report.php?id=<?= $r['id'] ?>&type=<?= $isLostTab ? 'lost' : 'found' ?>"
     class="btn sm danger"
     title="Delete"
     onclick="return confirm('Are you sure you want to delete this report?');">
    <i class="ti ti-trash"></i>
  </a>
</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>

  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

</body>
</html>
