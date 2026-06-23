<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = (int)$_SESSION['user_id'];

$mid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$mid) { header("Location: matches.php"); exit(); }

// ── Cancel Match ─────────────────────────────────────────────
if (isset($_POST['cancel_match'])) {
    $row = $conn->query("
        SELECT m.id, l.pet_name, l.user_id AS owner_id, l.id AS l_id,
               f.user_id AS finder_id, f.id AS f_id
        FROM matches m
        JOIN lost_reports  l ON m.lost_report_id = l.id
        JOIN found_reports f ON m.found_report_id = f.id
        WHERE m.id = $mid AND l.user_id = $user_id AND m.status = 'confirmed'
        LIMIT 1
    ")->fetch_assoc();

    if ($row) {
        // Cancel just this match — found report stays active
        $conn->query("UPDATE matches SET status='cancelled' WHERE id=$mid");

        $pet       = $conn->real_escape_string($row['pet_name']);
        $owner_id  = (int)$row['owner_id'];
        $finder_id = (int)$row['finder_id'];
        $f_id      = (int)$row['f_id'];

        // Notify owner
        $owner_msg = $conn->real_escape_string(
            "You cancelled the confirmed match for \"$pet\" (match #$mid). "
          . "Your other pending matches are still active — check Match alerts to continue your search."
        );
        $conn->query("INSERT INTO notifications (user_id,type,message,link)
            VALUES ($owner_id,'match_cancelled','$owner_msg','../dashboard/matches.php')");

        // Notify finder
        $finder_msg = $conn->real_escape_string(
            "The owner of \"$pet\" has cancelled the match with your found report (match #$mid). "
          . "Your found report remains active and may match other lost pets."
        );
        $conn->query("INSERT INTO notifications (user_id,type,message,link)
            VALUES ($finder_id,'match_cancelled','$finder_msg','../public/view_found.php?id=$f_id')");
    }

    header("Location: matches.php?cancelled=1"); exit();
}

// ── Load match ────────────────────────────────────────────────
$match = $conn->query("
    SELECT m.*,
           l.pet_name, l.pet_type AS l_type, l.color AS l_color,
           l.breed AS l_breed, l.gender AS l_gender,
           l.photo AS l_photo, l.last_seen_place, l.last_seen_date,
           l.user_id AS owner_id,
           f.id AS f_id, f.pet_type AS f_type, f.color AS f_color,
           f.breed AS f_breed, f.gender AS f_gender,
           f.photo AS f_photo, f.found_place, f.found_date,
           f.contact_name AS finder_name,
           f.contact_phone AS finder_phone,
           f.user_id AS finder_user_id,
           u.phone AS finder_account_phone,
           u.name  AS finder_account_name
    FROM matches m
    JOIN lost_reports  l ON m.lost_report_id  = l.id
    JOIN found_reports f ON m.found_report_id = f.id
    JOIN users         u ON f.user_id         = u.id
    WHERE m.id = $mid
      AND l.user_id = $user_id
      AND m.status IN ('confirmed','reunited','cancelled')
    LIMIT 1
")->fetch_assoc();

if (!$match) {    die("DEBUG — mid=$mid | user_id=$user_id | query returned nothing. Check match status and owner_id.");}


$display_phone = !empty($match['finder_account_phone'])
    ? $match['finder_account_phone']
    : $match['finder_phone'];
$display_name  = !empty($match['finder_account_name'])
    ? $match['finder_account_name']
    : $match['finder_name'];

// Days since confirmed — used to show escalation tip
$days_waiting = (int)(new DateTimeImmutable($match['updated_at']))->diff(new DateTimeImmutable())->days;

$myMatches   = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];
$unreadCount = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$name        = $_SESSION['name'];
$initials    = pf_initials($name);

pf_head('Contact Finder');
?>
<body>

<aside class="sb">
  <div class="sb-brand"><div class="sb-logo"><i class="ti ti-paw"></i></div><div><div class="sb-appname">Pawrtal</div><div class="sb-appsub">Dashboard</div></div></div>
  <nav class="sb-nav">
    <div class="sb-sec">Overview</div>
    <a href="index.php" class="sb-item"><i class="ti ti-home"></i> Dashboard</a>
    <div class="sb-sec">My activity</div>
    <a href="my_reports.php" class="sb-item"><i class="ti ti-clipboard-list"></i> My reports</a>
    <a href="matches.php" class="sb-item">
      <i class="ti ti-link"></i> Match alerts
      <?php if ($myMatches > 0): ?><span class="sb-badge"><?= $myMatches ?></span><?php endif; ?>
    </a>
    <a href="my_pets.php" class="sb-item"><i class="ti ti-paw"></i> My pets</a>
    <div class="sb-sec">Community</div>
    <a href="map.php" class="sb-item"><i class="ti ti-map"></i> Map</a>
    <a href="../public/lost.php" class="sb-item"><i class="ti ti-search"></i> Browse reports</a>
    <div class="sb-sec">Account</div>
    <a href="notifications.php" class="sb-item"><i class="ti ti-bell"></i> Notifications
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
      <div class="topbar-title">Contact Finder</div>
      <div class="topbar-sub">Match #<?= $match['id'] ?> &mdash; <?= htmlspecialchars($match['pet_name']) ?></div>
    </div>
    <a href="matches.php" style="display:flex;align-items:center;gap:5px;font-size:13px;color:var(--text3);text-decoration:none;">
      <i class="ti ti-arrow-left" style="font-size:15px;"></i> Back to matches
    </a>
  </div>

  <div class="content">

    <?php if ($match['status'] === 'cancelled'): ?>
    <!-- ── Cancelled state ── -->
    <div class="alert info" style="margin-bottom:16px;">
      <i class="ti ti-info-circle"></i>
      This match was cancelled. The found report is still active and may appear in future matches.
      <a href="matches.php" style="color:var(--green);font-weight:500;margin-left:4px;">View your pending matches →</a>
    </div>

    <?php elseif ($match['status'] === 'reunited'): ?>
    <!-- ── Reunited state ── -->
    <div class="alert success" style="margin-bottom:16px;">
      <i class="ti ti-heart-handshake"></i>
      <?= htmlspecialchars($match['pet_name']) ?> is home! This case is closed.
    </div>

    <?php else: ?>
    <!-- ── Active contact card ── -->
    <div style="background:var(--surface);border:.5px solid rgba(45,106,79,.3);border-left:3px solid var(--green);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:16px;">

      <div style="background:var(--green-lt);padding:14px 20px;display:flex;align-items:center;gap:12px;border-bottom:.5px solid rgba(45,106,79,.15);">
        <div style="width:40px;height:40px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="ti ti-circle-check" style="font-size:1.2rem;color:#fff;"></i>
        </div>
        <div>
          <div style="font-family:var(--display);font-size:1rem;color:var(--text);">Match confirmed — contact the finder</div>
          <div style="font-size:12px;color:var(--green);margin-top:1px;">
            The finder's phone number is now visible. Reach out to arrange the handoff.
          </div>
        </div>
      </div>

      <div style="padding:24px 24px 20px;">

        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
          <div style="width:60px;height:60px;border-radius:50%;background:var(--green);display:flex;align-items:center;justify-content:center;font-family:var(--display);font-size:1.4rem;color:#fff;flex-shrink:0;">
            <?= strtoupper(mb_substr($display_name, 0, 1)) ?>
          </div>
          <div style="flex:1;min-width:180px;">
            <div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:4px;">Finder</div>
            <div style="font-size:1.1rem;font-weight:600;color:var(--text);margin-bottom:8px;">
              <?= htmlspecialchars($display_name) ?>
            </div>
            <?php if (!empty($display_phone)): ?>
            <a href="tel:<?= htmlspecialchars($display_phone) ?>"
               style="display:inline-flex;align-items:center;gap:8px;background:var(--green);color:#fff;padding:10px 20px;border-radius:var(--radius);font-size:15px;font-weight:600;text-decoration:none;">
              <i class="ti ti-phone" style="font-size:17px;"></i>
              <?= htmlspecialchars($display_phone) ?>
            </a>
            <?php else: ?>
            <div style="font-size:13px;color:var(--text3);font-style:italic;">
              <i class="ti ti-info-circle" style="font-size:13px;vertical-align:-1px;"></i>
              No phone number on file. Check the found report for details.
            </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($days_waiting >= 7): ?>
        <!-- Escalation tip after 7 days -->
        <div style="margin-top:16px;padding:12px 14px;background:#fff7ed;border:.5px solid rgba(194,65,12,.2);border-radius:var(--radius);font-size:12px;color:#9a3412;line-height:1.6;">
          <i class="ti ti-alert-triangle" style="font-size:13px;vertical-align:-1px;"></i>
          <strong>It's been <?= $days_waiting ?> days with no response.</strong>
          If the finder isn't reachable, you can cancel this match below — your other pending matches will become available again.
        </div>
        <?php elseif ($days_waiting >= 3): ?>
        <!-- Gentle nudge after 3 days -->
        <div style="margin-top:16px;padding:12px 14px;background:var(--surface2);border-radius:var(--radius);font-size:12px;color:var(--text3);line-height:1.6;">
          <i class="ti ti-clock" style="font-size:13px;vertical-align:-1px;color:var(--amber);"></i>
          It's been <?= $days_waiting ?> days since you confirmed this match. If you haven't heard back yet, try reaching out again or cancel and check your other matches.
        </div>
        <?php else: ?>
        <!-- Normal tip -->
        <div style="margin-top:16px;padding:12px 14px;background:var(--surface2);border-radius:var(--radius);font-size:12px;color:var(--text3);line-height:1.6;">
          <i class="ti ti-bulb" style="font-size:13px;vertical-align:-1px;color:var(--amber);"></i>
          <strong style="color:var(--text);">Next steps:</strong>
          Call or text the finder to arrange a handoff. Once your pet is home, come back and mark the case as Reunited.
        </div>
        <?php endif; ?>

      </div>

      <!-- Actions -->
      <div style="padding:12px 18px;border-top:.5px solid var(--border);background:var(--surface);display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;align-items:center;">
        <form method="POST" style="display:inline;">
          <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
          <button name="cancel_match" class="btn danger sm"
                  onclick="return confirm('Cancel this match? The found report will stay active. Your other pending matches will become available again.')">
            <i class="ti ti-x"></i> Cancel Match
          </button>
        </form>
        <a href="../public/view_found.php?id=<?= $match['f_id'] ?>" class="btn sm" target="_blank">
          <i class="ti ti-eye"></i> View found report
        </a>
        <form method="POST" action="matches.php">
          <input type="hidden" name="match_id" value="<?= $match['id'] ?>">
          <button name="mark_reunited" class="btn success sm"
                  onclick="return confirm('Confirm your pet is safely home? This closes the report and notifies the finder.')">
            <i class="ti ti-heart-handshake"></i> Mark as Reunited
          </button>
        </form>
      </div>

    </div>
    <?php endif; ?>

    <!-- ── Match summary ── -->
    <div style="font-family:var(--display);font-size:.9rem;color:var(--text);margin-bottom:8px;">Match summary</div>
    <div style="background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">

      <div style="display:grid;grid-template-columns:1fr 40px 1fr;align-items:stretch;">

        <!-- Lost side -->
        <div style="display:grid;grid-template-columns:120px 1fr;align-items:stretch;">
          <div style="background:var(--surface2);display:flex;align-items:center;justify-content:center;min-height:140px;">
            <?php if (!empty($match['l_photo'])): ?>
              <img src="../uploads/reports/<?= htmlspecialchars($match['l_photo']) ?>"
                   style="width:120px;height:140px;object-fit:cover;" alt="Your lost pet">
            <?php else: ?>
              <i class="ti ti-paw" style="font-size:2rem;color:var(--text3);"></i>
            <?php endif; ?>
          </div>
          <div style="padding:12px 14px;display:flex;flex-direction:column;gap:5px;">
            <div style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:99px;text-transform:uppercase;letter-spacing:.06em;background:var(--red-lt);color:var(--red);width:fit-content;">
              <i class="ti ti-alert-circle" style="font-size:11px;"></i> Your lost pet
            </div>
            <div style="font-family:var(--display);font-size:1rem;color:var(--text);"><?= htmlspecialchars($match['pet_name']) ?></div>
            <div style="font-size:12px;color:var(--text3);display:flex;align-items:center;gap:4px;">
              <i class="ti ti-paw" style="font-size:12px;"></i>
              <?= htmlspecialchars($match['l_type']) ?><?= $match['l_breed'] ? ' · '.htmlspecialchars($match['l_breed']) : '' ?>
            </div>
            <div style="font-size:12px;color:var(--text3);display:flex;align-items:center;gap:4px;">
              <i class="ti ti-map-pin" style="font-size:12px;"></i>
              <?= htmlspecialchars($match['last_seen_place']) ?>
            </div>
          </div>
        </div>

        <!-- Divider -->
        <div style="display:flex;align-items:center;justify-content:center;border-left:.5px solid var(--border);border-right:.5px solid var(--border);background:var(--surface2);">
          <i class="ti ti-arrows-left-right" style="color:var(--text3);font-size:1rem;"></i>
        </div>

        <!-- Found side -->
        <div style="display:grid;grid-template-columns:120px 1fr;align-items:stretch;">
          <div style="background:var(--surface2);display:flex;align-items:center;justify-content:center;min-height:140px;">
            <?php if (!empty($match['f_photo'])): ?>
              <img src="../uploads/reports/<?= htmlspecialchars($match['f_photo']) ?>"
                   style="width:120px;height:140px;object-fit:cover;" alt="Found pet">
            <?php else: ?>
              <i class="ti ti-paw" style="font-size:2rem;color:var(--text3);"></i>
            <?php endif; ?>
          </div>
          <div style="padding:12px 14px;display:flex;flex-direction:column;gap:5px;">
            <div style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:99px;text-transform:uppercase;letter-spacing:.06em;background:var(--green-lt);color:var(--green);width:fit-content;">
              <i class="ti ti-circle-check" style="font-size:11px;"></i> Found report
            </div>
            <div style="font-family:var(--display);font-size:1rem;color:var(--text);">
              <?= ucfirst(htmlspecialchars($match['f_type'])) ?><?= $match['f_breed'] ? ' · '.htmlspecialchars($match['f_breed']) : '' ?>
            </div>
            <div style="font-size:12px;color:var(--text3);display:flex;align-items:center;gap:4px;">
              <i class="ti ti-color-swatch" style="font-size:12px;"></i>
              <?= htmlspecialchars($match['f_color']) ?>
            </div>
            <div style="font-size:12px;color:var(--text3);display:flex;align-items:center;gap:4px;">
              <i class="ti ti-map-pin" style="font-size:12px;"></i>
              <?= htmlspecialchars($match['found_place']) ?>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

</body>
</html>