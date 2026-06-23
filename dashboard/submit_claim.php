<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = (int)$_SESSION['user_id'];

$found_id = isset($_GET['found_id']) ? (int)$_GET['found_id'] : 0;
if (!$found_id) { header("Location: ../public/lost.php"); exit(); }

// Load the found report — must be active
$found = $conn->query("
    SELECT f.*, u.name AS finder_name, u.id AS finder_user_id
    FROM found_reports f
    JOIN users u ON f.user_id = u.id
    WHERE f.id = $found_id AND f.status = 'active'
    LIMIT 1
")->fetch_assoc();
if (!$found) { header("Location: ../public/lost.php"); exit(); }

// Can't claim your own found report
if ((int)$found['finder_user_id'] === $user_id) {
    header("Location: ../public/view_found.php?id=$found_id"); exit();
}

// Load claimant's registered pets
$my_pets = $conn->query("
    SELECT id, name, type, breed, color, photo FROM pets
    WHERE user_id = $user_id
    ORDER BY name ASC
")->fetch_all(MYSQLI_ASSOC);

$error   = '';
$success = false;

// ── Handle submission ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pet_id      = (int)($_POST['pet_id'] ?? 0);
    $mark_desc   = trim($_POST['mark_description'] ?? '');

    // Validate pet belongs to claimant
    $pet = $conn->query("
        SELECT * FROM pets WHERE id = $pet_id AND user_id = $user_id LIMIT 1
    ")->fetch_assoc();

    if (!$pet) {
        $error = 'Please select one of your registered pets.';
    } elseif (strlen($mark_desc) < 10) {
        $error = 'Please describe a distinguishing mark (at least 10 characters).';
    } else {

        // Check for existing claim from this user on this found report
        $existing = $conn->query("
            SELECT id, status FROM claims
            WHERE found_report_id = $found_id AND claimant_id = $user_id
            LIMIT 1
        ")->fetch_assoc();

        if ($existing && $existing['status'] === 'pending') {
            $error = 'You already have a pending claim on this found report.';
        } elseif ($existing && $existing['status'] === 'approved') {
            $error = 'Your claim on this report has already been approved.';
        } else {
            // Handle proof photo upload
            $proof_photo = null;
            if (!empty($_FILES['proof_photo']['name'])) {
                $ext      = strtolower(pathinfo($_FILES['proof_photo']['name'], PATHINFO_EXTENSION));
                $allowed  = ['jpg','jpeg','png','webp'];
                if (!in_array($ext, $allowed)) {
                    $error = 'Proof photo must be JPG, PNG, or WEBP.';
                } elseif ($_FILES['proof_photo']['size'] > 5 * 1024 * 1024) {
                    $error = 'Proof photo must be under 5 MB.';
                } else {
                    $filename    = 'claim_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = '../uploads/claims/' . $filename;
                    if (!is_dir('../uploads/claims')) mkdir('../uploads/claims', 0755, true);
                    if (move_uploaded_file($_FILES['proof_photo']['tmp_name'], $upload_path)) {
                        $proof_photo = $filename;
                    } else {
                        $error = 'Failed to upload proof photo. Please try again.';
                    }
                }
            }

            if (!$error) {
                $mark_esc  = $conn->real_escape_string($mark_desc);
                $photo_esc = $proof_photo ? $conn->real_escape_string($proof_photo) : 'NULL';
                $photo_val = $proof_photo ? "'$photo_esc'" : 'NULL';

                if ($existing && $existing['status'] === 'rejected') {
                    // Allow resubmission after rejection
                    $claim_id = (int)$existing['id'];
                    $conn->query("
                        UPDATE claims
                        SET pet_id=$pet_id,
                            mark_description='$mark_esc',
                            proof_photo=$photo_val,
                            status='pending',
                            finder_note=NULL
                        WHERE id=$claim_id
                    ");
                } else {
                    $conn->query("
                        INSERT INTO claims (found_report_id, claimant_id, pet_id, mark_description, proof_photo)
                        VALUES ($found_id, $user_id, $pet_id, '$mark_esc', $photo_val)
                    ");
                    $claim_id = (int)$conn->insert_id;
                }

                // Notify finder
                $pet_name    = $conn->real_escape_string($pet['name']);
                $finder_id   = (int)$found['finder_user_id'];
                $claimant    = $conn->real_escape_string($_SESSION['name']);
                $notify_msg  = $conn->real_escape_string(
                    "$claimant has submitted a claim on your found report, "
                  . "saying \"$pet_name\" is their missing pet. "
                  . "Review their proof and approve or reject the claim."
                );
                $conn->query("
                    INSERT INTO notifications (user_id, type, message, link)
                    VALUES ($finder_id, 'claim_submitted',
                            '$notify_msg',
                            '../dashboard/review_claims.php?found_id=$found_id')
                ");

                $success = true;
            }
        }
    }
}

$myMatches   = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];
$unreadCount = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$name        = $_SESSION['name'];
$initials    = pf_initials($name);

pf_head('Claim Found Pet');
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
      <div class="topbar-title">Claim Found Pet</div>
      <div class="topbar-sub">Submit proof that this found pet belongs to you</div>
    </div>
    <a href="../public/view_found.php?id=<?= $found_id ?>" style="display:flex;align-items:center;gap:5px;font-size:13px;color:var(--text3);text-decoration:none;">
      <i class="ti ti-arrow-left" style="font-size:15px;"></i> Back to report
    </a>
  </div>

  <div class="content">

    <?php if ($success): ?>
    <!-- ── Success state ── -->
    <div style="background:var(--surface);border:.5px solid rgba(45,106,79,.3);border-left:3px solid var(--green);border-radius:var(--radius-lg);padding:28px 24px;text-align:center;max-width:520px;margin:0 auto;">
      <div style="width:56px;height:56px;border-radius:50%;background:var(--green-lt);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
        <i class="ti ti-circle-check" style="font-size:1.8rem;color:var(--green);"></i>
      </div>
      <div style="font-family:var(--display);font-size:1.15rem;color:var(--text);margin-bottom:8px;">Claim submitted</div>
      <div style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:20px;">
        The finder has been notified and will review your proof. You'll receive a notification once they approve or reject your claim.
      </div>
      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
        <a href="matches.php" class="btn sm"><i class="ti ti-link"></i> View match alerts</a>
        <a href="../public/lost.php" class="btn sm"><i class="ti ti-search"></i> Browse reports</a>
      </div>
    </div>

    <?php else: ?>

    <?php if (empty($my_pets)): ?>
    <!-- ── No pets registered ── -->
    <div style="background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:28px 24px;text-align:center;max-width:520px;margin:0 auto;">
      <i class="ti ti-paw-off" style="font-size:2.5rem;color:var(--text3);display:block;margin-bottom:12px;"></i>
      <div style="font-family:var(--display);font-size:1rem;color:var(--text);margin-bottom:8px;">No registered pets</div>
      <div style="font-size:13px;color:var(--text3);line-height:1.6;margin-bottom:20px;">
        You need at least one registered pet profile to submit a claim. This helps the finder verify your ownership.
      </div>
      <a href="my_pets.php" class="btn sm"><i class="ti ti-plus"></i> Register a pet</a>
    </div>

    <?php else: ?>
    <!-- ── Claim form ── -->

    <?php if ($error): ?>
    <div class="alert danger" style="margin-bottom:16px;"><i class="ti ti-alert-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Found report summary -->
    <div style="background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:14px 16px;margin-bottom:16px;display:flex;gap:14px;align-items:center;">
      <?php if (!empty($found['photo'])): ?>
        <img src="../uploads/reports/<?= htmlspecialchars($found['photo']) ?>"
             style="width:72px;height:72px;object-fit:cover;border-radius:var(--radius);flex-shrink:0;" alt="Found pet">
      <?php else: ?>
        <div style="width:72px;height:72px;border-radius:var(--radius);background:var(--surface2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="ti ti-paw" style="font-size:1.8rem;color:var(--text3);"></i>
        </div>
      <?php endif; ?>
      <div>
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:3px;">Claiming this found report</div>
        <div style="font-size:14px;font-weight:600;color:var(--text);">
          <?= ucfirst(htmlspecialchars($found['pet_type'])) ?>
          <?= !empty($found['breed']) ? '· '.htmlspecialchars($found['breed']) : '' ?>
        </div>
        <div style="font-size:12px;color:var(--text3);margin-top:2px;display:flex;align-items:center;gap:4px;">
          <i class="ti ti-map-pin" style="font-size:12px;"></i>
          <?= htmlspecialchars($found['found_place']) ?>
        </div>
        <div style="font-size:12px;color:var(--text3);margin-top:2px;display:flex;align-items:center;gap:4px;">
          <i class="ti ti-user" style="font-size:12px;"></i>
          Found by <?= htmlspecialchars($found['finder_name']) ?>
        </div>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px;">

      <!-- Step 1: Select pet -->
      <div style="background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:16px 18px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:10px;font-weight:600;">
          Step 1 — Which of your pets is this?
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;" id="petList">
          <?php foreach ($my_pets as $p): ?>
          <label style="display:flex;align-items:center;gap:12px;padding:10px 12px;border:.5px solid var(--border);border-radius:var(--radius);cursor:pointer;transition:border-color .12s;" class="pet-option">
            <input type="radio" name="pet_id" value="<?= $p['id'] ?>" style="accent-color:var(--green);width:16px;height:16px;flex-shrink:0;" required>
            <?php if (!empty($p['photo'])): ?>
              <img src="../uploads/pets/<?= htmlspecialchars($p['photo']) ?>"
                   style="width:44px;height:44px;object-fit:cover;border-radius:50%;flex-shrink:0;" alt="<?= htmlspecialchars($p['name']) ?>">
            <?php else: ?>
              <div style="width:44px;height:44px;border-radius:50%;background:var(--surface2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="ti ti-paw" style="font-size:1.2rem;color:var(--text3);"></i>
              </div>
            <?php endif; ?>
            <div>
              <div style="font-size:14px;font-weight:600;color:var(--text);"><?= htmlspecialchars($p['name']) ?></div>
              <div style="font-size:11px;color:var(--text3);">
                <?= ucfirst(htmlspecialchars($p['type'])) ?>
                <?= !empty($p['breed']) ? '· '.htmlspecialchars($p['breed']) : '' ?>
                <?= !empty($p['color']) ? '· '.htmlspecialchars($p['color']) : '' ?>
              </div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
        <a href="my_pets.php" style="font-size:12px;color:var(--green);text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-top:10px;">
          <i class="ti ti-plus" style="font-size:12px;"></i> Register another pet
        </a>
      </div>

      <!-- Step 2: Distinguishing mark -->
      <div style="background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:16px 18px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:4px;font-weight:600;">
          Step 2 — Describe a distinguishing mark
        </div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:10px;line-height:1.5;">
          Something only the true owner would know — a scar, birthmark, unique fur pattern, microchip location, collar tag, missing tooth, etc.
        </div>
        <textarea name="mark_description" rows="3" required minlength="10"
          placeholder="e.g. Small scar above left eye from a fence injury last year. White patch on chest shaped like a heart. Missing tip of right ear."
          style="width:100%;padding:10px 12px;border:.5px solid var(--border);border-radius:var(--radius);font-size:13px;font-family:var(--font);color:var(--text);background:var(--surface);resize:vertical;line-height:1.5;"><?= htmlspecialchars($_POST['mark_description'] ?? '') ?></textarea>
      </div>

      <!-- Step 3: Proof photo (optional) -->
      <div style="background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:16px 18px;">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text3);margin-bottom:4px;font-weight:600;">
          Step 3 — Upload a proof photo <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional but recommended)</span>
        </div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:10px;line-height:1.5;">
          A recent photo of your pet clearly showing the distinguishing mark you described above.
        </div>
        <input type="file" name="proof_photo" accept="image/jpeg,image/png,image/webp"
               style="font-size:13px;color:var(--text);">
        <div style="font-size:11px;color:var(--text3);margin-top:6px;">Max 5 MB · JPG, PNG, or WEBP</div>
      </div>

      <!-- Submit -->
      <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
        <a href="../public/view_found.php?id=<?= $found_id ?>" class="btn sm">Cancel</a>
        <button type="submit" class="btn confirm sm">
          <i class="ti ti-send"></i> Submit claim
        </button>
      </div>

    </form>
    <?php endif; ?>
    <?php endif; ?>

  </div>
  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

<style>
.pet-option:has(input:checked) {
  border-color: var(--green);
  background: var(--green-lt);
}
</style>

</body>
</html>