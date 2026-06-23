<?php
// qr/scan.php
// Mobile-first QR scanner page — no login required.
// Uses the device camera via jsQR (pure JS, no server needed).
// On a successful scan, redirects to public/pet_profile.php?token=...
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Scan Pet QR — Pawrtal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --green:#2d6a4f;--green-dk:#1b4332;--green-lt:#f0fdf4;
  --red:#8b2635;
  --text:#1a1208;--text2:#44352a;--text3:#8a7060;
  --surface:#fff;--bg:#f7f5f2;
  --border:rgba(0,0,0,.08);
  --radius:10px;--radius-lg:14px;
  --font:'DM Sans',system-ui,sans-serif;
  --display:'DM Serif Display',Georgia,serif;
}
html,body{height:100%;}
body{
  background:#111;
  font-family:var(--font);
  font-size:14px;
  color:#fff;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:flex-start;
  min-height:100%;
  overflow-x:hidden;
}

/* ── Header ─────────────────────────── */
.header{
  width:100%;
  padding:14px 18px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  background:rgba(0,0,0,.4);
  backdrop-filter:blur(8px);
  position:fixed;top:0;left:0;z-index:50;
}
.header-logo{display:flex;align-items:center;gap:9px;}
.header-logo-icon{
  width:30px;height:30px;border-radius:8px;
  background:var(--green);
  display:flex;align-items:center;justify-content:center;
}
.header-logo-icon i{color:#fff;font-size:16px;}
.header-title{font-family:var(--display);font-size:.95rem;color:#fff;}
.header-back{
  display:flex;align-items:center;gap:5px;
  color:rgba(255,255,255,.75);font-size:13px;
  text-decoration:none;
}
.header-back:hover{color:#fff;}

/* ── Camera viewport ─────────────────── */
.camera-wrap{
  position:fixed;inset:0;
  display:flex;align-items:center;justify-content:center;
}
#video{
  width:100%;height:100%;
  object-fit:cover;
  display:block;
}

/* ── Scan overlay ────────────────────── */
.scan-overlay{
  position:fixed;inset:0;
  display:flex;flex-direction:column;
  align-items:center;justify-content:center;
  z-index:10;
  pointer-events:none;
}
.scan-frame{
  width:240px;height:240px;
  position:relative;
}
/* Corner brackets */
.scan-frame::before,.scan-frame::after,
.corner-br,.corner-bl{
  content:'';
  position:absolute;
  width:36px;height:36px;
  border-color:#fff;
  border-style:solid;
}
.scan-frame::before{ top:0;left:0;     border-width:3px 0 0 3px; border-radius:4px 0 0 0; }
.scan-frame::after { top:0;right:0;    border-width:3px 3px 0 0; border-radius:0 4px 0 0; }
.corner-br          { bottom:0;right:0; border-width:0 3px 3px 0; border-radius:0 0 4px 0; }
.corner-bl          { bottom:0;left:0;  border-width:0 0 3px 3px; border-radius:0 0 0 4px; }

/* Scan line animation */
.scan-line{
  position:absolute;
  left:6px;right:6px;
  height:2px;
  background:linear-gradient(90deg,transparent,var(--green),transparent);
  top:0;
  animation:scanAnim 2s ease-in-out infinite;
}
@keyframes scanAnim{
  0%  {top:6px;  opacity:0;}
  10% {opacity:1;}
  90% {opacity:1;}
  100%{top:calc(100% - 8px);opacity:0;}
}

.scan-hint{
  margin-top:20px;
  font-size:13px;
  color:rgba(255,255,255,.8);
  text-align:center;
  text-shadow:0 1px 4px rgba(0,0,0,.5);
}

/* ── Status card (shown below frame) ─── */
.status-card{
  position:fixed;
  bottom:0;left:0;right:0;
  background:var(--surface);
  border-radius:20px 20px 0 0;
  padding:20px 22px 32px;
  transform:translateY(100%);
  transition:transform .35s cubic-bezier(.34,1.2,.64,1);
  z-index:20;
}
.status-card.visible{transform:translateY(0);}

.status-icon{
  width:52px;height:52px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 12px;
  font-size:26px;
}
.icon-success{background:var(--green-lt);color:var(--green);}
.icon-error  {background:#fef2f2;color:var(--red);}
.icon-loading{background:#f2efeb;color:var(--text3);}

.status-title{font-family:var(--display);font-size:1.2rem;color:var(--text);text-align:center;margin-bottom:4px;}
.status-sub  {font-size:13px;color:var(--text3);text-align:center;margin-bottom:18px;}

.status-btn{
  width:100%;padding:12px;
  border-radius:var(--radius);border:none;
  font-family:var(--font);font-size:14px;font-weight:500;
  cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:7px;
  text-decoration:none;
}
.btn-green{background:var(--green);color:#fff;}
.btn-green:hover{background:var(--green-dk);}
.btn-outline{background:var(--bg);color:var(--text2);border:.5px solid rgba(0,0,0,.12);margin-top:8px;}
.btn-outline:hover{background:#ece8e2;}

/* ── No-camera fallback ──────────────── */
.fallback-card{
  background:var(--surface);
  border-radius:var(--radius-lg);
  padding:24px 22px;
  margin:80px 20px 20px;
  text-align:center;
  width:calc(100% - 40px);
  max-width:380px;
}
.fallback-icon{font-size:2.5rem;color:var(--text3);margin-bottom:12px;}
.fallback-title{font-family:var(--display);font-size:1.1rem;color:var(--text);margin-bottom:6px;}
.fallback-sub{font-size:13px;color:var(--text3);margin-bottom:18px;line-height:1.6;}
.fallback-input{
  width:100%;padding:10px 12px;
  border:.5px solid rgba(0,0,0,.15);border-radius:var(--radius);
  font-family:var(--font);font-size:13px;color:var(--text);
  outline:none;margin-bottom:10px;
}
.fallback-input:focus{border-color:var(--green);}
</style>
</head>
<body>

<!-- Header -->
<div class="header">
  <div class="header-logo">
    <div class="header-logo-icon"><i class="ti ti-paw"></i></div>
    <div class="header-title">Pawrtal</div>
  </div>
  <a href="../public/index.php" class="header-back"><i class="ti ti-arrow-left" style="font-size:16px;"></i> Back</a>
</div>

<!-- Camera -->
<div class="camera-wrap" id="cameraWrap">
  <video id="video" autoplay playsinline muted></video>
</div>

<!-- Scan frame overlay -->
<div class="scan-overlay" id="scanOverlay">
  <div class="scan-frame">
    <div class="corner-br"></div>
    <div class="corner-bl"></div>
    <div class="scan-line"></div>
  </div>
  <div class="scan-hint">Point at a Pawrtal pet QR tag</div>
</div>

<!-- Hidden canvas for frame capture -->
<canvas id="canvas" style="display:none;"></canvas>

<!-- Status card (slides up from bottom) -->
<div class="status-card" id="statusCard">
  <div class="status-icon icon-loading" id="statusIcon"><i class="ti ti-loader-2 ti-spin"></i></div>
  <div class="status-title" id="statusTitle">Scanning…</div>
  <div class="status-sub"   id="statusSub">Hold steady</div>
  <a href="#" class="status-btn btn-green" id="statusBtn" style="display:none;"></a>
  <button class="status-btn btn-outline" id="retryBtn" style="display:none;" onclick="resetScanner()">
    <i class="ti ti-refresh"></i> Scan again
  </button>
</div>

<!-- No-camera fallback (shown if camera unavailable) -->
<div class="fallback-card" id="fallbackCard" style="display:none;">
  <div class="fallback-icon"><i class="ti ti-camera-off"></i></div>
  <div class="fallback-title">Camera unavailable</div>
  <div class="fallback-sub">Allow camera access, or enter the 8-character pet ID printed on the tag manually.</div>
  <input type="text" class="fallback-input" id="manualInput"
         placeholder="e.g. A3F7B2C1"
         maxlength="64"
         oninput="this.value=this.value.toUpperCase()">
  <button class="status-btn btn-green" onclick="lookupManual()">
    <i class="ti ti-search"></i> Look up pet
  </button>
</div>

<!-- jsQR library -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script>
const video      = document.getElementById('video');
const canvas     = document.getElementById('canvas');
const ctx        = canvas.getContext('2d');
const statusCard = document.getElementById('statusCard');
const statusIcon = document.getElementById('statusIcon');
const statusTitle= document.getElementById('statusTitle');
const statusSub  = document.getElementById('statusSub');
const statusBtn  = document.getElementById('statusBtn');
const retryBtn   = document.getElementById('retryBtn');
const fallback   = document.getElementById('fallbackCard');
const overlay    = document.getElementById('scanOverlay');
const cameraWrap = document.getElementById('cameraWrap');

let scanning     = true;
let animFrame    = null;

// ── Start camera ────────────────────────────────────────────────
async function startCamera() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } }
    });
    video.srcObject = stream;
    video.play();
    video.addEventListener('loadeddata', () => { tick(); });
  } catch (err) {
    // Camera denied or unavailable — show fallback
    cameraWrap.style.display = 'none';
    overlay.style.display    = 'none';
    fallback.style.display   = 'block';
    document.body.style.background = '#f7f5f2';
    document.body.style.color      = '#1a1208';
  }
}

