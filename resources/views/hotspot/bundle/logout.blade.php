{{-- SKYmanager hotspot bundle — logout (RouterOS form) --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Cache-Control" content="no-cache">
<title>Log out</title>
<style>body{font-family:system-ui,sans-serif;text-align:center;padding:2rem;color:#0f172a}button{padding:.6rem 1.2rem;border-radius:10px;border:none;background:#0ea5e9;color:#fff;font-weight:600;cursor:pointer}</style>
</head>
<body>
<p>Log out from hotspot?</p>
<form action="$(link-logout)" name="logout" method="post">
<button type="submit">Log out</button>
</form>
<p><a href="$(link-status)">Back to status</a></p>
</body>
</html>
