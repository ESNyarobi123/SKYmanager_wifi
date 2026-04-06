{{-- SKYmanager hotspot bundle — captive portal redirect step --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Cache-Control" content="no-cache">
<meta http-equiv="refresh" content="0; url=$(link-redirect)">
<title>Redirecting</title>
<script>try{location.replace("$(link-redirect)");}catch(e){}</script>
</head>
<body>
<p><a href="$(link-redirect)">Continue to internet</a></p>
</body>
</html>
