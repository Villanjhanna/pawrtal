<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$myLost     = $conn->query("SELECT COUNT(*) AS c FROM lost_reports  WHERE user_id=$user_id AND status='active'")->fetch_assoc()['c'];
$myMatches  = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];
$myReunited = $conn->query("SELECT COUNT(*) AS c FROM lost_reports  WHERE user_id=$user_id AND status='reunited'")->fetch_assoc()['c'];
$unreadCount= $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];

// Confirmed matches waiting for action (need to contact finder / mark reunited)
$confirmed = $conn->query("
    SELECT m.id, l.pet_name, f.found_place, f.contact_name
    FROM matches m
    JOIN lost_reports  l ON m.lost_report_id  = l.id
    JOIN found_reports f ON m.found_report_id = f.id
    WHERE l.user_id=$user_id AND m.status='confirmed'
    ORDER BY m.updated_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Top pending match alerts
$matchAlerts = $conn->query("
    SELECT m.id, m.score, l.pet_name, l.photo AS l_photo,
           f.pet_type AS f_type, f.found_place, f.found_date,
           f.photo AS f_photo, f.id AS f_id
    FROM matches m
    JOIN lost_reports  l ON m.lost_report_id  = l.id
    JOIN found_reports f ON m.found_report_id = f.id
    WHERE l.user_id=$user_id AND m.status='pending'
    ORDER BY m.score DESC LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

$name      = $_SESSION['name'];
$initials  = pf_initials($name);
$firstName = explode(' ', $name)[0];

pf_head('Dashboard');
?>
<body>

<aside class="sb">
  <div class="sb-brand"><div class="sb-logo"><i class="ti ti-paw"></i></div><div><div class="sb-appname">Pawrtal</div><div class="sb-appsub">Dashboard</div></div></div>
  <nav class="sb-nav">
    <div class="sb-sec">Overview</div>
    <a href="index.php"      class="sb-item active"><i class="ti ti-home"></i> Dashboard</a>
    <div class="sb-sec">My activity</div>
    <a href="my_reports.php" class="sb-item"><i class="ti ti-clipboard-list"></i> My reports</a>
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
      <div class="topbar-title">Welcome back, <?= htmlspecialchars($firstName) ?></div>
      <div class="topbar-sub"><?= date('l, F j, Y') ?></div>
    </div>
    <div class="topbar-right">
      <a href="report_lost.php"  class="btn primary"><i class="ti ti-alert-circle"></i> Report lost</a>
      <a href="report_found.php" class="btn success"><i class="ti ti-circle-check"></i> Report found</a>
    </div>
  </div>

  <div class="content">

    <!-- ── 3 metric cards ── -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">

      <a href="my_reports.php?tab=lost" style="background:var(--surface);border:.5px solid var(--border);border-top:2px solid var(--red);border-radius:var(--radius-lg);padding:16px 18px;text-decoration:none;display:flex;align-items:center;gap:14px;">
        <i class="ti ti-alert-circle" style="font-size:1.6rem;color:var(--red);flex-shrink:0;"></i>
        <div>
          <div style="font-family:var(--display);font-size:1.8rem;color:var(--text);line-height:1;"><?= $myLost ?></div>
          <div style="font-size:11px;color:var(--text3);margin-top:3px;text-transform:uppercase;letter-spacing:.06em;">Active lost reports</div>
        </div>
      </a>

      <a href="matches.php" style="background:var(--surface);border:.5px solid var(--border);border-top:2px solid var(--amber);border-radius:var(--radius-lg);padding:16px 18px;text-decoration:none;display:flex;align-items:center;gap:14px;">
        <i class="ti ti-link" style="font-size:1.6rem;color:var(--amber);flex-shrink:0;"></i>
        <div>
          <div style="font-family:var(--display);font-size:1.8rem;color:var(--text);line-height:1;"><?= $myMatches ?></div>
          <div style="font-size:11px;color:var(--text3);margin-top:3px;text-transform:uppercase;letter-spacing:.06em;">Pending match alerts</div>
        </div>
      </a>

      <a href="my_reports.php?status=reunited" style="background:var(--surface);border:.5px solid var(--border);border-top:2px solid var(--green);border-radius:var(--radius-lg);padding:16px 18px;text-decoration:none;display:flex;align-items:center;gap:14px;">
        <i class="ti ti-heart-handshake" style="font-size:1.6rem;color:var(--green);flex-shrink:0;"></i>
        <div>
          <div style="font-family:var(--display);font-size:1.8rem;color:var(--text);line-height:1;"><?= $myReunited ?></div>
          <div style="font-size:11px;color:var(--text3);margin-top:3px;text-transform:uppercase;letter-spacing:.06em;">Pets reunited</div>
        </div>
      </a>

    </div>

    <!-- ── Action needed: confirmed matches ── -->
    <?php if (!empty($confirmed)): ?>
    <div style="background:#fffbeb;border:.5px solid rgba(146,64,14,.25);border-left:3px solid #92400e;border-radius:var(--radius-lg);padding:14px 18px;margin-bottom:16px;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:10px;">
          <i class="ti ti-clock" style="font-size:1.2rem;color:#92400e;flex-shrink:0;"></i>
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--text);">
              <?= count($confirmed) === 1 ? '1 match confirmed' : count($confirmed).' matches confirmed' ?> — waiting for you
            </div>
            <div style="font-size:12px;color:#92400e;margin-top:1px;">
              Contact the finder<?= count($confirmed) > 1 ? 's' : '' ?> and arrange the handoff, then mark as Reunited.
            </div>
          </div>
        </div>
        <a href="matches.php" class="btn sm" style="white-space:nowrap;flex-shrink:0;">
          <i class="ti ti-arrow-right"></i> Go to matches
        </a>
      </div>
      <?php if (count($confirmed) <= 3): ?>
      <div style="margin-top:12px;display:flex;flex-direction:column;gap:6px;">
        <?php foreach ($confirmed as $c): ?>
        <a href="match_contact.php?id=<?= $c['id'] ?>" style="display:flex;align-items:center;justify-content:space-between;gap:8px;background:#fff;border:.5px solid rgba(146,64,14,.15);border-radius:var(--radius);padding:9px 13px;text-decoration:none;">
          <div style="font-size:13px;color:var(--text);">
            <strong><?= htmlspecialchars($c['pet_name']) ?></strong>
            <span style="color:var(--text3);font-size:12px;"> &middot; found at <?= htmlspecialchars($c['found_place']) ?></span>
          </div>
          <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
            <span style="font-size:12px;color:#92400e;font-weight:500;"><?= htmlspecialchars($c['contact_name']) ?></span>
            <i class="ti ti-chevron-right" style="font-size:13px;color:var(--text3);"></i>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Match alerts preview ── -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Match alerts</div>
          <div class="card-sub">Found pets that may match your lost reports</div>
        </div>
        <a href="matches.php" class="btn sm">View all</a>
      </div>

      <?php if (empty($matchAlerts)): ?>
      <div class="empty">
        <i class="ti ti-search"></i>
        No pending matches. We'll alert you when a found pet closely matches yours.
      </div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:8px;">
        <?php foreach ($matchAlerts as $m): ?>
        <a href="matches.php" style="text-decoration:none;display:flex;align-items:center;gap:12px;background:var(--surface2);border:.5px solid var(--border);border-radius:var(--radius);padding:10px 13px;transition:background .1s;" onmouseover="this.style.background='#ece8e2'" onmouseout="this.style.background='var(--surface2)'">

          <!-- Score bubble -->
          <div style="width:42px;height:42px;border-radius:50%;background:var(--green);color:#fff;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= $m['score'] ?>%</div>

          <!-- Lost pet thumbnail -->
          <?php if (!empty($m['l_photo'])): ?>
          <img src="../uploads/reports/<?= htmlspecialchars($m['l_photo']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0;border:.5px solid var(--border);" alt="">
          <?php else: ?>
          <div style="width:40px;height:40px;border-radius:6px;background:var(--red-lt);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="ti ti-paw" style="color:var(--red);font-size:1rem;"></i></div>
          <?php endif; ?>

          <div style="flex:1;min-width:0;">
            <div style="font-size:13px;font-weight:500;color:var(--text);">
              Lost <strong><?= htmlspecialchars($m['pet_name']) ?></strong>
              <span style="color:var(--text3);font-weight:400;"> &rarr; found <?= htmlspecialchars($m['f_type']) ?></span>
            </div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px;">
              <i class="ti ti-map-pin" style="font-size:11px;vertical-align:-1px;"></i>
              <?= htmlspecialchars($m['found_place']) ?> &middot; <?= date('M j', strtotime($m['found_date'])) ?>
            </div>
          </div>

          <!-- Found pet thumbnail -->
          <?php if (!empty($m['f_photo'])): ?>
          <img src="../uploads/reports/<?= htmlspecialchars($m['f_photo']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;flex-shrink:0;border:.5px solid var(--border);" alt="">
          <?php else: ?>
          <div style="width:40px;height:40px;border-radius:6px;background:var(--green-lt);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="ti ti-paw" style="color:var(--green);font-size:1rem;"></i></div>
          <?php endif; ?>

          <i class="ti ti-chevron-right" style="font-size:15px;color:var(--text3);flex-shrink:0;"></i>
        </a>
        <?php endforeach; ?>

        <?php if ($myMatches > 3): ?>
        <a href="matches.php" style="text-align:center;font-size:12px;color:var(--green);padding:8px;text-decoration:none;font-weight:500;">
          +<?= $myMatches - 3 ?> more match<?= ($myMatches - 3) > 1 ? 'es' : '' ?> — view all →
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

</body>
</html>