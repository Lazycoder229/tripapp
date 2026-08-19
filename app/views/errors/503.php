<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 — Service Unavailable</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { max-width: 520px; width: 100%; text-align: center; background: #1e293b; padding: 48px 36px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); border: 1px solid #334155; }
        .status-code { font-size: 80px; font-weight: 900; line-height: 1; color: #facc15; margin: 0 0 16px 0; letter-spacing: -0.05em; }
        h1 { font-size: 22px; color: #f1f5f9; margin: 0 0 12px 0; font-weight: 700; }
        p { font-size: 15px; color: #94a3b8; line-height: 1.6; margin: 0 0 28px 0; }
        .spinner { width: 36px; height: 36px; margin: 0 auto 20px auto; border: 3px solid rgba(250, 204, 21, 0.2); border-top-color: #facc15; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <div class="status-code">503</div>
        <h1>Under Maintenance</h1>
        <p>We are currently undergoing scheduled system maintenance to serve you better. Please check back shortly.</p>
    </div>
</body>
</html>