// ── Scan loop ───────────────────────────────────────────────────
function tick() {
  if (!scanning) return;

  if (video.readyState === video.HAVE_ENOUGH_DATA) {
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code      = jsQR(imageData.data, imageData.width, imageData.height, {
      inversionAttempts: 'dontInvert'
    });

    if (code) {
      scanning = false;
      cancelAnimationFrame(animFrame);
      handleResult(code.data);
      return;
    }
  }
  animFrame = requestAnimationFrame(tick);
}

// ── Handle scanned URL ──────────────────────────────────────────
function handleResult(data) {
  // Expect a URL containing ?token=... or just a raw token
  let token = '';

  try {
    const url    = new URL(data);
    token        = url.searchParams.get('token') || '';
  } catch {
    // Not a URL — treat raw value as the token
    token = data.trim();
  }

  if (token) {
    showSuccess(token);
  } else {
    showError('QR code found but it doesn\'t seem to be a Pawrtal pet tag. Make sure you\'re scanning the right code.');
  }
}

// ── Success state ───────────────────────────────────────────────
function showSuccess(token) {
  const profileUrl = `../public/pet_profile.php?token=${encodeURIComponent(token)}`;

  statusIcon.className  = 'status-icon icon-success';
  statusIcon.innerHTML  = '<i class="ti ti-circle-check"></i>';
  statusTitle.textContent = 'Pet tag found!';
  statusSub.textContent   = 'Loading pet profile…';

  statusBtn.style.display = 'flex';
  statusBtn.href          = profileUrl;
  statusBtn.innerHTML     = '<i class="ti ti-paw"></i> View pet profile';

  retryBtn.style.display  = 'flex';
  statusCard.classList.add('visible');

  // Auto-redirect after 1.5s
  setTimeout(() => { window.location.href = profileUrl; }, 1500);
}

