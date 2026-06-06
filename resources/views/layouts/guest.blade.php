<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'DFOMS') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favwh.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('favwh.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favwh.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--accent:#e5252a;--accent-hover:#c41e23;--text:#0d0d0d;--text-2:#6b7280;--text-3:#9ca3af;--border:#e5e7eb;--bg:#f5f6f8}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Poppins',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;min-height:100vh}
        .auth-wrap{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px}
        .auth-brand{display:inline-flex;align-items:center;text-decoration:none;margin-bottom:22px}
        .auth-brand .mark{background:var(--accent);border-radius:10px 0 0 10px;padding:9px 14px;line-height:1;box-shadow:0 3px 10px rgba(229,37,42,.28)}
        .auth-brand .mark span{color:#fff;font-weight:600;font-size:22px;letter-spacing:-1.5px}
        .auth-brand .tail{background:#1c1c1e;border-radius:0 10px 10px 0;padding:9px 12px;line-height:1}
        .auth-brand .tail span{color:rgba(255,255,255,.85);font-weight:500;font-size:11px;letter-spacing:3px}
        .auth-card{width:100%;max-width:420px;background:#fff;border:1px solid var(--border);border-radius:16px;box-shadow:0 10px 40px rgba(15,23,42,.08);padding:28px 26px}
        .auth-card h1{font-size:19px;font-weight:700;margin-bottom:4px}
        .auth-card .sub{font-size:13px;color:var(--text-2);margin-bottom:20px}
        .auth-field{margin-bottom:14px}
        .auth-label{display:block;font-size:12px;font-weight:600;margin-bottom:6px;color:var(--text)}
        .auth-input{width:100%;height:44px;padding:0 14px;font-size:14px;font-family:inherit;color:var(--text);background:#fff;border:1.5px solid var(--border);border-radius:9px;transition:border-color .15s,box-shadow .15s}
        .auth-input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(229,37,42,.10)}
        .auth-row{display:flex;align-items:center;justify-content:space-between;margin:6px 0 18px}
        .auth-check{display:inline-flex;align-items:center;gap:7px;font-size:13px;color:var(--text-2);cursor:pointer}
        .auth-check input{width:16px;height:16px;accent-color:var(--accent)}
        .auth-link{font-size:13px;color:var(--accent);text-decoration:none;font-weight:500}
        .auth-link:hover{text-decoration:underline}
        .auth-btn{width:100%;height:46px;font-size:14.5px;font-weight:700;font-family:inherit;color:#fff;background:var(--text);border:none;border-radius:9px;cursor:pointer;transition:background .15s,box-shadow .15s}
        .auth-btn:hover{background:#1f2937;box-shadow:0 4px 12px rgba(15,23,42,.18)}
        .auth-error{color:#dc2626;font-size:12px;margin-top:5px}
        .auth-status{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0;border-radius:9px;padding:10px 12px;font-size:13px;margin-bottom:16px}
        .auth-note{font-size:13px;color:var(--text-2);line-height:1.6;margin-bottom:16px}
        .auth-foot{margin-top:18px;text-align:center;font-size:12px;color:var(--text-3)}
    </style>
</head>
<body>
    <div class="auth-wrap">
        <a href="/" class="auth-brand">
            <span class="mark"><span>DF</span></span>
            <span class="tail"><span>OMS</span></span>
        </a>
        <div class="auth-card">
            {{ $slot }}
        </div>
        <div class="auth-foot">© {{ date('Y') }} DFOMS · Order Management System</div>
    </div>
</body>
</html>
