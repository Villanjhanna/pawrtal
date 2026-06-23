<?php
session_start();
include '../config/db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['email']   = $user['email'];
            header("Location: ../public/index.php");
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "No account found with that email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in — Pawrtal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f7f5f2;--surface:#fff;--surface2:#f2efeb;
  --border:rgba(0,0,0,.08);--border-md:rgba(0,0,0,.14);
  --green:#2d6a4f;--green-dk:#1b4332;--green-lt:#f0fdf4;
  --red:#8b2635;--red-lt:#fef2f2;
  --text:#1a1208;--text2:#44352a;--text3:#8a7060;
  --radius:8px;--radius-lg:12px;
  --font:'DM Sans',system-ui,sans-serif;
  --display:'DM Serif Display',Georgia,serif;
}
body{
  background:var(--bg);
  font-family:var(--font);font-size:14px;line-height:1.6;
  min-height:100vh;display:flex;align-items:center;justify-content:center;
  padding:24px;
}

.shell{
  display:flex;width:100%;max-width:820px;
  background:var(--surface);
  border-radius:var(--radius-lg);
  overflow:hidden;
  box-shadow:0 4px 24px rgba(0,0,0,.07),0 1px 4px rgba(0,0,0,.04);
}

/* ── Left panel ─────────────────────────── */
.panel{
  width:300px;flex-shrink:0;
  background:var(--green);
  padding:44px 36px;
  display:flex;flex-direction:column;
  position:relative;overflow:hidden;
}
.panel::before{
  content:'';position:absolute;
  top:-80px;right:-80px;
  width:260px;height:260px;border-radius:50%;
  background:rgba(255,255,255,.05);
}
.panel::after{
  content:'';position:absolute;
  bottom:-60px;left:-40px;
  width:200px;height:200px;border-radius:50%;
  background:rgba(255,255,255,.04);
}
.panel-logo{display:flex;align-items:center;gap:10px;position:relative;z-index:1;margin-bottom:auto;}
.panel-logo-icon{
  width:34px;height:34px;border-radius:9px;
  background:rgba(255,255,255,.15);
  display:flex;align-items:center;justify-content:center;
}
.panel-logo-icon i{color:#fff;font-size:18px;}
.panel-logo-name{font-family:var(--display);font-size:1.05rem;color:#fff;line-height:1.1;}
.panel-logo-sub{font-size:10px;color:rgba(255,255,255,.6);}
.panel-body{position:relative;z-index:1;margin-top:auto;}
.panel-headline{font-family:var(--display);font-size:1.35rem;color:#fff;line-height:1.25;margin-bottom:8px;}
.panel-desc{font-size:12px;color:rgba(255,255,255,.65);line-height:1.7;margin-bottom:24px;}
.panel-features{display:flex;flex-direction:column;gap:10px;}
.panel-feature{display:flex;align-items:center;gap:9px;font-size:12px;color:rgba(255,255,255,.75);}
.panel-feature-icon{
  width:24px;height:24px;border-radius:6px;
  background:rgba(255,255,255,.12);flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
}
.panel-feature-icon i{color:#fff;font-size:13px;}

/* ── Right form ─────────────────────────── */
.form-wrap{flex:1;padding:44px 44px;display:flex;flex-direction:column;justify-content:center;}
.form-eyebrow{font-size:11px;letter-spacing:.09em;text-transform:uppercase;color:var(--green);font-weight:600;margin-bottom:6px;}
.form-title{font-family:var(--display);font-size:1.5rem;color:var(--text);margin-bottom:4px;}
.form-sub{font-size:13px;color:var(--text3);margin-bottom:28px;}

.field{display:flex;flex-direction:column;gap:5px;margin-bottom:14px;}
.field-label{font-size:12px;font-weight:500;color:var(--text2);}
.field-wrap{position:relative;}
.field-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none;}
.field-icon i{font-size:15px;color:var(--text3);}
.field-input{
  width:100%;padding:9px 36px 9px 34px;
  border-radius:var(--radius);border:.5px solid var(--border-md);
  background:var(--surface);color:var(--text);
  font-size:13px;font-family:var(--font);
  outline:none;transition:border-color .12s,box-shadow .12s;
}
.field-input:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(45,106,79,.1);}
.field-input.err{border-color:var(--red);box-shadow:0 0 0 3px rgba(139,38,53,.08);}
.toggle-btn{
  position:absolute;right:10px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;padding:2px;
  display:flex;align-items:center;color:var(--text3);
}
.toggle-btn i{font-size:15px;}

.error-box{
  display:flex;align-items:center;gap:8px;
  padding:10px 13px;border-radius:var(--radius);
  background:var(--red-lt);color:var(--red);
  border:.5px solid rgba(139,38,53,.2);
  font-size:12px;margin-bottom:16px;
}
.error-box i{font-size:15px;flex-shrink:0;}

.btn-submit{
  width:100%;padding:10px;border-radius:var(--radius);border:none;
  background:var(--green);color:#fff;
  font-size:14px;font-weight:500;font-family:var(--font);
  cursor:pointer;transition:background .12s;margin-top:4px;
  display:flex;align-items:center;justify-content:center;gap:6px;
}
.btn-submit:hover{background:var(--green-dk);}

.form-footer{text-align:center;margin-top:20px;font-size:13px;color:var(--text3);}
.form-footer a{color:var(--green);font-weight:500;text-decoration:none;}
.form-footer a:hover{text-decoration:underline;}

.registered-banner{
  display:flex;align-items:center;gap:8px;
  padding:10px 13px;border-radius:var(--radius);
  background:var(--green-lt);color:var(--green);
  border:.5px solid rgba(45,106,79,.2);
  font-size:12px;margin-bottom:16px;
}
.registered-banner i{font-size:15px;}

@media(max-width:600px){
  .panel{display:none;}
  .form-wrap{padding:32px 24px;}
  .shell{border-radius:var(--radius);}
}
</style>
</head>
<body>

<div class="shell">

  <!-- Left panel -->
  <div class="panel">
    <div class="panel-logo">
      <div class="panel-logo-icon"><i class="ti ti-paw"></i></div>
      <div>
        <div class="panel-logo-name">Pawrtal</div>
        <div class="panel-logo-sub">Bacolod City</div>
      </div>
    </div>
    <div class="panel-body">
      <div class="panel-headline">Lost &amp; Found Pet Recovery</div>
      <div class="panel-desc">A community-based platform that helps reconnect lost pets with their families in Bacolod City.</div>
      <div class="panel-features">
        <div class="panel-feature">
          <div class="panel-feature-icon"><i class="ti ti-paw"></i></div>
          Register your pet &amp; get a QR tag
        </div>
        <div class="panel-feature">
          <div class="panel-feature-icon"><i class="ti ti-alert-circle"></i></div>
          Report lost or found pets
        </div>
        <div class="panel-feature">
          <div class="panel-feature-icon"><i class="ti ti-link"></i></div>
          Get matched automatically
        </div>
      </div>
    </div>
  </div>

  <!-- Right form -->
  <div class="form-wrap">
    <div class="form-eyebrow">Welcome back</div>
    <div class="form-title">Log in</div>
    <div class="form-sub">Enter your credentials to access your dashboard.</div>

    <?php if (isset($_GET['registered'])): ?>
    <div class="registered-banner">
      <i class="ti ti-circle-check"></i>
      Account created successfully. Log in to get started.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="error-box">
      <i class="ti ti-alert-circle"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
      <div class="field">
        <label class="field-label" for="email">Email address</label>
        <div class="field-wrap">
          <span class="field-icon"><i class="ti ti-mail"></i></span>
          <input class="field-input <?= $error ? 'err' : '' ?>"
                 type="email" name="email" id="email"
                 placeholder="you@email.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 autocomplete="email" required>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="password">Password</label>
        <div class="field-wrap">
          <span class="field-icon"><i class="ti ti-lock"></i></span>
          <input class="field-input <?= $error ? 'err' : '' ?>"
                 type="password" name="password" id="password"
                 placeholder="••••••••"
                 autocomplete="current-password" required>
          <button type="button" class="toggle-btn" onclick="togglePw('password', this)" aria-label="Show password">
            <i class="ti ti-eye" id="eye-password"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-submit">
        <i class="ti ti-login"></i> Log in
      </button>
    </form>

    <div class="form-footer">
      Don't have an account? <a href="register.php">Create one</a>
    </div>
  </div>

</div>

<script>
function togglePw(id, btn) {
  const input = document.getElementById(id);
  const icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'ti ti-eye-off';
  } else {
    input.type = 'password';
    icon.className = 'ti ti-eye';
  }
}
</script>
</body>
</html>