// ── Error state ─────────────────────────────────────────────────
function showError(msg) {
  statusIcon.className    = 'status-icon icon-error';
  statusIcon.innerHTML    = '<i class="ti ti-alert-circle"></i>';
  statusTitle.textContent = 'Not recognized';
  statusSub.textContent   = msg;
  statusBtn.style.display = 'none';
  retryBtn.style.display  = 'flex';
  statusCard.classList.add('visible');
}

// ── Reset ───────────────────────────────────────────────────────
function resetScanner() {
  scanning = true;
  statusCard.classList.remove('visible');
  statusIcon.className    = 'status-icon icon-loading';
  statusIcon.innerHTML    = '<i class="ti ti-loader-2"></i>';
  statusTitle.textContent = 'Scanning…';
  statusSub.textContent   = 'Hold steady';
  statusBtn.style.display = 'none';
  retryBtn.style.display  = 'none';
  setTimeout(() => { tick(); }, 400);
}

// ── Manual token lookup ─────────────────────────────────────────
function lookupManual() {
  const val = document.getElementById('manualInput').value.trim();
  if (!val) return;
  // The printed ID is the first 8 chars of the token — but we stored the full token.
  // Redirect to a lookup endpoint that handles partial tokens.
  window.location.href = `../public/pet_profile.php?token=${encodeURIComponent(val)}`;
}

// Also allow Enter key in manual input
document.getElementById('manualInput').addEventListener('keydown', e => {
  if (e.key === 'Enter') lookupManual();
});

// ── Boot ────────────────────────────────────────────────────────
startCamera();
</script>
</body>
</html>
