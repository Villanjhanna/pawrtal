<?php
session_start();
include '../config/db.php';
include '_shared.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$id   = (int)($_GET['id'] ?? 0);
$type = $_GET['type'] ?? 'lost';

if (!$id || !in_array($type, ['lost', 'found'])) {
    die('Invalid request.');
}

$table = $type === 'lost' ? 'lost_reports' : 'found_reports';

$stmt = $conn->prepare("
    SELECT *
    FROM $table
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param('ii', $id, $user_id);
$stmt->execute();

$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    die('Report not found.');
}

$unreadCount = $conn->query("
    SELECT COUNT(*) AS c FROM notifications WHERE user_id=$user_id AND is_read=0
")->fetch_assoc()['c'];

$name     = $_SESSION['name'];
$initials = pf_initials($name);

pf_head('View Report');
?>
<body>

<aside class="sb">
  <div class="sb-brand">
    <div class="sb-logo"><i class="ti ti-paw"></i></div>
    <div>
      <div class="sb-appname">Pawrtal</div>
      <div class="sb-appsub">Dashboard</div>
    </div>
  </div>
  <nav class="sb-nav">
    <div class="sb-sec">Overview</div>
    <a href="index.php"         class="sb-item active"><i class="ti ti-home"></i> Dashboard</a>

    <div class="sb-sec">Reports</div>
    <a href="report_lost.php"   class="sb-item"><i class="ti ti-alert-circle"></i> Report lost pet</a>
    <a href="report_found.php"  class="sb-item"><i class="ti ti-circle-check"></i> Report found pet</a>
    <a href="my_reports.php"    class="sb-item"><i class="ti ti-clipboard-list"></i> My reports</a>
    <a href="matches.php"       class="sb-item">
      <i class="ti ti-link"></i> Match alerts
      
    </a>

    <div class="sb-sec">My Pets</div>
    <a href="my_pets.php"       class="sb-item"><i class="ti ti-paw"></i> Registered pets</a>

    <div class="sb-sec">Community</div>
    <a href="../public/lost.php"  class="sb-item"><i class="ti ti-search"></i> Browse lost pets</a>
    <a href="../public/found.php" class="sb-item"><i class="ti ti-search"></i> Browse found pets</a>

    <div class="sb-sec">Account</div>
    <a href="notifications.php" class="sb-item">
      <i class="ti ti-bell"></i> Notifications
      <?php if ($unreadCount > 0): ?><span class="sb-badge"><?= $unreadCount ?></span><?php endif; ?>
    </a>
    <a href="../auth/logout.php"  class="sb-item"><i class="ti ti-logout"></i> Logout</a>
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
            <div class="topbar-title">
                <?= $type === 'lost' ? 'Lost Pet Report' : 'Found Pet Report' ?>
            </div>
            <div class="topbar-sub">
                Report #<?= $report['id'] ?>
            </div>
        </div>

        <a href="my_reports.php?tab=<?= $type ?>" class="btn">
            <i class="ti ti-arrow-left"></i>
            Back
        </a>
    </div>

    <div class="content">

    <!-- Hero -->
    <div style="
        background:linear-gradient(135deg,var(--green),var(--green-dk));
        border-radius:var(--radius-lg);
        padding:24px;
        color:#fff;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:20px;
    ">

        <div>
            <div style="font-size:12px;opacity:.8;text-transform:uppercase;letter-spacing:.08em;">
                <?= $type === 'lost' ? 'Lost Pet Report' : 'Found Pet Report' ?>
            </div>

            <div style="
                font-family:var(--display);
                font-size:2rem;
                margin-top:4px;
            ">
                <?= $type === 'lost'
                    ? htmlspecialchars($report['pet_name'])
                    : ucfirst(htmlspecialchars($report['pet_type'])) ?>
            </div>

            <div style="font-size:13px;opacity:.9;margin-top:6px;">
                Report #<?= $report['id'] ?>
            </div>
        </div>

        <?php
        $status = $report['status'] ?? 'active';

        $badgeColor = match($status){
            'active' => '#16a34a',
            'matched' => '#d97706',
            'reunited' => '#2563eb',
            'claimed' => '#2563eb',
            default => '#6b7280'
        };
        ?>

        <div style="
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.2);
            padding:10px 18px;
            border-radius:999px;
            font-size:13px;
            font-weight:600;
        ">
            <?= ucfirst($status) ?>
        </div>

    </div>

    <!-- Photo + Details -->
    <div style="
        display:grid;
        grid-template-columns:320px 1fr;
        gap:18px;
    ">

        <!-- Photo -->
        <div class="card">

            <?php if (!empty($report['photo'])): ?>

                <img
                    src="../uploads/reports/<?= htmlspecialchars($report['photo']) ?>"
                    alt=""
                    style="
                        width:100%;
                        height:320px;
                        object-fit:cover;
                        border-radius:12px;
                    ">

            <?php else: ?>

                <div style="
                    height:320px;
                    background:var(--surface2);
                    border-radius:12px;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                ">
                    <i class="ti ti-paw" style="font-size:4rem;color:var(--text3);"></i>
                </div>

            <?php endif; ?>

        </div>

        <!-- Details -->
        <div class="card">

            <div class="card-header">
                <div>
                    <div class="card-title">Pet Information</div>
                    <div class="card-sub">Report details</div>
                </div>
            </div>

            <div style="
                display:grid;
                grid-template-columns:repeat(2,1fr);
                gap:14px;
            ">

                <?php if ($type === 'lost'): ?>

                    <div><strong>Pet Name</strong><br><?= htmlspecialchars($report['pet_name']) ?></div>

                    <div><strong>Pet Type</strong><br><?= htmlspecialchars($report['pet_type']) ?></div>

                    <div><strong>Breed</strong><br><?= htmlspecialchars($report['breed'] ?: 'Unknown') ?></div>

                    <div><strong>Color</strong><br><?= htmlspecialchars($report['color']) ?></div>

                    <div><strong>Gender</strong><br><?= htmlspecialchars($report['gender']) ?></div>

                    <div><strong>Last Seen</strong><br><?= date('M j, Y', strtotime($report['last_seen_date'])) ?></div>

                <?php else: ?>

                    <div><strong>Pet Type</strong><br><?= htmlspecialchars($report['pet_type']) ?></div>

                    <div><strong>Breed</strong><br><?= htmlspecialchars($report['breed'] ?: 'Unknown') ?></div>

                    <div><strong>Color</strong><br><?= htmlspecialchars($report['color']) ?></div>

                    <div><strong>Gender</strong><br><?= htmlspecialchars($report['gender']) ?></div>

                    <div><strong>Found Date</strong><br><?= date('M j, Y', strtotime($report['found_date'])) ?></div>

                    <div><strong>Contact Name</strong><br><?= htmlspecialchars($report['contact_name']) ?></div>

                <?php endif; ?>

            </div>

        </div>

    </div>

    <!-- Location -->
    <div class="card">

        <div class="card-header">
            <div>
                <div class="card-title">
                    <i class="ti ti-map-pin"></i>
                    Location Information
                </div>
            </div>
        </div>

        <?php if ($type === 'lost'): ?>

            <div style="font-size:14px;">
                <?= htmlspecialchars($report['last_seen_place']) ?>
            </div>

        <?php else: ?>

            <div style="font-size:14px;">
                <?= htmlspecialchars($report['found_place']) ?>
            </div>

        <?php endif; ?>

    </div>

    <!-- Contact -->
    <?php if ($type === 'found'): ?>

    <div class="card">

        <div class="card-header">
            <div>
                <div class="card-title">
                    <i class="ti ti-phone"></i>
                    Finder Contact Information
                </div>
            </div>
        </div>

        <div style="
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:16px;
        ">
            <div>
                <strong>Name</strong><br>
                <?= htmlspecialchars($report['contact_name']) ?>
            </div>

            <div>
                <strong>Phone Number</strong><br>
                <?= htmlspecialchars($report['contact_phone']) ?>
            </div>
        </div>

    </div>

    <?php endif; ?>

    <!-- Description -->
    <div class="card">

        <div class="card-header">
            <div>
                <div class="card-title">
                    <i class="ti ti-file-description"></i>
                    Description
                </div>
            </div>
        </div>

        <div style="
            color:var(--text2);
            line-height:1.8;
            white-space:pre-line;
        ">
            <?= nl2br(htmlspecialchars($report['description'] ?: 'No description provided.')) ?>
        </div>

    </div>


</div>

</div>

</body>
</html>