<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = (int)$_SESSION['user_id'];

// Mark all as read on page load
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$user_id AND is_read=0");

$notifications = $conn->query("
    SELECT * FROM notifications
    WHERE user_id=$user_id
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$myMatches   = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];
$unreadCount = 0; // just marked them all read

$name     = $_SESSION['name'];
$initials = pf_initials($name);

// Icon + color config for every notification type
function notifStyle(string $type): array {
    return match($type) {
        // ADD these three cases before the default:
'claim_submitted'   => ['ti-inbox',        'var(--amber)',  '#fffbeb',         'rgba(146,64,14,.15)'],
'claim_approved'    => ['ti-circle-check', 'var(--green)',  'var(--green-lt)', 'rgba(45,106,79,.15)'],
'claim_rejected'    => ['ti-circle-x',     'var(--red)',    'var(--red-lt)',   'rgba(139,38,53,.15)'],
'match_reminder_3'  => ['ti-clock',        'var(--amber)',  '#fffbeb',         'rgba(146,64,14,.15)'],
'match_reminder_7'  => ['ti-alarm',        'var(--red)',    'var(--red-lt)',   'rgba(139,38,53,.15)'],
'match_cancelled'   => ['ti-x',            'var(--text3)', 'var(--surface2)', 'var(--border)'],
        'match_confirmed'       => ['ti-check',           'var(--green)',  'var(--green-lt)',  'rgba(45,106,79,.15)'],
        'match_confirmed_owner' => ['ti-circle-check',    'var(--green)',  'var(--green-lt)',  'rgba(45,106,79,.15)'],
        'reunited'              => ['ti-heart-handshake', 'var(--green)',  'var(--green-lt)',  'rgba(45,106,79,.15)'],
        'reunited_owner'        => ['ti-heart-handshake', 'var(--green)',  'var(--green-lt)',  'rgba(45,106,79,.15)'],
        'qr_claim'              => ['ti-search-heart',    'var(--amber)',  '#fffbeb',          'rgba(146,64,14,.15)'],
        'qr_found'              => ['ti-map-pin',         'var(--amber)',  '#fffbeb',          'rgba(146,64,14,.15)'],
        'match_found'           => ['ti-link',            'var(--green)',  'var(--green-lt)',  'rgba(45,106,79,.15)'],
        'lost_report'           => ['ti-alert-circle',    'var(--red)',    'var(--red-lt)',    'rgba(139,38,53,.15)'],
        default                 => ['ti-bell',            'var(--text3)', 'var(--surface2)',  'var(--border)'],
    };
}

function notifLabel(string $type): string {
    return match($type) {
        // ADD these before the default:
'claim_submitted'   => 'Ownership claim received',
'claim_approved'    => 'Claim approved — contact revealed',
'claim_rejected'    => 'Claim not approved',
'match_reminder_3'  => 'Match reminder — day 3',
'match_reminder_7'  => 'Match reminder — day 7',
'match_cancelled'   => 'Match cancelled',
        'match_confirmed'       => 'Match confirmed',
        'match_confirmed_owner' => 'Match confirmed',
        'reunited'              => 'Pet reunited',
        'reunited_owner'        => 'Pet reunited',
        'qr_claim'              => 'QR tag scanned — possible owner',
        'qr_found'              => 'QR tag scanned — finder alert',
        'match_found'           => 'New match found',
        'lost_report'           => 'Lost report update',
        default                 => 'Notification',
    };
}

pf_head('Notifications');
?>
<body>

<aside class="sb">
  <div class="sb-brand"><div class="sb-logo"><i class="ti ti-paw"></i></div><div><div class="sb-appname">Pawrtal</div><div class="sb-appsub">Dashboard</div></div></div>
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
    <a href="../public/lost.php" class="sb-item"><i class="ti ti-search"></i> Browse reports</a>
    <div class="sb-sec">Account</div>
    <a href="notifications.php" class="sb-item active"><i class="ti ti-bell"></i> Notifications</a>
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
      <div class="topbar-title">Notifications</div>
      <div class="topbar-sub">Updates about matches, reports, and pet recovery activity</div>
    </div>
  </div>

  <div class="content">

    <?php if (empty($notifications)): ?>
    <div class="card">
      <div class="empty">
        <i class="ti ti-bell-off"></i>
        No notifications yet. You'll be alerted here when matches are found, confirmed, or when someone scans your pet's QR tag.
      </div>
    </div>

    <?php else: ?>

    <div style="display:flex;flex-direction:column;gap:8px;">
    <?php foreach ($notifications as $n):
      [$icon, $iconColor, $iconBg, $iconBorder] = notifStyle($n['type']);
      $label = notifLabel($n['type']);
      $hasLink = !empty($n['link']) && $n['link'] !== '#';
    ?>

    <?php if ($hasLink): ?>
    <a href="<?= htmlspecialchars($n['link']) ?>" style="text-decoration:none;color:inherit;display:flex;gap:12px;background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:14px 16px;transition:background .12s;" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background='var(--surface)'">
    <?php else: ?>
    <div style="display:flex;gap:12px;background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:14px 16px;">
    <?php endif; ?>

      <!-- Icon -->
      <div style="width:40px;height:40px;border-radius:50%;background:<?= $iconBg ?>;border:.5px solid <?= $iconBorder ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="ti <?= $icon ?>" style="font-size:18px;color:<?= $iconColor ?>;"></i>
      </div>

      <!-- Content -->
      <div style="flex:1;min-width:0;">
        <div style="font-size:11px;font-weight:600;color:<?= $iconColor ?>;text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">
          <?= $label ?>
        </div>
        <div style="font-size:13px;color:var(--text);line-height:1.5;">
          <?= htmlspecialchars($n['message']) ?>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:5px;display:flex;align-items:center;gap:4px;">
          <i class="ti ti-clock" style="font-size:12px;"></i>
          <?= date('M j, Y \a\t g:i A', strtotime($n['created_at'])) ?>
        </div>
      </div>

      <?php if ($hasLink): ?>
      <div style="flex-shrink:0;display:flex;align-items:center;">
        <i class="ti ti-chevron-right" style="font-size:16px;color:var(--text3);"></i>
      </div>
      <?php endif; ?>

    <?php echo $hasLink ? '</a>' : '</div>'; ?>

    <?php endforeach; ?>
    </div>

    <?php endif; ?>

  </div>
  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

</body>
</html>
