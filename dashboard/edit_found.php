<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT *
    FROM found_reports
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();

$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    die("Report not found.");
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_POST = $report;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pet_type      = trim($_POST['pet_type'] ?? '');
    $breed         = trim($_POST['breed'] ?? '');
    $color         = trim($_POST['color'] ?? '');
    $gender        = trim($_POST['gender'] ?? '');
    $found_place   = trim($_POST['found_place'] ?? '');
    $found_date    = trim($_POST['found_date'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $contact_name  = trim($_POST['contact_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');

    if (!$pet_type)       $errors[] = 'Pet type is required.';
    if (!$found_place)    $errors[] = 'Found location is required.';
    if (!$found_date)     $errors[] = 'Found date is required.';
    if (!$contact_name)   $errors[] = 'Your name is required.';
    if (!$contact_phone)  $errors[] = 'Your contact number is required.';

    $photo = $report['photo'];

    if (!empty($_FILES['photo']['name'])) {

        $ext = strtolower(
            pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION)
        );

        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {

            $errors[] = 'Photo must be JPG, PNG, or WebP.';

        } else {

            $newPhoto = uniqid('fr_', true) . '.' . $ext;

            move_uploaded_file(
                $_FILES['photo']['tmp_name'],
                "../uploads/reports/$newPhoto"
            );

            $photo = $newPhoto;
        }
    }

    if (empty($errors)) {

        $stmt = $conn->prepare("
            UPDATE found_reports
            SET
                pet_type=?,
                breed=?,
                color=?,
                gender=?,
                found_place=?,
                found_date=?,
                description=?,
                contact_name=?,
                contact_phone=?,
                photo=?
            WHERE id=? AND user_id=?
        ");

        $stmt->bind_param(
            "ssssssssssii",
            $pet_type,
            $breed,
            $color,
            $gender,
            $found_place,
            $found_date,
            $description,
            $contact_name,
            $contact_phone,
            $photo,
            $id,
            $user_id
        );

        if ($stmt->execute()) {

            header("Location: my_reports.php?tab=found&updated=1");
            exit();

        } else {

            $errors[] = 'Failed to update report.';
        }
    }
}

$unreadCount = $conn->query("
    SELECT COUNT(*) AS c
    FROM notifications
    WHERE user_id=$user_id AND is_read=0
")->fetch_assoc()['c'];

$myMatches = $conn->query("
    SELECT COUNT(*) AS c
    FROM matches m
    JOIN lost_reports l ON m.lost_report_id=l.id
    WHERE l.user_id=$user_id
    AND m.status='pending'
")->fetch_assoc()['c'];

$name = $_SESSION['name'];
$initials = pf_initials($name);

pf_head('Edit Found Report');
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
      <div class="topbar-title">Report a found pet</div>
      <div class="topbar-sub">Help reunite a stray with their owner</div>
    </div>
    <a href="my_reports.php" class="btn"><i class="ti ti-arrow-left"></i> My reports</a>
  </div>

  <div class="content">

    <?php if ($success): ?>
    <div class="alert success">
      <i class="ti ti-circle-check"></i>
      Your found pet report has been posted and matched against active lost reports.
      <a href="my_reports.php?tab=found" style="margin-left:10px;font-weight:500;color:inherit;">View my reports &rarr;</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert" style="background:var(--danger-bg);color:var(--danger-text);border-color:rgba(153,27,27,.2);">
      <i class="ti ti-alert-circle"></i>
      <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start;">

      <div class="card">
        <div class="card-header"><div class="card-title">Found pet details</div></div>
        <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:16px;">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Pet type <span style="color:var(--red);">*</span></label>
              <select name="pet_type" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
                <option value="">Select type…</option>
                <?php foreach (['dog','cat','bird','rabbit','other'] as $t): ?>
                <option value="<?= $t ?>" <?= ($_POST['pet_type'] ?? '')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Breed (if known)</label>
              <input type="text" name="breed" placeholder="e.g. Aspin" value="<?= htmlspecialchars($_POST['breed'] ?? '') ?>" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Color / markings</label>
              <input type="text" name="color" placeholder="e.g. Black and white" value="<?= htmlspecialchars($_POST['color'] ?? '') ?>" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
            </div>
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Gender (if known)</label>
              <select name="gender" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
                <option value="">Unknown</option>
                <option value="male"   <?= ($_POST['gender'] ?? '')==='male'  ?'selected':'' ?>>Male</option>
                <option value="female" <?= ($_POST['gender'] ?? '')==='female'?'selected':'' ?>>Female</option>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Where found <span style="color:var(--red);">*</span></label>
              <input type="text" name="found_place" placeholder="e.g. Ayala Center, Cebu City" value="<?= htmlspecialchars($_POST['found_place'] ?? '') ?>" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
            </div>
            <div>
              <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Date found <span style="color:var(--red);">*</span></label>
              <input type="date" name="found_date" value="<?= htmlspecialchars($_POST['found_date'] ?? date('Y-m-d')) ?>" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
            </div>
          </div>

          <div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Description</label>
            <textarea name="description" rows="3" placeholder="Collar, tags, condition, behavior, etc." style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);resize:vertical;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
          </div>

          <!-- Contact details -->
          <div style="border-top:.5px solid var(--border);padding-top:14px;">
            <div style="font-size:12px;font-weight:600;color:var(--text2);margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em;">Your contact details</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div>
                <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Your name <span style="color:var(--red);">*</span></label>
                <input type="text" name="contact_name" value="<?= htmlspecialchars($_POST['contact_name'] ?? $name) ?>" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
              </div>
              <div>
                <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Phone number <span style="color:var(--red);">*</span></label>
                <input type="tel" name="contact_phone" placeholder="e.g. 09XX XXX XXXX" value="<?= htmlspecialchars($_POST['contact_phone'] ?? '') ?>" style="width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);">
              </div>
            </div>
          </div>

          <div>
            <label style="font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;">Photo</label>
            <input type="file" name="photo" accept="image/*" style="font-size:13px;color:var(--text2);">
            <div style="font-size:11px;color:var(--text3);margin-top:4px;">JPG, PNG or WebP. A clear photo greatly improves matching.</div>
          </div>

          <div style="border-top:.5px solid var(--border);padding-top:14px;display:flex;gap:8px;">
            <button type="submit" class="btn success"><i class="ti ti-send"></i> Edit found report</button>
            <a href="my_reports.php" class="btn">Cancel</a>
          </div>

        </form>
      </div>

      <!-- Sidebar tips -->
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="card">
          <div class="card-title" style="margin-bottom:10px;">How this works</div>
          <div style="display:flex;flex-direction:column;gap:8px;font-size:12px;color:var(--text2);">
            <div style="display:flex;gap:8px;"><i class="ti ti-number-1" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>You submit the found pet report with a photo and your contact details.</div></div>
            <div style="display:flex;gap:8px;"><i class="ti ti-number-2" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Our system automatically compares it against active lost pet reports.</div></div>
            <div style="display:flex;gap:8px;"><i class="ti ti-number-3" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>If a match is found, the owner gets alerted with your contact details.</div></div>
          </div>
        </div>
        <div class="card" style="background:var(--green-lt);border-color:rgba(45,106,79,.2);">
          <div style="font-size:12px;color:var(--green);font-weight:500;margin-bottom:6px;"><i class="ti ti-shield-check" style="font-size:14px;vertical-align:-1px;"></i> Your contact is protected</div>
          <div style="font-size:12px;color:var(--text2);">Your phone number is only revealed to the pet owner after they confirm the match &mdash; not publicly visible.</div>
        </div>
      </div>

    </div>

  </div>

  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

</body>
</html>
