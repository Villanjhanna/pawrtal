<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) { header("Location: ../auth/login.php"); exit(); }
$user_id = $_SESSION['user_id'];

$errors  = [];
$success = '';
$action  = $_GET['action'] ?? '';
$edit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── Handle DELETE ────────────────────────────────────────────────
if (isset($_POST['delete_pet'])) {
    $pid = (int)$_POST['pet_id'];
    $conn->query("DELETE FROM pets WHERE id=$pid AND user_id=$user_id");
    header("Location: my_pets.php?deleted=1"); exit();
}

// ── Handle ADD / EDIT ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_pet'])) {
    $pid         = (int)($_POST['pet_id']     ?? 0);
    $pet_name    = trim($_POST['pet_name']    ?? '');
    $pet_type    = trim($_POST['pet_type']    ?? '');
    $breed       = trim($_POST['breed']       ?? '');
    $color       = trim($_POST['color']       ?? '');
    $gender      = trim($_POST['gender']      ?? 'unknown');
    $age_years   = trim($_POST['age_years']   ?? '');
    $description = trim($_POST['description'] ?? '');
    $owner_phone = trim($_POST['owner_phone'] ?? '');

    if (!$pet_name) $errors[] = 'Pet name is required.';
    if (!$pet_type) $errors[] = 'Pet type is required.';
    if (!$color)    $errors[] = 'Color / markings is required.';
    if (!$owner_phone) $errors[] = 'Your contact number is required for the QR tag.';

    if ($age_years !== '' && (!is_numeric($age_years) || $age_years < 0)) {
        $errors[] = 'Age must be a positive number (e.g. 2 or 0.5).';
    }
    $age_years_val = $age_years !== '' ? (float)$age_years : null;

    // Photo upload
    $photo = $_POST['existing_photo'] ?? '';
    if (!empty($_FILES['photo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
            $errors[] = 'Photo must be JPG, PNG, or WebP.';
        } else {
            $photo = uniqid('pet_', true) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], "../uploads/pets/$photo");
        }
    }

    if (empty($errors)) {
        // Save phone number to users table so QR profile can show it
        $safe_phone = $conn->real_escape_string($owner_phone);
        $conn->query("UPDATE users SET phone='$safe_phone' WHERE id=$user_id");

        if ($pid > 0) {
            $stmt = $conn->prepare("
                UPDATE pets
SET name=?, type=?, breed=?, color=?, gender=?,
    age_years=?, description=?, photo=?
WHERE id=? AND user_id=?
            ");
            $stmt->bind_param(
    'sssssdssii',
    $pet_name, $pet_type, $breed, $color, $gender,
    $age_years_val, $description, $photo,
    $pid, $user_id
);
            $stmt->execute();
            $success = 'Pet updated successfully.';
            $action  = '';
        } else {
            $qr_token = bin2hex(random_bytes(32));
            $stmt = $conn->prepare("
                INSERT INTO pets
                    (user_id, name, type, breed, color, gender,
                     age_years, description, photo, qr_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'isssssdsss',
                $user_id, $pet_name, $pet_type, $breed, $color, $gender,
                $age_years_val, $description, $photo, $qr_token
            );
            $stmt->execute();
            $success = 'Pet registered successfully.';
            $action  = '';
        }
    } else {
        $action  = $pid > 0 ? 'edit' : 'add';
        $edit_id = $pid;
    }
}

// ── Load pet for editing ─────────────────────────────────────────
$editing = null;
if ($action === 'edit' && $edit_id > 0) {
    $editing = $conn->query("SELECT * FROM pets WHERE id=$edit_id AND user_id=$user_id")->fetch_assoc();
    if (!$editing) { header("Location: my_pets.php"); exit(); }
}

// ── Form field defaults ──────────────────────────────────────────
$f = [];
if (!empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $_POST;
} elseif ($editing) {
    $f = $editing;
    $f['pet_name'] = $editing['name'];
    $f['pet_type'] = $editing['type'];
} else {
    $f = [];
}

// Load current phone for the form default
$currentPhone = $conn->query("SELECT phone FROM users WHERE id=$user_id")->fetch_assoc()['phone'] ?? '';

$unreadCount = $conn->query("SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0")->fetch_assoc()['c'];
$myMatches   = $conn->query("SELECT COUNT(*) AS c FROM matches m JOIN lost_reports l ON m.lost_report_id=l.id WHERE l.user_id=$user_id AND m.status='pending'")->fetch_assoc()['c'];
$pets        = $conn->query("SELECT * FROM pets WHERE user_id=$user_id ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$name     = $_SESSION['name'];
$initials = pf_initials($name);

pf_head('Registered Pets');
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
    <a href="my_reports.php" class="sb-item"><i class="ti ti-clipboard-list"></i> My reports</a>
    <a href="matches.php"    class="sb-item">
      <i class="ti ti-link"></i> Match alerts
      <?php if ($myMatches > 0): ?><span class="sb-badge"><?= $myMatches ?></span><?php endif; ?>
    </a>
    <a href="my_pets.php"    class="sb-item active"><i class="ti ti-paw"></i> My pets</a>
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

  <?php if ($action === 'add' || $action === 'edit'): ?>
  <!-- ── ADD / EDIT FORM ──────────────────────────────────────── -->
  <div class="topbar">
    <div>
      <div class="topbar-title"><?= $action === 'edit' ? 'Edit pet' : 'Register a pet' ?></div>
      <div class="topbar-sub"><?= $action === 'edit' ? "Update your pet's details" : 'Add your pet to get a QR identification tag' ?></div>
    </div>
    <a href="my_pets.php" class="btn"><i class="ti ti-arrow-left"></i> Back to my pets</a>
  </div>

  <div class="content">

    <?php if (!empty($errors)): ?>
    <div style="background:var(--danger-bg);color:var(--danger-text);border:.5px solid rgba(153,27,27,.2);border-radius:var(--radius);display:flex;align-items:flex-start;gap:8px;padding:10px 14px;font-size:13px;">
      <i class="ti ti-alert-circle" style="flex-shrink:0;margin-top:1px;"></i>
      <div><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start;">

      <div class="card">
        <div class="card-header">
          <div class="card-title"><?= $action === 'edit' ? 'Pet details' : 'Tell us about your pet' ?></div>
        </div>

        <form method="POST" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:16px;">
          <input type="hidden" name="save_pet"       value="1">
          <input type="hidden" name="pet_id"         value="<?= $editing['id'] ?? 0 ?>">
          <input type="hidden" name="existing_photo" value="<?= htmlspecialchars($editing['photo'] ?? '') ?>">

          <!-- Name + Type -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label class="field-label">Pet name <span style="color:var(--red);">*</span></label>
              <input type="text" name="pet_name" placeholder="e.g. Brownie"
                     value="<?= htmlspecialchars($f['pet_name'] ?? '') ?>"
                     class="field-input">
            </div>
            <div>
              <label class="field-label">Pet type <span style="color:var(--red);">*</span></label>
              <select name="pet_type" class="field-input">
                <option value="">Select type…</option>
                <?php foreach (['dog','cat','bird','rabbit','other'] as $t): ?>
                <option value="<?= $t ?>" <?= ($f['pet_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Breed + Color -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label class="field-label">Breed</label>
              <input type="text" name="breed" placeholder="e.g. Aspin"
                     value="<?= htmlspecialchars($f['breed'] ?? '') ?>"
                     class="field-input">
            </div>
            <div>
              <label class="field-label">Color / markings <span style="color:var(--red);">*</span></label>
              <input type="text" name="color" placeholder="e.g. Brown with white chest"
                     value="<?= htmlspecialchars($f['color'] ?? '') ?>"
                     class="field-input">
            </div>
          </div>

          <!-- Gender + Age -->
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label class="field-label">Gender</label>
              <select name="gender" class="field-input">
                <option value="unknown" <?= ($f['gender'] ?? 'unknown') === 'unknown' ? 'selected' : '' ?>>Unknown</option>
                <option value="male"    <?= ($f['gender'] ?? '') === 'male'            ? 'selected' : '' ?>>Male</option>
                <option value="female"  <?= ($f['gender'] ?? '') === 'female'          ? 'selected' : '' ?>>Female</option>
              </select>
            </div>
            <div>
              <label class="field-label">Age (years)</label>
              <input type="number" name="age_years" placeholder="e.g. 2 or 0.5"
                     min="0" max="30" step="0.1"
                     value="<?= htmlspecialchars($f['age_years'] ?? '') ?>"
                     class="field-input">
            </div>
          </div>

          <!-- Description -->
          <div>
            <label class="field-label">Description</label>
            <textarea name="description" rows="3"
                      placeholder="Distinctive features, collar color, medical needs, microchip number, etc."
                      class="field-input" style="resize:vertical;"><?= htmlspecialchars($f['description'] ?? '') ?></textarea>
          </div>

          <!-- ── Owner contact for QR tag ───────────────────────── -->
          <div style="border-top:.5px solid var(--border);padding-top:14px;">
            <div style="font-size:12px;font-weight:600;color:var(--text2);margin-bottom:4px;text-transform:uppercase;letter-spacing:.06em;">Your contact for the QR tag</div>
            <div style="font-size:11px;color:var(--text3);margin-bottom:10px;">This is the number shown to anyone who scans your pet's QR tag. Your email will not be shown.</div>
            <label class="field-label">Contact number <span style="color:var(--red);">*</span></label>
            <input type="tel" name="owner_phone"
                   placeholder="e.g. 09XX XXX XXXX"
                   value="<?= htmlspecialchars($f['owner_phone'] ?? $currentPhone) ?>"
                   class="field-input">
          </div>

          <!-- Photo -->
          <div>
            <label class="field-label">Photo</label>
            <?php if (!empty($editing['photo'])): ?>
            <div style="margin-bottom:8px;display:flex;align-items:center;gap:10px;">
              <img src="../uploads/pets/<?= htmlspecialchars($editing['photo']) ?>" alt="Current photo"
                   style="width:72px;height:72px;border-radius:var(--radius);object-fit:cover;border:.5px solid var(--border);">
              <div style="font-size:11px;color:var(--text3);">Current photo.<br>Upload a new one to replace it.</div>
            </div>
            <?php endif; ?>
            <input type="file" name="photo" accept="image/*" style="font-size:13px;color:var(--text2);">
            <div style="font-size:11px;color:var(--text3);margin-top:4px;">JPG, PNG or WebP. Used on QR tag and for matching.</div>
          </div>

          <!-- Submit -->
          <div style="border-top:.5px solid var(--border);padding-top:14px;display:flex;gap:8px;">
            <button type="submit" class="btn success">
              <i class="ti ti-<?= $action === 'edit' ? 'device-floppy' : 'plus' ?>"></i>
              <?= $action === 'edit' ? 'Save changes' : 'Register pet' ?>
            </button>
            <a href="my_pets.php" class="btn">Cancel</a>
          </div>

        </form>
      </div>

      <!-- Sidebar hints -->
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="card">
          <div class="card-title" style="margin-bottom:10px;">Why register your pet?</div>
          <div style="display:flex;flex-direction:column;gap:9px;font-size:12px;color:var(--text2);">
            <div style="display:flex;gap:8px;"><i class="ti ti-qrcode" style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Generate a printable QR tag that links to your contact info when scanned.</div></div>
            <div style="display:flex;gap:8px;"><i class="ti ti-bolt"   style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Pre-filled details speed up posting a lost report if your pet goes missing.</div></div>
            <div style="display:flex;gap:8px;"><i class="ti ti-link"   style="color:var(--green);font-size:16px;flex-shrink:0;margin-top:1px;"></i><div>Registered pets get priority matching against found reports in your area.</div></div>
          </div>
        </div>
        <div class="card" style="background:var(--green-lt);border-color:rgba(45,106,79,.2);">
          <div style="font-size:12px;color:var(--green);font-weight:500;margin-bottom:5px;"><i class="ti ti-shield-check" style="font-size:14px;vertical-align:-1px;"></i> Privacy note</div>
          <div style="font-size:12px;color:var(--text2);">Only your first name and phone number appear on the public QR profile. Your email and full name stay private.</div>
        </div>
        <div class="card" style="background:var(--amber-lt);border-color:rgba(146,64,14,.2);">
          <div style="font-size:12px;color:var(--amber);font-weight:500;margin-bottom:5px;"><i class="ti ti-info-circle" style="font-size:14px;vertical-align:-1px;"></i> Tip</div>
          <div style="font-size:12px;color:var(--text2);">A clear, recent photo makes it much easier for the community to identify your pet if found.</div>
        </div>
      </div>

    </div>
  </div>

  <?php else: ?>
  <!-- ── PET LIST ──────────────────────────────────────────────── -->
  <div class="topbar">
    <div>
      <div class="topbar-title">Registered pets</div>
      <div class="topbar-sub">Your pets on file — register them for quick QR identification</div>
    </div>
    <a href="my_pets.php?action=add" class="btn success"><i class="ti ti-plus"></i> Add a pet</a>
  </div>

  <div class="content">

    <?php if (!empty($success)): ?>
    <div class="alert success"><i class="ti ti-circle-check"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert info"><i class="ti ti-trash"></i> Pet removed from your profile.</div>
    <?php endif; ?>

    <?php if (empty($pets)): ?>
    <div class="card">
      <div class="empty">
        <i class="ti ti-paw"></i>
        No pets registered yet. Add your pet to get a QR tag for quick identification.
        <div style="margin-top:12px;"><a href="my_pets.php?action=add" class="btn success sm"><i class="ti ti-plus"></i> Add your first pet</a></div>
      </div>
    </div>

    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px;">
      <?php foreach ($pets as $p): ?>
      <div style="background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;display:flex;flex-direction:column;">

        <?php if (!empty($p['photo'])): ?>
          <img src="../uploads/pets/<?= htmlspecialchars($p['photo']) ?>" alt="<?= htmlspecialchars($p['name']) ?>"
               style="width:100%;height:155px;object-fit:cover;background:var(--surface2);">
        <?php else: ?>
          <div style="width:100%;height:155px;background:var(--surface2);display:flex;align-items:center;justify-content:center;">
            <i class="ti ti-paw" style="font-size:3rem;color:var(--text3);"></i>
          </div>
        <?php endif; ?>

        <div style="padding:14px;flex:1;display:flex;flex-direction:column;">
          <div style="font-weight:600;font-size:14px;color:var(--text);margin-bottom:2px;"><?= htmlspecialchars($p['name']) ?></div>
          <div style="font-size:12px;color:var(--text3);margin-bottom:8px;">
            <?= ucfirst(htmlspecialchars($p['type'])) ?>
            <?= !empty($p['breed']) ? ' &middot; '.htmlspecialchars($p['breed']) : '' ?>
          </div>
          <div style="font-size:11px;color:var(--text3);display:flex;flex-direction:column;gap:3px;margin-bottom:12px;flex:1;">
            <?php if (!empty($p['color'])): ?>
            <div><i class="ti ti-color-swatch" style="font-size:12px;vertical-align:-1px;"></i> <?= htmlspecialchars($p['color']) ?></div>
            <?php endif; ?>
            <?php if (!empty($p['gender']) && $p['gender'] !== 'unknown'): ?>
            <div><i class="ti ti-gender-male-female" style="font-size:12px;vertical-align:-1px;"></i> <?= ucfirst($p['gender']) ?></div>
            <?php endif; ?>
            <?php if (!empty($p['age_years'])): ?>
            <div><i class="ti ti-clock" style="font-size:12px;vertical-align:-1px;"></i> <?= $p['age_years'] ?> <?= $p['age_years'] == 1 ? 'year' : 'years' ?> old</div>
            <?php endif; ?>
          </div>
          <div style="display:flex;gap:6px;">
            <a href="my_pets.php?action=edit&id=<?= $p['id'] ?>" class="btn sm" style="flex:1;justify-content:center;"><i class="ti ti-edit"></i> Edit</a>
            <a href="pet_qr.php?id=<?= $p['id'] ?>"              class="btn sm" style="flex:1;justify-content:center;"><i class="ti ti-qrcode"></i> QR</a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove <?= htmlspecialchars(addslashes($p['name'])) ?> from your profile?')">
              <input type="hidden" name="pet_id"    value="<?= $p['id'] ?>">
              <input type="hidden" name="delete_pet" value="1">
              <button type="submit" class="btn sm danger" style="padding:5px 8px;"><i class="ti ti-trash"></i></button>
            </form>
          </div>
        </div>

      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>

  <style>
    .field-label{font-size:12px;font-weight:500;color:var(--text2);display:block;margin-bottom:5px;}
    .field-input{width:100%;padding:8px 10px;border:.5px solid var(--border-md);border-radius:var(--radius);font-family:var(--font);font-size:13px;background:var(--surface);color:var(--text);outline:none;transition:border-color .1s;}
    .field-input:focus{border-color:var(--green);}
    .alert.success{background:var(--success-bg);color:var(--success-text);border:.5px solid #bbf7d0;border-radius:var(--radius);display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;}
    .alert.info{background:var(--amber-lt);color:var(--amber);border:.5px solid rgba(146,64,14,.2);border-radius:var(--radius);display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;}
  </style>

  <footer>&copy; <?= date('Y') ?> Pawrtal &middot; Community-based lost &amp; found pet recovery</footer>
</div>

</body>
</html>