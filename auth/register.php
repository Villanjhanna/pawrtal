<?php
// auth/register.php — with email verification trigger
include '../config/db.php';

$message   = '';
$formName  = $_POST['name']  ?? '';
$formEmail = $_POST['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (strlen($name) < 2)                         { $message = 'Name must be at least 2 characters.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $message = 'Please enter a valid email address.'; }
    elseif (strlen($password) < 8)                 { $message = 'Password must be at least 8 characters.'; }
    elseif ($password !== $confirm)                 { $message = 'Passwords do not match.'; }
    else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param('s', $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $message = 'An account with that email already exists.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $token  = bin2hex(random_bytes(32));
            $exp    = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $stmt = $conn->prepare("
    INSERT INTO users (name, email, password, email_verified)
    VALUES (?, ?, ?, 1)
");
$stmt->bind_param('sss', $name, $email, $hashed);

if ($stmt->execute()) {
    $uid = $conn->insert_id;

    /*
    ==========================================================
    EMAIL VERIFICATION TEMPORARILY DISABLED
    ==========================================================

    $base    = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    $link    = $base . '/auth/verify_email.php?token=' . urlencode($token);

    $subject = 'Verify your Pawrtal account';

    $body = "Hi $name,\n\nWelcome to Pawrtal! Click the link below to verify your email:\n\n$link\n\nExpires in 24 hours.\n\n— Pawrtal";

    $headers = "From: no-reply@pawrtal.com\r\nContent-Type: text/plain; charset=UTF-8";

    mail($email, $subject, $body, $headers);
    ==========================================================
    */

    session_start();
    $_SESSION['user_id'] = $uid;
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    $_SESSION['email_verified'] = 1;

    header("Location: ../dashboard/index.php");
    exit();
}else {
                $message = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html><html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create account — Pawrtal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f7f5f2;--surface:#fff;--surface2:#f2efeb;--border:rgba(0,0,0,.08);--border-md:rgba(0,0,0,.14);--green:#2d6a4f;--green-dk:#1b4332;--green-lt:#f0fdf4;--red:#8b2635;--red-lt:#fef2f2;--text:#1a1208;--text2:#44352a;--text3:#8a7060;--radius:8px;--font:'DM Sans',system-ui,sans-serif;--display:'DM Serif Display',Georgia,serif;}
body{background:var(--bg);font-family:var(--font);font-size:14px;line-height:1.6;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.shell{display:flex;width:100%;max-width:860px;background:var(--surface);border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.07);}
.panel{width:300px;flex-shrink:0;background:var(--green);padding:44px 36px;display:flex;flex-direction:column;position:relative;overflow:hidden;}
.panel::before{content:'';position:absolute;top:-80px;right:-80px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.05);}
.panel::after{content:'';position:absolute;bottom:-60px;left:-40px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.04);}
.panel-logo{display:flex;align-items:center;gap:10px;position:relative;z-index:1;margin-bottom:auto;}
.panel-logo-icon{width:34px;height:34px;border-radius:9px;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;}
.panel-logo-icon i{color:#fff;font-size:18px;}
.panel-logo-name{font-family:var(--display);font-size:1.05rem;color:#fff;line-height:1.1;}
.panel-logo-sub{font-size:10px;color:rgba(255,255,255,.6);}
.panel-body{position:relative;z-index:1;margin-top:auto;}
.panel-headline{font-family:var(--display);font-size:1.35rem;color:#fff;line-height:1.25;margin-bottom:8px;}
.panel-desc{font-size:12px;color:rgba(255,255,255,.65);line-height:1.7;margin-bottom:24px;}
.panel-steps{display:flex;flex-direction:column;gap:14px;}
.panel-step{display:flex;align-items:flex-start;gap:10px;}
.step-num{width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#fff;flex-shrink:0;margin-top:2px;}
.step-text{font-size:12px;color:rgba(255,255,255,.7);line-height:1.5;}
.step-text strong{color:#fff;font-weight:500;}
.form-wrap{flex:1;padding:40px 44px;display:flex;flex-direction:column;justify-content:center;overflow-y:auto;}
.form-eyebrow{font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:var(--green);font-weight:600;margin-bottom:6px;}
.form-title{font-family:var(--display);font-size:1.5rem;color:var(--text);margin-bottom:4px;}
.form-sub{font-size:13px;color:var(--text3);margin-bottom:22px;}
.field{display:flex;flex-direction:column;gap:5px;margin-bottom:13px;}
.field-label{font-size:12px;font-weight:500;color:var(--text2);display:flex;justify-content:space-between;align-items:center;}
.field-wrap{position:relative;}
.field-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;}
.field-icon i{font-size:15px;color:var(--text3);}
.field-input{width:100%;padding:9px 36px 9px 34px;border-radius:var(--radius);border:.5px solid var(--border-md);background:var(--surface);color:var(--text);font-size:13px;font-family:var(--font);outline:none;transition:border-color .12s,box-shadow .12s;}
.field-input:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(45,106,79,.1);}
.toggle-btn{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;padding:2px;display:flex;align-items:center;color:var(--text3);}
.toggle-btn i{font-size:15px;}
.error-box{display:flex;align-items:center;gap:8px;padding:10px 13px;border-radius:var(--radius);background:var(--red-lt);color:var(--red);border:.5px solid rgba(139,38,53,.2);font-size:12px;margin-bottom:16px;}
.pw-strength{margin-top:5px;} .pw-bars{display:flex;gap:3px;margin-bottom:3px;} .pw-bar{flex:1;height:3px;border-radius:2px;background:var(--surface2);transition:background .2s;} .pw-label{font-size:11px;color:var(--text3);}
.match-msg{font-size:11px;margin-top:3px;}
.btn-submit{width:100%;padding:10px;border-radius:var(--radius);border:none;background:var(--green);color:#fff;font-size:14px;font-weight:500;font-family:var(--font);cursor:pointer;transition:background .12s;margin-top:4px;display:flex;align-items:center;justify-content:center;gap:6px;}
.btn-submit:hover{background:var(--green-dk);}
.form-footer{text-align:center;margin-top:18px;font-size:13px;color:var(--text3);}
.form-footer a{color:var(--green);font-weight:500;text-decoration:none;}
.terms-note{font-size:11px;color:var(--text3);text-align:center;margin-top:10px;line-height:1.5;}
.terms-note a{color:var(--green);}
@media(max-width:600px){.panel{display:none;}.form-wrap{padding:32px 24px;}.shell{border-radius:var(--radius);}}
</style>
</head>
<body>
<div class="shell">
  <div class="panel">
    <div class="panel-logo">
      <div class="panel-logo-icon"><i class="ti ti-paw"></i></div>
      <div><div class="panel-logo-name">Pawrtal</div><div class="panel-logo-sub">Bacolod City</div></div>
    </div>
    <div class="panel-body">
      <div class="panel-headline">Get started in minutes</div>
      <div class="panel-desc">Join the community and help reunite lost pets with their families.</div>
      <div class="panel-steps">
        <div class="panel-step"><div class="step-num">1</div><div class="step-text"><strong>Create your account</strong> — sign up and verify your email</div></div>
        <div class="panel-step"><div class="step-num">2</div><div class="step-text"><strong>Register your pet</strong> — add a photo and details, get a QR tag</div></div>
        <div class="panel-step"><div class="step-num">3</div><div class="step-text"><strong>Report &amp; connect</strong> — post lost or found reports and help pets get home</div></div>
      </div>
    </div>
  </div>
  <div class="form-wrap">
    <div class="form-eyebrow">Get started</div>
    <div class="form-title">Create your account</div>
    <div class="form-sub">Free for the Bacolod City community.</div>
    <?php if ($message): ?>
    <div class="error-box"><i class="ti ti-alert-circle"></i><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="field">
        <label class="field-label" for="name">Full name</label>
        <div class="field-wrap">
          <span class="field-icon"><i class="ti ti-user"></i></span>
          <input class="field-input" type="text" name="name" id="name" placeholder="e.g. Juan Dela Cruz" value="<?= htmlspecialchars($formName) ?>" autocomplete="name" required>
        </div>
      </div>
      <div class="field">
        <label class="field-label" for="email">Email address</label>
        <div class="field-wrap">
          <span class="field-icon"><i class="ti ti-mail"></i></span>
          <input class="field-input" type="email" name="email" id="email" placeholder="juandelacruz@gmail.com" value="<?= htmlspecialchars($formEmail) ?>" autocomplete="email" required>
        </div>
      </div>
      <div class="field">
        <label class="field-label" for="password">Password <span style="font-weight:400;color:var(--text3);font-size:11px;">min. 8 characters</span></label>
        <div class="field-wrap">
          <span class="field-icon"><i class="ti ti-lock"></i></span>
          <input class="field-input" type="password" name="password" id="password" placeholder="••••••••" autocomplete="new-password" oninput="checkStrength(this.value)" required>
          <button type="button" class="toggle-btn" onclick="togglePw('password',this)"><i class="ti ti-eye"></i></button>
        </div>
        <div class="pw-strength" id="pwStrength" style="display:none;">
          <div class="pw-bars"><div class="pw-bar" id="bar1"></div><div class="pw-bar" id="bar2"></div><div class="pw-bar" id="bar3"></div><div class="pw-bar" id="bar4"></div></div>
          <div class="pw-label" id="pwLabel"></div>
        </div>
      </div>
      <div class="field">
        <label class="field-label" for="confirm_password">Confirm password</label>
        <div class="field-wrap">
          <span class="field-icon"><i class="ti ti-lock-check"></i></span>
          <input class="field-input" type="password" name="confirm_password" id="confirm_password" placeholder="••••••••" autocomplete="new-password" oninput="checkMatch()" required>
          <button type="button" class="toggle-btn" onclick="togglePw('confirm_password',this)"><i class="ti ti-eye"></i></button>
        </div>
        <div class="match-msg" id="matchMsg"></div>
      </div>
      <button type="submit" class="btn-submit"><i class="ti ti-user-plus"></i> Create account</button>
    </form>
    <div class="form-footer">Already have an account? <a href="login.php">Log in</a></div>
    <div class="terms-note">By creating an account, you agree to our <a href="../public/disclaimer.php">Terms &amp; Disclaimer</a>.</div>
  </div>
</div>
<script>
function togglePw(id,btn){const i=document.getElementById(id);const ic=btn.querySelector('i');i.type=i.type==='password'?'text':'password';ic.className=i.type==='text'?'ti ti-eye-off':'ti ti-eye';}
function checkStrength(v){const w=document.getElementById('pwStrength');const l=document.getElementById('pwLabel');const b=['bar1','bar2','bar3','bar4'].map(x=>document.getElementById(x));if(!v){w.style.display='none';return;}w.style.display='block';let s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;const c=['','#ef4444','#f59e0b','#10b981','#059669'];const la=['','Weak','Fair','Good','Strong'];b.forEach((x,i)=>x.style.background=i<s?c[s]:'var(--surface2)');l.textContent=s?la[s]+' password':'';l.style.color=c[s]||'var(--text3)';}
function checkMatch(){const pw=document.getElementById('password').value;const cf=document.getElementById('confirm_password').value;const m=document.getElementById('matchMsg');if(!cf){m.textContent='';return;}m.textContent=pw===cf?'Passwords match':'Passwords do not match';m.style.color=pw===cf?'#2d6a4f':'#8b2635';}
</script>
</body></html>