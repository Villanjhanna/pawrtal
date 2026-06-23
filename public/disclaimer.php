<?php session_start(); ?>
<!DOCTYPE html><html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms &amp; Disclaimer — Pawrtal</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f7f5f2;--surface:#fff;--border:rgba(0,0,0,.08);--green:#2d6a4f;--green-dk:#1b4332;--green-lt:#f0fdf4;--text:#1a1208;--text2:#44352a;--text3:#8a7060;--radius:8px;--radius-lg:12px;--font:'DM Sans',system-ui,sans-serif;--display:'DM Serif Display',Georgia,serif;}
body{background:var(--bg);font-family:var(--font);font-size:14px;color:var(--text);line-height:1.7;}
nav{background:var(--surface);border-bottom:.5px solid var(--border);padding:0 2rem;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;box-shadow:0 1px 6px rgba(0,0,0,.04);}
.nav-logo{display:flex;align-items:center;gap:10px;}
.nav-logo-icon{width:32px;height:32px;border-radius:9px;background:var(--green);display:flex;align-items:center;justify-content:center;}
.nav-logo-icon i{color:#fff;font-size:17px;}
.nav-logo-name{font-family:var(--display);font-size:1.05rem;color:var(--text);}
.btn{display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border-radius:var(--radius);border:.5px solid rgba(0,0,0,.14);background:var(--surface);color:var(--text);font-size:13px;font-family:var(--font);cursor:pointer;font-weight:500;text-decoration:none;transition:background .12s;}
.btn:hover{background:#f2efeb;}
.wrap{max-width:760px;margin:3rem auto;padding:0 2rem 4rem;}
.hero{text-align:center;margin-bottom:3rem;}
.hero-tag{display:inline-flex;align-items:center;gap:7px;padding:5px 14px;border-radius:99px;border:.5px solid rgba(45,106,79,.25);background:var(--green-lt);color:var(--green);font-size:12px;font-weight:500;margin-bottom:1rem;}
.hero h1{font-family:var(--display);font-size:2rem;color:var(--text);margin-bottom:8px;}
.hero p{font-size:13px;color:var(--text3);}
.card{background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:28px 32px;margin-bottom:16px;}
.card h2{font-family:var(--display);font-size:1.1rem;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:9px;}
.card h2 i{font-size:1.1rem;color:var(--green);}
.card p{font-size:13px;color:var(--text2);margin-bottom:10px;line-height:1.75;}
.card p:last-child{margin-bottom:0;}
.card ul{font-size:13px;color:var(--text2);padding-left:18px;line-height:1.9;}
.highlight{background:var(--green-lt);border:.5px solid rgba(45,106,79,.2);border-radius:var(--radius);padding:14px 16px;font-size:13px;color:var(--text2);margin-top:12px;}
.highlight strong{color:var(--green);}
footer{text-align:center;padding:1.5rem;border-top:.5px solid var(--border);font-size:12px;color:var(--text3);background:var(--surface);}
footer a{color:var(--green);font-weight:500;}
</style>
</head>
<body>
<nav>
  <div class="nav-logo">
    <div class="nav-logo-icon"><i class="ti ti-paw"></i></div>
    <div><div class="nav-logo-name">Pawrtal</div></div>
  </div>
  <a href="index.php" class="btn"><i class="ti ti-arrow-left"></i> Back to home</a>
</nav>

<div class="wrap">
  <div class="hero">
    <div class="hero-tag"><i class="ti ti-shield-check" style="font-size:13px;"></i> Legal &amp; Privacy</div>
    <h1>Terms &amp; Disclaimer</h1>
    <p>Last updated: <?= date('F j, Y') ?> &middot; Please read carefully before using Pawrtal.</p>
  </div>

  <div class="card">
    <h2><i class="ti ti-info-circle"></i> About Pawrtal</h2>
    <p>Pawrtal is a community-based lost and found pet recovery platform serving Bacolod City and surrounding areas. It is provided as a free public service to help connect pet owners with community members who find stray or missing pets.</p>
    <p>Pawrtal is not a government agency, animal control authority, or licensed animal welfare organization. We are a volunteer-driven, community-supported platform.</p>
  </div>

  <div class="card">
    <h2><i class="ti ti-user-check"></i> User responsibilities</h2>
    <p>By creating an account and using Pawrtal, you agree to:</p>
    <ul>
      <li>Provide accurate, truthful information in all reports and your profile</li>
      <li>Only post reports for pets you own or have personally found</li>
      <li>Update or remove your report when a pet is recovered or the situation changes</li>
      <li>Contact other users respectfully and in good faith</li>
      <li>Not use the platform for commercial solicitation, spam, or any unlawful purpose</li>
      <li>Not attempt to claim ownership of a pet that is not yours</li>
    </ul>
  </div>

  <div class="card">
    <h2><i class="ti ti-shield" style="color:#8b2635;"></i> No guarantee of outcomes</h2>
    <p>Pawrtal facilitates community connection but cannot guarantee that a lost pet will be found, that a found pet will be claimed, or that any match between reports is accurate. All matches are algorithmic suggestions based on reported attributes — they must be verified by the parties involved.</p>
    <p>Pawrtal is not responsible for the actions of its users, the condition of animals reported, or the outcome of any interaction between users.</p>
  </div>

  <div class="card">
    <h2><i class="ti ti-lock"></i> Privacy &amp; data</h2>
    <p>When you register, your name and email are stored securely. Your email address is verified before you can post reports. Your contact information is only shared with other users in the following limited circumstances:</p>
    <ul>
      <li>Your phone number on a found report is only revealed to a pet owner after they confirm a match</li>
      <li>Your email is visible on your QR pet profile only if you choose to include it</li>
      <li>Report details (pet descriptions, photos, general location) are publicly visible to help with recovery</li>
    </ul>
    <div class="highlight"><strong>QR tags:</strong> Anyone who scans your pet's QR tag will see your pet's details and your contact information. Do not include sensitive personal information in your pet's profile description.</div>
  </div>

  <div class="card">
    <h2><i class="ti ti-map-pin"></i> Location data</h2>
    <p>If you drop a pin on the map when submitting a report, the approximate coordinates are stored and displayed publicly on the community map. We recommend using a general area rather than a precise home address.</p>
    <p>Location data is used solely to display reports on the community map. We do not track your device location.</p>
  </div>

  <div class="card">
    <h2><i class="ti ti-photo"></i> Photos &amp; content</h2>
    <p>By uploading a photo, you confirm that you have the right to share it and grant Pawrtal permission to display it on the platform for the purpose of pet recovery. Photos may be visible to all site visitors.</p>
    <p>Do not upload photos that contain identifying personal information, license plates, or sensitive locations.</p>
  </div>

  <div class="card">
    <h2><i class="ti ti-heart-handshake"></i> Reunification &amp; match workflow</h2>
    <p>When a pet owner confirms a match, the status is updated to <strong>Matched</strong> and the finder is notified with the owner's intent to reconnect. Both parties are expected to coordinate directly via the contact details provided.</p>
    <p>Once the pet is physically returned, the owner should click <strong>Mark as Reunited</strong> to close the report and update community statistics. Leaving a resolved report active prevents accurate community data.</p>
  </div>

  <div class="card">
    <h2><i class="ti ti-alert-triangle" style="color:#92400e;"></i> Limitation of liability</h2>
    <p>Pawrtal and its administrators are not liable for any loss, injury, dispute, or damage arising from use of the platform, including but not limited to: incorrect matches, failure to recover a pet, miscommunication between users, or any third-party actions.</p>
    <p>Use of this platform is entirely at your own risk. If you have concerns about an animal's welfare, please contact the appropriate local animal welfare authority.</p>
  </div>

  <div class="card">
    <h2><i class="ti ti-refresh"></i> Changes to these terms</h2>
    <p>These terms may be updated periodically. Continued use of Pawrtal after changes are posted constitutes acceptance of the revised terms. The date at the top of this page reflects the most recent update.</p>
    <p>For questions or concerns, contact us at <a href="mailto:support@pawrtal.com" style="color:var(--green);font-weight:500;">support@pawrtal.com</a>.</p>
  </div>
</div>

<footer>&copy; <?= date('Y') ?> Pawrtal &middot; <a href="index.php">Home</a> &middot; <a href="lost.php">Lost pets</a> &middot; <a href="found.php">Found pets</a></footer>
</body></html>