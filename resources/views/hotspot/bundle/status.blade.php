{{-- SKYmanager hotspot bundle — status (MikroTik macros) --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Cache-Control" content="no-cache">
<title>Hotspot status</title>
<style>
body{font-family:system-ui,sans-serif;padding:1.25rem;max-width:28rem;margin:0 auto;color:#0f172a}
.box{border:1px solid #e2e8f0;border-radius:12px;padding:1rem;margin-top:1rem;background:#fff}
a{color:#0ea5e9}
dt{opacity:.7;font-size:.75rem;text-transform:uppercase;margin-top:.5rem}
dd{margin:0;font-weight:600}
</style>
</head>
<body>
<h1 style="font-size:1.1rem">Session</h1>
<div class="box">
<dl>
<dt>IP</dt><dd>$(ip)</dd>
<dt>MAC</dt><dd>$(mac)</dd>
<dt>User</dt><dd>$(username)</dd>
<dt>Time left</dt><dd>$(session-time-left)</dd>
</dl>
<p style="margin-top:1rem"><a href="$(link-logout)">Log out</a> · <a href="$(link-orig)">Open internet</a></p>
</div>
</body>
</html>
