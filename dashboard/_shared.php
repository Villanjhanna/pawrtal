<?php
// Shared sidebar + head snippet — include at top of each dashboard page
// Usage: include '_shared.php'; then call pf_head($title), pf_sidebar($active), pf_topbar($title, $sub, $buttons)

function pf_head(string $title): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?> — Pawrtal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Serif+Display&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f7f5f2;--surface:#fff;--surface2:#f2efeb;
  --border:rgba(0,0,0,.08);--border-md:rgba(0,0,0,.14);
  --green:#2d6a4f;--green-lt:#f0fdf4;--green-dk:#1b4332;
  --red:#8b2635;--red-lt:#fef2f2;
  --teal:#0f766e;--teal-lt:#f0fdfa;
  --blue:#1e40af;--blue-lt:#eff6ff;
  --amber:#92400e;--amber-lt:#fffbeb;
  --text:#1a1208;--text2:#44352a;--text3:#8a7060;
  --success-bg:#f0fdf4;--success-text:#166534;
  --warn-bg:#fffbeb;--warn-text:#92400e;
  --danger-bg:#fef2f2;--danger-text:#991b1b;
  --radius:8px;--radius-lg:12px;
  --font:'DM Sans',system-ui,sans-serif;
  --display:'DM Serif Display',Georgia,serif;
}
body{background:var(--bg);color:var(--text);font-family:var(--font);font-size:14px;line-height:1.6;display:flex;min-height:100vh}

/* ── Sidebar ─────────────────────────────── */
.sb{width:224px;flex-shrink:0;background:var(--surface);border-right:.5px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:50}
.sb-brand{padding:16px 14px;border-bottom:.5px solid var(--border);display:flex;align-items:center;gap:10px}
.sb-logo{width:34px;height:34px;border-radius:9px;background:var(--green);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-logo i{color:#fff;font-size:18px}
.sb-appname{font-family:var(--display);font-size:1.05rem;color:var(--text);line-height:1.1}
.sb-appsub{font-size:10px;color:var(--text3)}
.sb-nav{padding:8px;flex:1;overflow-y:auto}
.sb-sec{font-size:10px;letter-spacing:.09em;text-transform:uppercase;color:var(--text3);padding:10px 8px 4px}
.sb-item{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:var(--radius);color:var(--text2);font-size:13px;text-decoration:none;transition:background .1s;margin-bottom:1px;position:relative}
.sb-item:hover{background:var(--surface2)}
.sb-item.active{background:var(--green-lt);color:var(--green);font-weight:500}
.sb-item i{font-size:16px;width:17px;text-align:center;flex-shrink:0}
.sb-badge{margin-left:auto;background:var(--green);color:#fff;font-size:10px;font-weight:600;padding:1px 6px;border-radius:99px}
.sb-foot{padding:8px;border-top:.5px solid var(--border)}
.sb-user{display:flex;align-items:center;gap:9px;padding:8px 10px}
.sb-av{width:32px;height:32px;border-radius:50%;background:var(--green-lt);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:var(--green);flex-shrink:0}
.sb-uname{font-size:13px;font-weight:500;color:var(--text)}
.sb-uemail{font-size:11px;color:var(--text3)}

/* ── Main area ───────────────────────────── */
.main{margin-left:224px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--surface);border-bottom:.5px solid var(--border);padding:14px 24px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40}
.topbar-title{font-family:var(--display);font-size:1.05rem;color:var(--text)}
.topbar-sub{font-size:12px;color:var(--text3);margin-top:1px}
.topbar-right{display:flex;gap:8px}
.content{padding:22px 24px;display:flex;flex-direction:column;gap:16px}

/* ── Buttons ─────────────────────────────── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border-radius:var(--radius);border:.5px solid var(--border-md);background:var(--surface);color:var(--text);font-size:13px;font-family:var(--font);cursor:pointer;font-weight:500;white-space:nowrap;text-decoration:none;transition:all .1s}
.btn:hover{background:var(--surface2)}
.btn.primary{background:var(--red);color:#fff;border-color:var(--red)}
.btn.primary:hover{background:#7a1f2d}
.btn.success{background:var(--green);color:#fff;border-color:var(--green)}
.btn.success:hover{background:var(--green-dk)}
.btn.sm{padding:5px 10px;font-size:12px}
.btn.danger{background:var(--danger-bg);color:var(--danger-text);border-color:rgba(153,27,27,.2)}
.btn.danger:hover{background:#fee2e2}
.btn.confirm{background:var(--success-bg);color:var(--success-text);border-color:rgba(22,101,52,.2)}
.btn.confirm:hover{background:#dcfce7}

/* ── Cards ───────────────────────────────── */
.card{background:var(--surface);border:.5px solid var(--border);border-radius:var(--radius-lg);padding:18px 20px}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.card-title{font-family:var(--display);font-size:.95rem;color:var(--text)}
.card-sub{font-size:12px;color:var(--text3);margin-top:2px}

/* ── Alerts ──────────────────────────────── */
.alert{display:flex;align-items:center;gap:8px;padding:10px 14px;border-radius:var(--radius);font-size:13px;border:.5px solid}
.alert.success{background:var(--success-bg);color:var(--success-text);border-color:#bbf7d0}
.alert.info{background:var(--amber-lt);color:var(--amber);border-color:#fde68a}

/* ── Empty state ─────────────────────────── */
.empty{text-align:center;padding:28px 16px;color:var(--text3);font-size:13px}
.empty i{font-size:2.2rem;display:block;margin-bottom:8px;color:var(--text3)}

/* ── Footer ──────────────────────────────── */
footer{padding:14px 24px;border-top:.5px solid var(--border);font-size:11px;color:var(--text3);background:var(--surface);margin-top:auto}

/* ── Responsive ──────────────────────────── */
@media(max-width:640px){
  .sb{display:none}
  .main{margin-left:0}
}

@media(max-width:900px){

    .content > div:nth-child(2){
        grid-template-columns:1fr !important;
    }

}

@media(max-width:640px){

    .content{
        padding:16px;
    }

}
</style>
<?php }

function pf_initials(string $name): string {
    $i = '';
    foreach (explode(' ', $name) as $p) $i .= strtoupper(substr($p, 0, 1));
    return substr($i, 0, 2);
}
