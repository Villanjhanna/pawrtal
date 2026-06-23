<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = (int)$_SESSION['user_id'];

$found_id = isset($_GET['found_id']) ? (int)$_GET['found_id'] : 0;
if (!$found_id) { header("Location: matches.php"); exit(); }

// Must be the finder who owns this found report
$found = $conn->query("
    SELECT f.*, u.name AS finder_name
    FROM found_reports f
    JOIN users u ON f.user_id = u.id
    WHERE f.id = $found_id AND f.user_id = $user_id
    LIMIT 1
")->fetch_assoc();
if (!$found) { header("Location: matches.php"); exit(); }

// ── Handle approve / reject ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $claim_id    = (int)($_POST['claim_id'] ?? 0);
    $action      = $_POST['action'] ?? '';
    $finder_note = trim($_POST['finder_note'] ?? '');

    // Verify claim belongs to this found report
    $claim = $conn->query("
        SELECT c.*, p.name AS pet_name, u.name AS claimant_name
        FROM claims c
        JOIN pets  p ON c.pet_id      = p.id
        JOIN users u ON c.claimant_id = u.id
        WHERE c.id = $claim_id AND c.found_report_id = $found_id
        LIMIT 1
    ")->fetch_assoc();

    if ($claim) {
        $claimant_id  = (int)$claim['claimant_id'];
        $pet_name_esc = $conn->real_escape_string($claim['pet_name']);
        $note_esc     = $conn->real_escape_string($finder_note);

        if ($action === 'approve') {
            $conn->query("UPDATE claims SET status='approved', finder_note=NULL WHERE id=$claim_id");

            // Reject all other pending claims for this found report
            $conn->query("
                UPDATE claims SET status='rejected',
                    finder_note='Another claimant was approved by the finder.'
                WHERE found_report_id=$found_id AND id != $claim_id AND status='pending'
            ");

            // Notify the approved claimant
            $msg = $conn->real_escape_string(
                "Your claim for \"$pet_name_esc\" has been approved by the finder! "
              . "Their contact details are now revealed — reach out to arrange the handoff."
            );
            $conn->query("
                INSERT INTO notifications (user_id,type,message,link)
                VALUES ($claimant_id,'claim_approved','$msg',
                        '../dashboard/match_contact.php?found_id=$found_id&claim_id=$claim_id')
            ");

            // Notify rejected claimants
            $rejected = $conn->query("
                SELECT claimant_id FROM claims
                WHERE found_report_id=$found_id AND id != $claim_id AND status='rejected'
            ")->fetch_all(MYSQLI_ASSOC);
            foreach ($rejected as $r) {
                $rid = (int)$r['claimant_id'];
                $rej_msg = $conn->real_escape_string(
                    "Your ownership claim for the found $found[pet_type] was not approved this time. "
                  . "The finder approved a different claimant. If you believe this is your pet, "
                  . "you can contact Pawrtal support."
                );
                $conn->query("
                    INSERT INTO notifications (user_id,type,message,link)
                    VALUES ($rid,'claim_rejected','$rej_msg','../public/lost.php')
                ");
            }

            header("Location: review_claims.php?found_id=$found_id&approved=1"); exit();

        } elseif ($action === 'reject') {
            $conn->query("
                UPDATE claims SET status='rejected', finder_note='$note_esc'
                WHERE id=$claim_id
            ");

            $msg = $conn->real_escape_string(
                "Your ownership claim for the found {$found['pet_type']} was not approved."
              . ($finder_note ? " Finder's note: $finder_note" : '')
              . " You can resubmit with additional proof if you believe this is your pet."
            );
            $conn->query("
                INSERT INTO notifications (user_id,type,message,link)
                VALUES ($claimant_id,'claim_rejected','$msg',
                        '../dashboard/submit_claim.php?found_id=$found_id')
            ");

            header("Location: review_claims.php?found_id=$found_id&rejected=1"); exit();
        }
    }
}

// ── Load all claims for this found report ─────────────────────
$claims = $conn->query("
    SELECT c.*,
           p.name AS pet_name, p.type AS pet_type, p.breed AS pet_breed,
           p.color AS pet_color, p.gender AS pet_gender, p.age_years,
           p.photo AS pet_photo, p.description AS pet_description,
           u.name AS claimant_name, u.email AS claimant_email
    FROM claims c
    JOIN pets  p ON c.pet_id      = p.id
    JOIN users u ON c.claimant_id = u.id
    WHERE c.found_report_id = $found_id
    ORDER BY
        FIELD(c.status,'pending','approved','rejected'),
        c.created_at ASC
")->fetch_all(MYSQLI_ASSOC);

$myMatches   = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];
$unreadCount = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$name        = $_SESSION['name'];
$initials    = pf_initials($name);

$pending_count  = count(array_filter($claims, fn($c) => $c['status'] === 'pending'));
$approved_count = count(array_filter($claims, fn($c) => $c['status'] === 'approved'));

pf_head('Review Claims');
?>
<body>
<aside class="sb">
  <div class="sb-brand"><div class="sb-logo"><i class="ti ti-paw"></i></div><div><div class="sb-appname">Pawrtal</div><div class="sb-appsub">Dashboard</div></div></div>
  <nav class="sb-nav">
    <div class="sb-sec">Overview</div>
    <a href="index.php" class="sb-item"><i class="ti ti-home"></i> Dashboard</a>
    <div class="sb-sec">My activity</div>
    <a href="my_reports.php" class="sb-item"><i class="ti ti-clipboard-list"></i> My reports</a>
    <a href="matches.php" class="sb-item"><i class="ti ti-link"></i> Match alerts<?php if($myMatches>0):?><span class="sb-badge"><?=$myMatches?></span><?php endif;?></a>
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
    <div>
      <div class="topbar-title">Review Claims</div>
      <div class="topbar-sub">
        <?= $pending_count ?> pending &middot; <?= $approved_count ?> approved
        &mdash; Found <?= ucfirst(htmlspecialchars($found['pet_type'])) ?>
        at <?= htmlspecialchars($found['found_place']) ?>
      </div>
    </div>
    <a href="../public/view_found.php?id=<?= $found_id ?>" style="display:flex;align-items:center;gap:5px;font-size:13px;color:var(--text3);text-decoration:none;">
      <i class="ti ti-arrow-left" style="font-size:15px;"></i> Back to report
    </a>
  </div>

  <div class="content">

    <?php if (isset($_GET['approved'])): ?>
      <div class="alert success"><i class="ti ti-circle-check"></i> Claim approved. The owner has been notified and can now see your contact details.</div>
    <?php elseif (isset($_GET['rejected'])): ?>
      <div class="alert info"><i class="ti ti-info-circle"></i> Claim rejected. The claimant has been notified and can resubmit with more proof.</div>
    <?php endif; ?>

    <?php if ($approved_count > 0): ?>
    <div style="background:#fffbeb;border:.5px solid rgba(146,64,14,.2);border-radius:var(--radius);padding:10px 14px;font-size:12px;color:#92400e;margin-bottom:12px;display:flex;align-items:center;gap:8px;">
      <i class="ti ti-lock" style="font-size:14px;"></i>
      A claim has already been approved for this report. You can still review other claims but approving again is not recommended.
    </div>
    <?php endif; ?>

    <?php if (empty($claims)): ?>
    <div class="card">
      <div class="empty"><i class="ti ti-inbox"></i> No claims submitted yet for this found report.</div>
    </div>

    <?php else: foreach ($claims as $c):
      $status_color  = match($c['status']) { 'approved' => 'var(--green)', 'rejected' => 'var(--red)', default => 'var(--amber)' };
      $status_bg     = match($c['status']) { 'approved' => 'var(--green-lt)', 'rejected' => 'var(--red-lt)', default => '#fffbeb' };
      $status_label  = match($c['status']) { 'approved' => 'Approved', 'rejected' => 'Rejected', default => 'Pending review' };
      $status_icon   = match($c['status']) { 'approved' => 'ti-circle-check', 'rejected' => 'ti-circle-x', default => 'ti-clock' };
      $border_color  = match($c['status']) { 'approved' => 'rgba(45,106,79,.3)', 'rejected' => 'rgba(139,38,53,.2)', default => 'rgba(146,64,14,.3)' };
      $border_left   = match($c['status']) { 'approved' => 'var(--green)', 'rejected' => 'var(--red)', default => '#92400e' };
    ?>
    <div style="background:var(--surface);border:.5px solid <?= $border_color ?>;border-left:3px solid <?= $border_left ?>;border-radius:var(--radius-lg);overflow:hidden;margin-bottom:14px;">

      <!-- Claim header -->
      <div style="padding:12px 16px;background:<?= $status_bg ?>;border-bottom:.5px solid <?= $border_color ?>;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:8px;">
          <i class="ti <?= $status_icon ?>" style="color:<?= $status_color ?>;font-size:1.1rem;"></i>
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--text);"><?= htmlspecialchars($c['claimant_name']) ?></div>
            <div style="font-size:11px;color:var(--text3);">Submitted <?= date('M j, Y \a\t g:i A', strtotime($c['created_at'])) ?></div>
          </div>
        </div>
        <span style="font-size:11px;font-weight:600;color:<?= $status_color ?>;background:<?= $status_bg ?>;border:.5px solid <?= $border_color ?>;padding:3px 10px;border-radius:99px;text-transform:uppercase;letter-spacing:.06em;">
          <?= $status_label ?>
        </span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">

        <!-- Pet profile -->
        <div style="padding:14px 16px;border-right:.5px solid var(--border);">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);font-weight:600;margin-bottom:10px;">Registered pet profile</div>
          <div style="display:flex;gap:10px;align-items:flex-start;">
            <?php if (!empty($c['pet_photo'])): ?>
              <img src="../uploads/pets/<?= htmlspecialchars($c['pet_photo']) ?>"
                   style="width:56px;height:56px;object-fit:cover;border-radius:50%;flex-shrink:0;border:.5px solid var(--border);" alt="<?= htmlspecialchars($c['pet_name']) ?>">
            <?php else: ?>
              <div style="width:56px;height:56px;border-radius:50%;background:var(--surface2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="ti ti-paw" style="font-size:1.4rem;color:var(--text3);"></i>
              </div>
            <?php endif; ?>
            <div>
              <div style="font-size:14px;font-weight:600;color:var(--text);"><?= htmlspecialchars($c['pet_name']) ?></div>
              <div style="font-size:12px;color:var(--text3);">
                <?= ucfirst(htmlspecialchars($c['pet_type'])) ?>
                <?= !empty($c['pet_breed']) ? '· '.htmlspecialchars($c['pet_breed']) : '' ?>
              </div>
              <?php if (!empty($c['pet_color'])): ?>
              <div style="font-size:12px;color:var(--text3);margin-top:2px;">
                <i class="ti ti-color-swatch" style="font-size:11px;"></i> <?= htmlspecialchars($c['pet_color']) ?>
              </div>
              <?php endif; ?>
              <?php if (!empty($c['pet_description'])): ?>
              <div style="font-size:11px;color:var(--text3);margin-top:6px;line-height:1.5;font-style:italic;">
                "<?= htmlspecialchars(mb_substr($c['pet_description'], 0, 100)) ?><?= strlen($c['pet_description']) > 100 ? '…' : '' ?>"
              </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Proof -->
        <div style="padding:14px 16px;">
          <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);font-weight:600;margin-bottom:10px;">Proof submitted</div>

          <?php if (!empty($c['proof_photo'])): ?>
          <a href="../uploads/claims/<?= htmlspecialchars($c['proof_photo']) ?>" target="_blank"
   style="display:block;margin-bottom:8px;">
  <img src="../uploads/claims/<?= htmlspecialchars($c['proof_photo']) ?>"
       style="width:100%;height:auto;max-height:280px;object-fit:contain;border-radius:var(--radius);border:.5px solid var(--border);background:var(--surface2);padding:4px;" alt="Proof photo">
</a>
<div style="font-size:11px;color:var(--text3);margin-bottom:8px;">
  <i class="ti ti-external-link" style="font-size:11px;vertical-align:-1px;"></i>
  Click image to view full size
</div>
          <?php endif; ?>

          <div style="font-size:12px;color:var(--text);line-height:1.6;background:var(--surface2);border-radius:var(--radius);padding:8px 10px;">
            <div style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:4px;">Distinguishing mark</div>
            <?= htmlspecialchars($c['mark_description']) ?>
          </div>
        </div>

      </div>

      <?php if ($c['status'] === 'rejected' && !empty($c['finder_note'])): ?>
      <div style="padding:10px 16px;background:var(--red-lt);border-top:.5px solid rgba(139,38,53,.15);font-size:12px;color:var(--red);">
        <i class="ti ti-message" style="font-size:12px;vertical-align:-1px;"></i>
        <strong>Your note:</strong> <?= htmlspecialchars($c['finder_note']) ?>
      </div>
      <?php endif; ?>

      <!-- Actions — only for pending claims -->
      <?php if ($c['status'] === 'pending'): ?>
      <div style="padding:12px 16px;border-top:.5px solid var(--border);background:var(--surface);display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;align-items:center;">

        <!-- Reject with optional note -->
        <details style="display:contents;">
          <summary style="list-style:none;cursor:pointer;">
            <span class="btn danger sm"><i class="ti ti-x"></i> Reject</span>
          </summary>
          <div style="grid-column:1/-1;width:100%;margin-top:8px;padding:12px;background:var(--red-lt);border-radius:var(--radius);border:.5px solid rgba(139,38,53,.15);">
            <form method="POST">
              <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
              <input type="hidden" name="action" value="reject">
              <label style="font-size:12px;color:var(--text3);display:block;margin-bottom:6px;">Optional note to claimant:</label>
              <textarea name="finder_note" rows="2" placeholder="e.g. The marks described don't match what I see on this pet."
                style="width:100%;padding:8px 10px;border:.5px solid var(--border);border-radius:var(--radius);font-size:12px;font-family:var(--font);color:var(--text);background:var(--surface);resize:vertical;margin-bottom:8px;"></textarea>
              <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="submit" class="btn danger sm"><i class="ti ti-x"></i> Confirm reject</button>
              </div>
            </form>
          </div>
        </details>

        <form method="POST" style="display:inline;">
          <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
          <input type="hidden" name="action" value="approve">
          <button type="submit" class="btn confirm sm"
                  onclick="return confirm('Approve this claim? The owner will be notified and can see your contact details. All other pending claims will be rejected.')">
            <i class="ti ti-circle-check"></i> Approve claim
          </button>
        </form>

      </div>
      <?php endif; ?>

    </div>
    <?php endforeach; endif; ?>

  </div>
  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>
</body>
</html>