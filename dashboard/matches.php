<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = $_SESSION['user_id'];

// ── Dismiss ───────────────────────────────────────────────────────
if (isset($_POST['dismiss_match'])) {
    $mid = (int)$_POST['match_id'];
    // Verify the lost report belongs to this user before dismissing
    $conn->query("
        UPDATE matches SET status='dismissed'
        WHERE id=$mid
          AND lost_report_id IN (SELECT id FROM lost_reports WHERE user_id=$user_id)
    ");
    header("Location: matches.php?dismissed=1"); exit();
}

// ── Confirm → Matched ────────────────────────────────────────────
if (isset($_POST['confirm_match'])) {
    $mid = (int)$_POST['match_id'];
    $row = $conn->query("
        SELECT m.*, l.pet_name, l.user_id AS owner_id, l.id AS l_id,
               f.user_id AS finder_id, f.id AS f_id, f.found_place, f.contact_phone
        FROM matches m
        JOIN lost_reports  l ON m.lost_report_id  = l.id
        JOIN found_reports f ON m.found_report_id = f.id
        WHERE m.id=$mid AND l.user_id=$user_id AND m.status='pending'
        LIMIT 1
    ")->fetch_assoc();

    if ($row) {
        $conn->query("UPDATE matches SET status='confirmed' WHERE id=$mid");

        $pet       = $conn->real_escape_string($row['pet_name']);
        $finder_id = (int)$row['finder_id'];
        $owner_id  = (int)$row['owner_id'];
        $f_id      = (int)$row['f_id'];

        // Notify finder — contact details are now revealed on view_found
        $msg = $conn->real_escape_string(
            "The owner of \"$pet\" confirmed your found report is a match. "
          . "They can now see your contact details and will reach out soon. "
          . "Please keep an eye on your phone."
        );
        $conn->query("INSERT INTO notifications (user_id,type,message,link)
            VALUES ($finder_id,'match_confirmed','$msg','../public/view_found.php?id=$f_id')");

        // Notify owner — confirmation receipt with next step
        $owner_msg = $conn->real_escape_string(
            "You confirmed the match for \"$pet\". The finder's contact details are now visible. "
          . "Reach out to arrange the handoff, then come back here to mark the reunion complete."
        );
        $conn->query("INSERT INTO notifications (user_id,type,message,link)
VALUES ($owner_id,'match_confirmed_owner','$owner_msg','../dashboard/match_contact.php?id=$mid')");
    }
    header("Location: matches.php?confirmed=1"); exit();
}

// ── Mark as Reunited ─────────────────────────────────────────────
if (isset($_POST['mark_reunited'])) {
    $mid = (int)$_POST['match_id'];
    $row = $conn->query("
        SELECT m.*, l.pet_name, l.user_id AS owner_id, l.id AS l_id,
               f.user_id AS finder_id, f.id AS f_id, f.found_place
        FROM matches m
        JOIN lost_reports  l ON m.lost_report_id  = l.id
        JOIN found_reports f ON m.found_report_id = f.id
        WHERE m.id=$mid AND l.user_id=$user_id AND m.status='confirmed'
        LIMIT 1
    ")->fetch_assoc();

    if ($row) {
        // In your mark_reunited block in matches.php, verify both updates succeeded
$conn->query("UPDATE matches       SET status='reunited' WHERE id=$mid");
$conn->query("UPDATE lost_reports  SET status='reunited' WHERE id={$row['l_id']}");
$conn->query("UPDATE found_reports SET status='claimed'  WHERE id={$row['f_id']}");

// Dismiss remaining matches so they don't linger in the UI
$conn->query("
    UPDATE matches SET status='dismissed'
    WHERE lost_report_id = {$row['l_id']}
      AND id != $mid
      AND status IN ('pending','confirmed')
");

        $pet       = $conn->real_escape_string($row['pet_name']);
        $finder_id = (int)$row['finder_id'];
        $owner_id  = (int)$row['owner_id'];
        $f_id      = (int)$row['f_id'];

        // Notify finder — meaningful closure message
        $finder_msg = $conn->real_escape_string(
            "\"$pet\" has been marked as reunited! "
          . "The owner confirmed the pet is safely home thanks to your help. "
          . "You made a real difference for this family — thank you."
        );
        $conn->query("INSERT INTO notifications (user_id,type,message,link)
            VALUES ($finder_id,'reunited','$finder_msg','../public/view_found.php?id=$f_id')");

        // Notify owner
        $owner_msg = $conn->real_escape_string(
            "\"$pet\" is now marked as reunited. The case is closed and the finder has been thanked. "
          . "Welcome home, $pet!"
        );
        $conn->query("INSERT INTO notifications (user_id,type,message,link)
            VALUES ($owner_id,'reunited_owner','$owner_msg','matches.php')");
    }
    header("Location: matches.php?reunited=1"); exit();
}

// ── Load data ─────────────────────────────────────────────────────
$pending = $conn->query("
    SELECT m.*, l.pet_name, l.pet_type AS l_type, l.color AS l_color,
           l.breed AS l_breed, l.gender AS l_gender, l.photo AS l_photo,
           l.last_seen_place, l.last_seen_date,
           f.pet_type AS f_type, f.color AS f_color, f.breed AS f_breed,
           f.gender AS f_gender, f.photo AS f_photo,
           f.found_place, f.found_date, f.contact_name, f.contact_phone, f.id AS f_id
    FROM matches m
    JOIN lost_reports  l ON m.lost_report_id  = l.id
    JOIN found_reports f ON m.found_report_id = f.id
    WHERE l.user_id=$user_id AND m.status='pending'
    ORDER BY m.score DESC
")->fetch_all(MYSQLI_ASSOC);

$confirmed = $conn->query("
    SELECT m.*, l.pet_name, l.photo AS l_photo, l.last_seen_place,
           f.found_place, f.contact_name, f.contact_phone,
           f.photo AS f_photo, f.id AS f_id, f.pet_type AS f_type,
           f.color AS f_color, f.breed AS f_breed
    FROM matches m
    JOIN lost_reports  l ON m.lost_report_id  = l.id
    JOIN found_reports f ON m.found_report_id = f.id
    WHERE l.user_id=$user_id AND m.status='confirmed'
    ORDER BY m.updated_at DESC
")->fetch_all(MYSQLI_ASSOC);

$reunited = $conn->query("
    SELECT m.*, l.pet_name, f.found_place, f.contact_name, f.contact_phone, f.id AS f_id
    FROM matches m
    JOIN lost_reports  l ON m.lost_report_id  = l.id
    JOIN found_reports f ON m.found_report_id = f.id
    WHERE l.user_id=$user_id AND m.status='reunited'
    ORDER BY m.updated_at DESC LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$myMatches   = count($pending);
$unreadCount = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$name        = $_SESSION['name'];
$initials    = pf_initials($name);

pf_head('Match Alerts');
?>
<style>
/* ── Match card photo-left layout ──────────────────────────── */
.match-side {
  display: grid;
  grid-template-columns: 160px 1fr;
  gap: 0;
  align-items: stretch;
}
.match-photo {
  width: 160px;
  min-height: 180px;
  object-fit: cover;
  border-radius: 0;
  background: var(--surface2);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.match-photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  min-height: 180px;
}
.match-photo i { font-size: 2.8rem; color: var(--text3); }
.match-details { padding: 14px 16px; display: flex; flex-direction: column; gap: 5px; }
.match-pet-name { font-family: var(--display); font-size: 1.1rem; color: var(--text); line-height: 1.2; }
.match-meta-row { font-size: 12px; color: var(--text3); display: flex; align-items: center; gap: 5px; }
.match-meta-row i { font-size: 13px; color: var(--text3); }
.match-type-pill {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 10px; font-weight: 600; padding: 2px 8px;
  border-radius: 99px; text-transform: uppercase; letter-spacing: .06em;
  margin-bottom: 6px;
}
.pill-lost  { background: var(--red-lt);   color: var(--red); }
.pill-found { background: var(--green-lt); color: var(--green); }

/* Coordinating status card styling */
.coord-card {
  background: var(--surface);
  border: .5px solid rgba(146,64,14,.3);
  border-left: 3px solid #92400e;
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.coord-header {
  background: #fffbeb;
  padding: 12px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: .5px solid rgba(146,64,14,.15);
}
.vs-divider {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  flex-shrink: 0;
  border-left: .5px solid var(--border);
  border-right: .5px solid var(--border);
  background: var(--surface2);
}
</style>
<body>

<aside class="sb">
  <div class="sb-brand"><div class="sb-logo"><i class="ti ti-paw"></i></div><div><div class="sb-appname">Pawrtal</div><div class="sb-appsub">Dashboard</div></div></div>
  <nav class="sb-nav">
    <div class="sb-sec">Overview</div>
    <a href="index.php" class="sb-item"><i class="ti ti-home"></i> Dashboard</a>
    <div class="sb-sec">My activity</div>
    <a href="my_reports.php" class="sb-item"><i class="ti ti-clipboard-list"></i> My reports</a>
    <a href="matches.php" class="sb-item active"><i class="ti ti-link"></i> Match alerts<?php if($myMatches>0):?><span class="sb-badge"><?=$myMatches?></span><?php endif;?></a>
    <a href="my_pets.php" class="sb-item"><i class="ti ti-paw"></i> My pets</a>
    <div class="sb-sec">Community</div>
    <a href="map.php" class="sb-item"><i class="ti ti-map"></i> Map</a>
    <a href="../public/lost.php" class="sb-item"><i class="ti ti-search"></i> Browse reports</a>
    <div class="sb-sec">Account</div>
    <a href="notifications.php" class="sb-item"><i class="ti ti-bell"></i> Notifications<?php if($unreadCount>0):?><span class="sb-badge"><?=$unreadCount?></span><?php endif;?></a>
    <a href="../auth/logout.php" class="sb-item"><i class="ti ti-logout"></i> Logout</a>
  </nav>
  <div class="sb-foot"><div class="sb-user"><div class="sb-av"><?=htmlspecialchars($initials)?></div><div><div class="sb-uname"><?=htmlspecialchars($name)?></div><div class="sb-uemail"><?=htmlspecialchars($_SESSION['email']??'')?></div></div></div></div>
</aside>

<div class="main">
  <div class="topbar">
    <div><div class="topbar-title">Match alerts</div><div class="topbar-sub">Found pets matched to your lost reports</div></div>
    <?php if ($unreadCount > 0): ?>
    <a href="notifications.php" style="display:flex;align-items:center;gap:6px;font-size:13px;color:var(--green);font-weight:500;text-decoration:none;">
      <i class="ti ti-bell" style="font-size:16px;"></i><?= $unreadCount ?> new notification<?= $unreadCount>1?'s':''?>
    </a>
    <?php endif; ?>
  </div>

  <div class="content">

    <!-- Flash messages -->
    <?php if (isset($_GET['confirmed'])): ?>
      <div class="alert success"><i class="ti ti-circle-check"></i> Match confirmed. The finder has been notified and their contact details are now visible. Reach out to arrange the handoff, then mark it as Reunited.</div>
    <?php elseif (isset($_GET['reunited'])): ?>
      <div class="alert success"><i class="ti ti-heart-handshake"></i> Marked as reunited — the report is now closed and the finder has been thanked.</div>
    <?php elseif (isset($_GET['dismissed'])): ?>
      <div class="alert info"><i class="ti ti-info-circle"></i> Match dismissed.</div>
      <?php elseif (isset($_GET['cancelled'])): ?>
  <div class="alert info"><i class="ti ti-x"></i> Match cancelled. Your other pending matches are still active.</div>
    <?php endif; ?>

    <!-- ══ MATCHED — awaiting reunion ══════════════════════════ -->
    <?php if (!empty($confirmed)): ?>
    <div style="font-family:var(--display);font-size:.95rem;color:var(--text);display:flex;align-items:center;gap:8px;margin-bottom:4px;">
      Coordinating
      <span style="font-family:var(--font);font-size:12px;color:var(--amber);background:#fffbeb;border:.5px solid rgba(146,64,14,.2);padding:2px 9px;border-radius:99px;font-weight:600;">
        <i class="ti ti-clock" style="font-size:11px;vertical-align:-1px;"></i> <?= count($confirmed) ?> in progress
      </span>
    </div>

    <?php foreach ($confirmed as $m): ?>
    <div class="coord-card" style="margin-bottom:12px;">

      <!-- Header -->
      <div class="coord-header">
        <div style="display:flex;align-items:center;gap:10px;">
          <i class="ti ti-clock" style="font-size:1.1rem;color:#92400e;"></i>
          <div>
            <div style="font-family:var(--display);font-size:.9rem;color:var(--text);">Coordinating with finder</div>
            <div style="font-size:11px;color:#92400e;">Contact the finder below, arrange the handoff, then mark as Reunited.</div>
          </div>
        </div>
      </div>

      <!-- Photo-left comparison -->
      <div style="display:grid;grid-template-columns:1fr 40px 1fr;align-items:stretch;">

        <!-- Lost side -->
        <div class="match-side">
          <div class="match-photo">
            <?php if (!empty($m['l_photo'])): ?>
              <img src="../uploads/reports/<?= htmlspecialchars($m['l_photo']) ?>" alt="Your lost pet">
            <?php else: ?>
              <i class="ti ti-paw"></i>
            <?php endif; ?>
          </div>
          <div class="match-details">
            <div class="match-type-pill pill-lost"><i class="ti ti-alert-circle" style="font-size:11px;"></i> Your lost pet</div>
            <div class="match-pet-name"><?= htmlspecialchars($m['pet_name']) ?></div>
            <div class="match-meta-row"><i class="ti ti-map-pin"></i><?= htmlspecialchars($m['last_seen_place']) ?></div>
          </div>
        </div>

        <!-- VS divider -->
        <div class="vs-divider">
          <i class="ti ti-arrows-left-right" style="color:var(--text3);font-size:1rem;"></i>
        </div>

        <!-- Found side -->
        <div class="match-side">
          <div class="match-photo">
            <?php if (!empty($m['f_photo'])): ?>
              <img src="../uploads/reports/<?= htmlspecialchars($m['f_photo']) ?>" alt="Found pet">
            <?php else: ?>
              <i class="ti ti-paw"></i>
            <?php endif; ?>
          </div>
          <div class="match-details">
            <div class="match-type-pill pill-found"><i class="ti ti-circle-check" style="font-size:11px;"></i> Found report</div>
            <div class="match-pet-name"><?= ucfirst(htmlspecialchars($m['f_type'])) ?><?= $m['f_breed'] ? ' &middot; '.htmlspecialchars($m['f_breed']) : '' ?></div>
            <div class="match-meta-row"><i class="ti ti-color-swatch"></i><?= htmlspecialchars($m['f_color']) ?></div>
            <div class="match-meta-row"><i class="ti ti-map-pin"></i><?= htmlspecialchars($m['found_place']) ?></div>
          </div>
        </div>

      </div>

      <!-- Finder contact + actions -->
      <div style="padding:12px 18px;border-top:.5px solid var(--border);background:var(--surface);display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <div style="flex:1;min-width:160px;">
          <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Finder contact</div>
          <div style="font-size:13px;font-weight:600;color:var(--text);"><?= htmlspecialchars($m['contact_name']) ?></div>
          <?php if (!empty($m['contact_phone'])): ?>
          <a href="tel:<?= htmlspecialchars($m['contact_phone']) ?>" style="font-size:13px;color:var(--green);font-weight:500;text-decoration:none;display:flex;align-items:center;gap:5px;margin-top:2px;">
            <i class="ti ti-phone" style="font-size:14px;"></i><?= htmlspecialchars($m['contact_phone']) ?>
          </a>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="../public/view_found.php?id=<?= $m['f_id'] ?>" class="btn sm" target="_blank"><i class="ti ti-eye"></i> View found report</a>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
            <button name="mark_reunited" class="btn success sm"
                    onclick="return confirm('Confirm your pet is safely home? This closes the report and notifies the finder.')">
              <i class="ti ti-heart-handshake"></i> Mark as Reunited
            </button>
          </form>
        </div>
      </div>

    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- ══ PENDING ══════════════════════════════════════════════ -->
    <div style="font-family:var(--display);font-size:.95rem;color:var(--text);margin-bottom:4px;">
      Pending matches
      <span style="font-family:var(--font);font-size:13px;color:var(--text3);font-weight:400;">(<?= count($pending) ?>)</span>
    </div>

    <?php if (empty($pending)): ?>
    <div class="card">
      <div class="empty"><i class="ti ti-search"></i>No pending matches right now. We'll alert you when a found report closely matches your lost pet.</div>
    </div>

    <?php else: foreach ($pending as $m): ?>
    <div style="background:var(--surface);border:.5px solid #d1d5db;border-radius:var(--radius-lg);overflow:hidden;margin-bottom:10px;">

      <!-- Score header -->
      <div style="background:var(--surface2);padding:11px 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:.5px solid var(--border);">
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:44px;height:44px;border-radius:50%;background:var(--green);color:#fff;font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><?= $m['score'] ?>%</div>
          <div>
            <div style="font-family:var(--display);font-size:.9rem;color:var(--text);">Potential match</div>
            <div style="font-size:11px;color:var(--text3);">Score based on type, color, gender<?= $m['l_breed'] ? ', breed' : '' ?></div>
          </div>
        </div>
        <div style="font-size:11px;color:var(--text3);">#<?= $m['id'] ?></div>
      </div>

      <!-- Photo-left comparison -->
      <div style="display:grid;grid-template-columns:1fr 40px 1fr;align-items:stretch;">

        <!-- Lost side -->
        <div class="match-side">
          <div class="match-photo">
            <?php if (!empty($m['l_photo'])): ?>
              <img src="../uploads/reports/<?= htmlspecialchars($m['l_photo']) ?>" alt="Your lost pet">
            <?php else: ?>
              <i class="ti ti-paw"></i>
            <?php endif; ?>
          </div>
          <div class="match-details">
            <div class="match-type-pill pill-lost"><i class="ti ti-alert-circle" style="font-size:11px;"></i> Your lost pet</div>
            <div class="match-pet-name"><?= htmlspecialchars($m['pet_name']) ?></div>
            <div class="match-meta-row"><i class="ti ti-paw"></i><?= htmlspecialchars($m['l_type']) ?><?= $m['l_breed'] ? ' &middot; '.htmlspecialchars($m['l_breed']) : '' ?></div>
            <div class="match-meta-row"><i class="ti ti-color-swatch"></i><?= htmlspecialchars($m['l_color']) ?></div>
            <div class="match-meta-row"><i class="ti ti-map-pin"></i><?= htmlspecialchars($m['last_seen_place']) ?></div>
            <div class="match-meta-row"><i class="ti ti-calendar"></i>Last seen <?= date('M j, Y', strtotime($m['last_seen_date'])) ?></div>
          </div>
        </div>

        <!-- VS divider -->
        <div class="vs-divider">
          <i class="ti ti-arrows-left-right" style="color:var(--text3);font-size:1rem;"></i>
        </div>

        <!-- Found side -->
        <div class="match-side">
          <div class="match-photo">
            <?php if (!empty($m['f_photo'])): ?>
              <img src="../uploads/reports/<?= htmlspecialchars($m['f_photo']) ?>" alt="Found pet">
            <?php else: ?>
              <i class="ti ti-paw"></i>
            <?php endif; ?>
          </div>
          <div class="match-details">
            <div class="match-type-pill pill-found"><i class="ti ti-circle-check" style="font-size:11px;"></i> Found report</div>
            <div class="match-pet-name"><?= ucfirst(htmlspecialchars($m['f_type'])) ?><?= $m['f_breed'] ? ' &middot; '.htmlspecialchars($m['f_breed']) : '' ?></div>
            <div class="match-meta-row"><i class="ti ti-color-swatch"></i><?= htmlspecialchars($m['f_color']) ?></div>
            <div class="match-meta-row"><i class="ti ti-map-pin"></i><?= htmlspecialchars($m['found_place']) ?></div>
            <div class="match-meta-row"><i class="ti ti-calendar"></i>Found <?= date('M j, Y', strtotime($m['found_date'])) ?></div>
            <!-- Contact hidden until confirmed -->
            <div class="match-meta-row" style="margin-top:4px;padding-top:6px;border-top:.5px solid var(--border);color:var(--text3);font-style:italic;">
              <i class="ti ti-lock" style="font-size:12px;"></i> Contact revealed after confirmation
            </div>
          </div>
        </div>

      </div>

      <!-- Actions -->
      <div style="padding:11px 18px;border-top:.5px solid var(--border);display:flex;gap:8px;justify-content:flex-end;background:var(--surface);flex-wrap:wrap;">
        <form method="POST" style="display:inline;">
          <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
          <button name="dismiss_match" class="btn danger sm"
                  onclick="return confirm('Dismiss this match? It will be removed from your alerts.')">
            <i class="ti ti-x"></i> Not my pet
          </button>
        </form>
        <a href="../public/view_found.php?id=<?= $m['f_id'] ?>" class="btn sm" target="_blank">
          <i class="ti ti-eye"></i> View full report
        </a>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
          <button name="confirm_match" class="btn confirm sm"
                  onclick="return confirm('Confirm this is your pet? The finder will be notified and their contact details will be revealed to you.')">
            <i class="ti ti-check"></i> This is my pet
          </button>
        </form>
      </div>

    </div>
    <?php endforeach; endif; ?>

    <!-- ══ REUNITED ═════════════════════════════════════════════ -->
    <?php if (!empty($reunited)): ?>
    <div style="font-family:var(--display);font-size:.95rem;color:var(--text);margin-bottom:4px;margin-top:8px;">Reunited</div>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php foreach ($reunited as $m): ?>
      <div style="background:var(--surface);border:.5px solid var(--border);border-left:3px solid var(--green);border-radius:var(--radius);padding:13px 16px;display:flex;align-items:center;gap:12px;">
        <i class="ti ti-heart-handshake" style="font-size:1.4rem;color:var(--green);flex-shrink:0;"></i>
        <div style="flex:1;">
          <div style="font-size:13px;font-weight:500;color:var(--text);">
            <strong><?= htmlspecialchars($m['pet_name']) ?></strong> is home — reunited!
          </div>
          <div style="font-size:11px;color:var(--text3);margin-top:2px;">
            <i class="ti ti-map-pin" style="font-size:11px;vertical-align:-1px;"></i>
            Found at <?= htmlspecialchars($m['found_place']) ?> &middot; Finder: <?= htmlspecialchars($m['contact_name']) ?>
          </div>
        </div>
        <span style="font-size:11px;background:var(--green-lt);color:var(--green);padding:3px 9px;border-radius:99px;font-weight:600;white-space:nowrap;">
          <i class="ti ti-check" style="font-size:11px;vertical-align:-1px;"></i> Closed
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>
</body>
</html>
