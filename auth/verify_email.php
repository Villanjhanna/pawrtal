<?php
// auth/verify_email.php
session_start();
include '../config/db.php';

$token  = trim($_GET['token'] ?? '');
$resend = isset($_GET['resend']) && isset($_SESSION['user_id']);
$error  = '';

// ── Resend ────────────────────────────────────────────────────────
if ($resend) {
    $uid  = (int)$_SESSION['user_id'];
    $user = $conn->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
    if ($user && !$user['email_verified']) {
        sendVerificationEmail($conn, $user);
    }
    header("Location: verify_email.php?sent=1"); exit();
}

// ── Process token ─────────────────────────────────────────────────
if ($token) {
    $safe = $conn->real_escape_string($token);
    $user = $conn->query("
        SELECT * FROM users
        WHERE verify_token='$safe' AND verify_token_expires > NOW() AND email_verified=0
        LIMIT 1
    ")->fetch_assoc();

    if ($user) {
        $conn->query("UPDATE users SET email_verified=1, verify_token=NULL, verify_token_expires=NULL WHERE id={$user['id']}");
        $_SESSION['user_id']        = $user['id'];
        $_SESSION['name']           = $user['name'];
        $_SESSION['email']          = $user['email'];
        $_SESSION['email_verified'] = 1;
        header("Location: ../dashboard/index.php?verified=1"); exit();
    } else {
        $error = 'This verification link is invalid or has expired.';
    }
}

function sendVerificationEmail(mysqli $conn, array $user): void {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $uid     = (int)$user['id'];
    $safe    = $conn->real_escape_string($token);
    $conn->query("UPDATE users SET verify_token='$safe', verify_token_expires='$expires' WHERE id=$uid");
    $base    = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $link    = $base . '/auth/verify_email.php?token=' . urlencode($token);
    $name    = $user['name'];
    $subject = 'Verify your Pawrtal account';
    $body    = "Hi $name,\n\nClick the link below to verify your email:\n\n$link\n\nExpires in 24 hours. If you didn't sign up for Pawrtal, ignore this email.\n\n— Pawrtal";
    $headers = "From: no-reply@pawrtal.com\r\nContent-Type: text/plain; charset=UTF-8";
    mail($user['email'], $subject, $body, $headers);
}
?>
<!DOCTYPE html><html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify your email — Pawrtal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{background:#f7f5f2;font-family:'DM Sans',system-ui,sans-serif;font-size:14px;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.card{background:#fff;border-radius:14px;padding:36px 32px;max-width:400px;width:100%;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.07);}
.icon{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;}
.im{background:#f0fdf4;color:#2d6a4f;} .ie{background:#fef2f2;color:#8b2635;}
.title{font-family:'DM Serif Display',serif;font-size:1.4rem;color:#1a1208;margin-bottom:8px;}
.sub{font-size:13px;color:#8a7060;line-height:1.7;margin-bottom:22px;}
.sub strong{color:#1a1208;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:11px;border-radius:8px;border:none;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;cursor:pointer;text-decoration:none;transition:opacity .12s;}
.btn:hover{opacity:.88;}
.bg{background:#2d6a4f;color:#fff;} .bo{background:#f2efeb;color:#44352a;margin-top:8px;}
hr{border:none;border-top:.5px solid #e5e7eb;margin:20px 0;}
.note{font-size:11px;color:#8a7060;line-height:1.6;}
.alert-s{background:#f0fdf4;color:#2d6a4f;border:.5px solid rgba(45,106,79,.2);padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
</style>
</head>
<body><div class="card">
<?php if ($error): ?>
  <div class="icon ie"><i class="ti ti-alert-circle"></i></div>
  <div class="title">Link expired</div>
  <div class="sub"><?= htmlspecialchars($error) ?><?= isset($_SESSION['user_id']) ? ' Request a new link below.' : '' ?></div>
  <?php if (isset($_SESSION['user_id'])): ?>
  <a href="verify_email.php?resend=1" class="btn bg"><i class="ti ti-send"></i> Resend verification email</a>
  <?php endif; ?>
  <a href="../public/index.php" class="btn bo" style="display:inline-flex;"><i class="ti ti-home"></i> Go to homepage</a>
<?php elseif (isset($_GET['sent'])): ?>
  <div class="alert-s"><i class="ti ti-circle-check"></i> New verification email sent.</div>
  <div class="icon im"><i class="ti ti-mail"></i></div>
  <div class="title">Check your inbox</div>
  <div class="sub">We sent a fresh verification link to <strong><?= htmlspecialchars($_SESSION['email'] ?? 'your email') ?></strong>.</div>
  <hr><div class="note">Didn't receive it? Check spam, or <a href="verify_email.php?resend=1" style="color:#2d6a4f;font-weight:500;">resend again</a>.</div>
<?php else: ?>
  <div class="icon im"><i class="ti ti-mail"></i></div>
  <div class="title">Verify your email</div>
  <div class="sub">We sent a link to <strong><?= htmlspecialchars($_SESSION['email'] ?? 'your email') ?></strong>. Click it to activate your account.</div>
  <?php if (isset($_SESSION['user_id'])): ?>
  <a href="verify_email.php?resend=1" class="btn bg"><i class="ti ti-refresh"></i> Resend verification email</a>
  <?php endif; ?>
  <a href="../auth/login.php" class="btn bo" style="display:inline-flex;"><i class="ti ti-login"></i> Back to sign in</a>
  <hr><div class="note">Link expires in 24 hours. Check your spam folder if you don't see it.</div>
<?php endif; ?>
</div></body></html>