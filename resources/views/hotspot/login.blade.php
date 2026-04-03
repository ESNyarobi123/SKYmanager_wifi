<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<title>{{ $routerName }} WiFi</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f0f4f8;min-height:100vh;color:#1a202c}
.header{background:linear-gradient(135deg,#1a56db 0%,#06b6d4 100%);padding:28px 20px 60px;text-align:center;position:relative;overflow:hidden}
.header::after{content:'';position:absolute;bottom:-20px;left:0;right:0;height:40px;background:#f0f4f8;border-radius:50% 50% 0 0}
.wifi-icon{width:52px;height:52px;margin:0 auto 10px;display:block}
.header h1{color:#fff;font-size:1.5rem;font-weight:700;margin-bottom:4px}
.header p{color:rgba(255,255,255,.8);font-size:.875rem}
.container{max-width:480px;margin:-20px auto 0;padding:0 16px 32px;position:relative;z-index:1}
.section{display:none}
.section.active{display:block}
/* Loading skeleton */
.skeleton{background:#e2e8f0;border-radius:12px;height:120px;margin-bottom:12px;animation:pulse 1.5s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}
/* Plan cards */
.plans-grid{display:grid;gap:12px}
.plan-card{background:#fff;border-radius:16px;padding:20px;box-shadow:0 1px 4px rgba(0,0,0,.08);cursor:pointer;border:2px solid transparent;transition:border-color .2s,transform .15s,box-shadow .15s}
.plan-card:hover,.plan-card:active{border-color:#1a56db;transform:translateY(-1px);box-shadow:0 4px 16px rgba(26,86,219,.15)}
.plan-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px}
.plan-name{font-size:1rem;font-weight:600;color:#1a202c}
.plan-price{font-size:1.5rem;font-weight:700;color:#1a56db;line-height:1}
.plan-currency{font-size:.75rem;color:#64748b;font-weight:500}
.plan-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.tag{background:#e0f2fe;color:#0369a1;font-size:.72rem;font-weight:600;padding:3px 8px;border-radius:20px}
.tag.speed{background:#f0fdf4;color:#166534}
.plan-desc{margin-top:8px;font-size:.8rem;color:#64748b;line-height:1.4}
/* Phone input */
.card{background:#fff;border-radius:16px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
.card h2{font-size:1.1rem;font-weight:700;margin-bottom:4px;color:#1a202c}
.card p.subtitle{font-size:.85rem;color:#64748b;margin-bottom:20px;line-height:1.4}
.plan-summary{background:#eff6ff;border-radius:10px;padding:12px 16px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center}
.plan-summary .ps-name{font-weight:600;color:#1e40af;font-size:.9rem}
.plan-summary .ps-price{font-weight:700;color:#1a56db;font-size:1.1rem}
.input-group{margin-bottom:16px}
label{display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px}
.phone-wrap{display:flex;border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden;transition:border-color .2s}
.phone-wrap:focus-within{border-color:#1a56db;box-shadow:0 0 0 3px rgba(26,86,219,.1)}
.phone-prefix{background:#f8fafc;padding:0 12px;display:flex;align-items:center;font-size:.9rem;font-weight:600;color:#374151;border-right:1.5px solid #e2e8f0}
input[type=tel]{border:none;outline:none;padding:12px 14px;font-size:1rem;width:100%;background:#fff;color:#1a202c}
input[type=tel]::placeholder{color:#94a3b8}
.btn{width:100%;padding:14px;font-size:1rem;font-weight:700;border:none;border-radius:10px;cursor:pointer;transition:opacity .2s,transform .1s}
.btn:active{transform:scale(.98)}
.btn-primary{background:linear-gradient(135deg,#1a56db,#2563eb);color:#fff}
.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none}
.btn-ghost{background:transparent;color:#64748b;font-size:.85rem;margin-top:8px}
/* Spinner */
.spinner-wrap{text-align:center;padding:40px 20px}
.spinner{width:56px;height:56px;border:4px solid #e0f2fe;border-top-color:#1a56db;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 20px}
@keyframes spin{to{transform:rotate(360deg)}}
.spinner-wrap h3{font-size:1.1rem;font-weight:700;color:#1a202c;margin-bottom:6px}
.spinner-wrap p{font-size:.85rem;color:#64748b;line-height:1.5}
.dots span{animation:dot 1.4s linear infinite}
.dots span:nth-child(2){animation-delay:.2s}
.dots span:nth-child(3){animation-delay:.4s}
@keyframes dot{0%,80%,100%{opacity:.3}40%{opacity:1}}
/* Success */
.success-wrap{text-align:center;padding:40px 20px}
.check-circle{width:80px;height:80px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;margin:0 auto 20px;display:flex;align-items:center;justify-content:center;animation:popIn .4s ease}
@keyframes popIn{0%{transform:scale(0)}70%{transform:scale(1.1)}100%{transform:scale(1)}}
.check-circle svg{width:40px;height:40px;color:#fff}
.success-wrap h2{font-size:1.3rem;font-weight:800;color:#15803d;margin-bottom:6px}
.success-wrap p{font-size:.9rem;color:#64748b;line-height:1.5;margin-bottom:20px}
/* Error */
.error-wrap{text-align:center;padding:30px 20px}
.error-icon{width:60px;height:60px;background:#fef2f2;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center}
.error-wrap h3{font-size:1.05rem;font-weight:700;color:#dc2626;margin-bottom:6px}
.error-wrap p{font-size:.85rem;color:#64748b;margin-bottom:20px}
/* No plans */
.no-plans{text-align:center;padding:40px 20px;color:#64748b}
.no-plans svg{width:56px;height:56px;margin:0 auto 16px;color:#94a3b8}
/* Footer */
.footer{text-align:center;padding:20px;font-size:.75rem;color:#94a3b8}
.footer a{color:#1a56db;text-decoration:none}
</style>
</head>
<body>

<div class="header">
  <svg class="wifi-icon" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5 12.55a11 11 0 0 1 14.08 0"/>
    <path d="M1.42 9a16 16 0 0 1 21.16 0"/>
    <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
    <line x1="12" y1="20" x2="12.01" y2="20"/>
  </svg>
  <h1 id="wifiName">{{ $routerName }}</h1>
  <p>Choose a plan to get online</p>
</div>

<div class="container">

  {{-- Loading state --}}
  <div id="sec-loading" class="section active">
    <div class="skeleton"></div>
    <div class="skeleton" style="height:100px;opacity:.7"></div>
    <div class="skeleton" style="height:80px;opacity:.5"></div>
  </div>

  {{-- Plans list --}}
  <div id="sec-plans" class="section">
    <div id="plansList" class="plans-grid"></div>
  </div>

  {{-- No plans --}}
  <div id="sec-no-plans" class="section">
    <div class="no-plans">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
      </svg>
      <p>No active plans available.<br>Contact the hotspot operator.</p>
    </div>
  </div>

  {{-- Phone entry --}}
  <div id="sec-phone" class="section">
    <div class="card">
      <h2>Enter your phone number</h2>
      <p class="subtitle">A USSD prompt will appear on your phone. Enter your PIN to pay.</p>
      <div id="phonePlanSummary" class="plan-summary">
        <span class="ps-name" id="psName"></span>
        <span class="ps-price" id="psPrice"></span>
      </div>
      <div class="input-group">
        <label for="phoneInput">Mobile number (M-Pesa / Tigo / Airtel)</label>
        <div class="phone-wrap">
          <span class="phone-prefix">+255</span>
          <input type="tel" id="phoneInput" placeholder="7XX XXX XXX" maxlength="9" inputmode="numeric" pattern="[0-9]*">
        </div>
      </div>
      <button class="btn btn-primary" id="payBtn" onclick="submitPayment()">Pay Now</button>
      <button class="btn btn-ghost" onclick="showSection('sec-plans')">&#8592; Back to plans</button>
    </div>
  </div>

  {{-- Paying / waiting --}}
  <div id="sec-paying" class="section">
    <div class="card spinner-wrap">
      <div class="spinner"></div>
      <h3>Waiting for payment<span class="dots"><span>.</span><span>.</span><span>.</span></span></h3>
      <p>Check your phone for the USSD prompt and enter your PIN.</p>
      <p style="margin-top:12px;font-size:.8rem;color:#94a3b8">This will update automatically</p>
    </div>
  </div>

  {{-- Success --}}
  <div id="sec-success" class="section">
    <div class="card success-wrap">
      <div class="check-circle">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <h2>You're Connected!</h2>
      <p>Payment confirmed. Tap the button below to start browsing.</p>
      <button class="btn btn-primary" id="browseBtn" onclick="startBrowsing()">Start Browsing</button>
    </div>
  </div>

  {{-- Failed --}}
  <div id="sec-failed" class="section">
    <div class="card error-wrap">
      <div class="error-icon">
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </div>
      <h3>Payment Failed</h3>
      <p id="failedMsg">Payment was not successful. Please try again.</p>
      <button class="btn btn-primary" onclick="showSection('sec-plans')">Try Again</button>
    </div>
  </div>

</div>

<div class="footer">Powered by <a href="#">SKYmanager WiFi</a></div>

<script>
// ── Constants injected at PHP generation time ─────────────────────────────────
const VPS_URL   = '{{ $vpsUrl }}';
const ROUTER_ID = '{{ $routerId }}';

// ── MikroTik macros — left as literals here, MikroTik expands them at serve time
// When MikroTik serves this file to a connecting device it substitutes:
//   $(mac)              → client device MAC address  e.g. AA:BB:CC:DD:EE:FF
//   $(ip)               → client device IP address   e.g. 192.168.88.10
//   $(link-login-only)  → hotspot login endpoint     e.g. http://192.168.88.1/login
//   $(link-orig)        → original URL the user was trying to visit
const USER_MAC  = '$(mac)';
const USER_IP   = '$(ip)';
const LOGIN_URL = '$(link-login-only)';
const ORIG_URL  = '$(link-orig)';

// ── App state ─────────────────────────────────────────────────────────────────
var selectedPlan = null;
var pollInterval = null;
var currentRef   = null;

// ── API base paths ────────────────────────────────────────────────────────────
var API = {
  packages:   VPS_URL + '/api/local-portal/packages',
  session:    VPS_URL + '/api/local-portal/session/start',
  initiate:   VPS_URL + '/api/local-portal/payment/initiate',
  status:     VPS_URL + '/api/local-portal/payment/status/',
  authorize:  VPS_URL + '/api/local-portal/mikrotik/authorize',
};

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  startPortalSession();
});

/**
 * Step 1: announce session to VPS (validates router, logs the visit),
 * then immediately load the plan list.
 */
function startPortalSession() {
  showSection('sec-loading');

  // Fire-and-forget — we don't block plan loading on this
  if (ROUTER_ID) {
    fetch(API.session, {
      method: 'POST',
      headers: jsonHeaders(),
      body: JSON.stringify({ router_id: ROUTER_ID, mac: USER_MAC, ip: USER_IP })
    }).catch(function () { /* non-fatal */ });
  }

  loadPlans();
}

function showSection(id) {
  document.querySelectorAll('.section').forEach(function (s) { s.classList.remove('active'); });
  var el = document.getElementById(id);
  if (el) { el.classList.add('active'); }
}

// ── Step 2: Load packages from VPS ───────────────────────────────────────────
function loadPlans() {
  fetch(API.packages + '?router_id=' + encodeURIComponent(ROUTER_ID), {
    method: 'GET',
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    var plans = data.plans || [];
    if (plans.length === 0) { showSection('sec-no-plans'); return; }

    // Update router name in header if returned
    if (data.router_name) {
      var el = document.getElementById('wifiName');
      if (el) { el.textContent = data.router_name; }
    }

    renderPlans(plans);
    showSection('sec-plans');
  })
  .catch(function () {
    showSection('sec-no-plans');
  });
}

function renderPlans(plans) {
  var grid = document.getElementById('plansList');
  grid.innerHTML = '';
  plans.forEach(function (p) {
    var card = document.createElement('div');
    card.className = 'plan-card';
    card.innerHTML =
      '<div class="plan-top">' +
        '<span class="plan-name">' + esc(p.name) + '</span>' +
        '<div style="text-align:right">' +
          '<span class="plan-currency">TZS </span>' +
          '<span class="plan-price">' + formatPrice(p.price) + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="plan-tags">' +
        '<span class="tag">' + esc(p.duration_label) + '</span>' +
        '<span class="tag speed">' + esc(p.speed_label) + '</span>' +
      '</div>' +
      (p.description ? '<p class="plan-desc">' + esc(p.description) + '</p>' : '');
    card.addEventListener('click', function () { selectPlan(p); });
    grid.appendChild(card);
  });
}

function selectPlan(plan) {
  selectedPlan = plan;
  document.getElementById('psName').textContent  = plan.name;
  document.getElementById('psPrice').textContent = 'TZS ' + formatPrice(plan.price);
  document.getElementById('phoneInput').value    = '';
  showSection('sec-phone');
}

// ── Step 3: Initiate payment ──────────────────────────────────────────────────
function submitPayment() {
  var rawPhone = document.getElementById('phoneInput').value.replace(/\D/g, '');

  if (rawPhone.length < 9) {
    alert('Please enter a valid 9-digit phone number (e.g. 712345678).');
    return;
  }

  var phone = '255' + rawPhone.slice(-9);
  var btn   = document.getElementById('payBtn');
  btn.disabled    = true;
  btn.textContent = 'Sending...';

  // Field names match POST /api/local-portal/payment/initiate validation
  var body = {
    router_id: ROUTER_ID,
    plan_id:   selectedPlan.id,
    mac:       USER_MAC,
    ip:        USER_IP,
    phone:     phone
  };

  fetch(API.initiate, {
    method:  'POST',
    headers: jsonHeaders(),
    body:    JSON.stringify(body)
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    btn.disabled    = false;
    btn.textContent = 'Pay Now';

    if (data.error) {
      alert(data.error);
      return;
    }

    currentRef = data.reference;
    showSection('sec-paying');
    startPolling(currentRef);
  })
  .catch(function () {
    btn.disabled    = false;
    btn.textContent = 'Pay Now';
    alert('Network error. Please check your connection and try again.');
  });
}

// ── Step 4: Poll payment status ───────────────────────────────────────────────
function startPolling(reference) {
  stopPolling();
  var attempts    = 0;
  var maxAttempts = 60; // 3 minutes at 3 s intervals

  pollInterval = setInterval(function () {
    attempts++;

    if (attempts > maxAttempts) {
      stopPolling();
      setFailed('Payment timed out. If money was deducted, please contact support.');
      return;
    }

    fetch(API.status + encodeURIComponent(reference), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.status === 'authorized') {
        stopPolling();
        onAuthorized();
      } else if (data.status === 'success') {
        // Payment confirmed but MikroTik authorization hasn't completed yet.
        // Call the explicit authorize endpoint as a fallback.
        callAuthorize(reference);
      } else if (data.status === 'failed') {
        stopPolling();
        setFailed(data.message || 'Payment was not successful. Please try again.');
      }
      // 'pending' — keep polling
    })
    .catch(function () { /* network hiccup — silently retry */ });
  }, 3000);
}

function stopPolling() {
  if (pollInterval) { clearInterval(pollInterval); pollInterval = null; }
}

/**
 * Explicit authorize fallback — called when status = 'success' but
 * auto-authorization inside paymentStatus() hasn't fired yet.
 */
function callAuthorize(reference) {
  fetch(API.authorize, {
    method:  'POST',
    headers: jsonHeaders(),
    body:    JSON.stringify({ reference: reference })
  })
  .then(function (r) { return r.json(); })
  .then(function (data) {
    if (data.status === 'authorized') {
      stopPolling();
      onAuthorized();
    }
    // Otherwise polling will catch it on the next cycle
  })
  .catch(function () { /* non-fatal — polling will retry */ });
}

// ── Step 5: Authorized — show success + redirect ──────────────────────────────
function onAuthorized() {
  showSection('sec-success');
  // Auto-start browsing after 4 seconds
  setTimeout(startBrowsing, 4000);
}

function startBrowsing() {
  stopPolling();
  var isMikrotikMacro = function (v) { return !v || v.indexOf('$(') === 0; };

  if (isMikrotikMacro(LOGIN_URL)) {
    // Running in a browser outside MikroTik — just reload
    window.location.reload();
    return;
  }

  var dst = !isMikrotikMacro(ORIG_URL) ? ORIG_URL : 'http://www.google.com';
  // Navigate to MikroTik login endpoint — MAC binding auto-logs the device in
  window.location.href = LOGIN_URL + '?dst=' + encodeURIComponent(dst);
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function setFailed(msg) {
  document.getElementById('failedMsg').textContent = msg;
  showSection('sec-failed');
}

function jsonHeaders() {
  return {
    'Content-Type':     'application/json',
    'Accept':           'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  };
}

function esc(str) {
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function formatPrice(n) {
  return Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
}
</script>
</body>
</html>
