<?php
declare(strict_types=1);

$message = isset($message) && is_string($message) && trim($message) !== ''
    ? trim($message)
    : 'We are currently doing maintenance. Please check back later.';

$title = $title ?? 'Maintenance Mode';
$retryAfter = isset($retryAfter) ? (int)$retryAfter : 3600;

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="<?= max(60, $retryAfter) ?>">
  <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    :root{
      --bg1:#0f172a;
      --bg2:#111827;
      --card:#ffffff;
      --ink:#0f172a;
      --muted:#64748b;
      --accent:#d97706;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      min-height:100vh;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      color:var(--ink);
      background:
        radial-gradient(circle at top left, rgba(217,119,6,.16), transparent 30%),
        radial-gradient(circle at bottom right, rgba(59,130,246,.12), transparent 28%),
        linear-gradient(160deg, var(--bg1), var(--bg2));
      display:grid;
      place-items:center;
      padding:24px;
    }
    .wrap{
      width:min(760px, 100%);
      background:rgba(255,255,255,.96);
      border-radius:28px;
      box-shadow:0 24px 80px rgba(15,23,42,.45);
      overflow:hidden;
      border:1px solid rgba(255,255,255,.18);
    }
    .top{
      padding:34px 34px 0;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:16px;
    }
    .brand{
      font-size:14px;
      letter-spacing:.14em;
      text-transform:uppercase;
      color:var(--accent);
      font-weight:800;
    }
    .pill{
      background:#fff7ed;
      color:#9a3412;
      border:1px solid #fed7aa;
      padding:8px 12px;
      border-radius:999px;
      font-size:13px;
      font-weight:700;
    }
    .content{
      padding:24px 34px 34px;
    }
    h1{
      margin:14px 0 12px;
      font-size:clamp(2rem, 4vw, 3.2rem);
      line-height:1.05;
    }
    p{
      margin:0;
      color:var(--muted);
      font-size:1.05rem;
      line-height:1.7;
    }
    .note{
      margin-top:22px;
      display:flex;
      flex-wrap:wrap;
      gap:12px;
      align-items:center;
      color:var(--muted);
      font-size:.95rem;
    }
    .card{
      margin-top:28px;
      background:#f8fafc;
      border:1px solid #e2e8f0;
      border-radius:20px;
      padding:18px 20px;
    }
    .small{
      color:var(--muted);
      font-size:.92rem;
      margin-top:8px;
    }
    @media (max-width: 640px){
      .top,.content{padding-left:22px;padding-right:22px}
      .top{flex-direction:column;align-items:flex-start}
    }
  </style>
</head>
<body>
  <main class="wrap" role="main" aria-labelledby="maintenance-title">
    <div class="top">
      <div class="brand">Maorin Builders</div>
      <div class="pill">Maintenance in progress</div>
    </div>
    <div class="content">
      <h1 id="maintenance-title">We’ll be back soon.</h1>
      <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <div class="card">
        <strong>Status:</strong> The site is temporarily unavailable while updates are being made.
        <div class="small">Please try again later. This page will refresh automatically after a short interval.</div>
      </div>
      <div class="note">
        <span>HTTP 503 Service Unavailable</span>
        <span>Retry after <?= number_format(max(60, (int)$retryAfter)) ?> seconds</span>
      </div>
    </div>
  </main>
</body>
</html>
