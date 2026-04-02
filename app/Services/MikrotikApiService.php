<?php

namespace App\Services;

use App\Models\Router;
use Exception;
use Illuminate\Support\Facades\Log;

class MikrotikApiService
{
    private mixed $socket = null;

    private bool $isConnected = false;

    /**
     * Connect to a MikroTik router via the RouterOS API.
     *
     * @throws Exception
     */
    public function connect(Router $router): static
    {
        $this->socket = @fsockopen(
            $router->ip_address,
            $router->api_port,
            $errno,
            $errstr,
            5
        );

        if (! $this->socket) {
            throw new Exception("Failed to connect to router [{$router->name}]: {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, 5);

        $this->login($router->api_username, $router->api_password);

        $this->isConnected = true;

        return $this;
    }

    /**
     * Disconnect and close the socket.
     */
    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }

        $this->isConnected = false;
    }

    /**
     * Add a hotspot user to a MikroTik router.
     *
     * @throws Exception
     */
    public function addHotspotUser(
        string $username,
        string $password,
        string $profile,
        string $mac
    ): bool {
        $this->ensureConnected();

        $response = $this->sendCommand([
            '/ip/hotspot/user/add',
            '=name='.$username,
            '=password='.$password,
            '=profile='.$profile,
            '=mac-address='.$mac,
        ]);

        return ! empty($response) && ! $this->hasError($response);
    }

    /**
     * Remove a hotspot user from a MikroTik router.
     *
     * @throws Exception
     */
    public function removeHotspotUser(string $username): bool
    {
        $this->ensureConnected();

        $findResponse = $this->sendCommand([
            '/ip/hotspot/user/print',
            '?name='.$username,
        ]);

        foreach ($findResponse as $item) {
            if (isset($item['.id'])) {
                $this->sendCommand([
                    '/ip/hotspot/user/remove',
                    '=.id='.$item['.id'],
                ]);
            }
        }

        return true;
    }

    /**
     * Retrieve current active hotspot sessions and interface stats.
     *
     * @return array{sessions: list<array<string, string>>, interfaces: list<array<string, string>>}
     *
     * @throws Exception
     */
    public function getRouterStats(): array
    {
        $this->ensureConnected();

        $sessions = $this->sendCommand(['/ip/hotspot/active/print']);
        $interfaces = $this->sendCommand(['/interface/print']);

        return [
            'sessions' => $sessions,
            'interfaces' => $interfaces,
        ];
    }

    /**
     * Authenticate with the RouterOS API using plain-text login (API v2).
     * Supports RouterOS 7.x and later. MD5-challenge (v6) is intentionally removed.
     *
     * @throws Exception
     */
    private function login(string $username, string $password): void
    {
        $response = $this->sendCommand([
            '/login',
            '=name='.$username,
            '=password='.$password,
        ]);

        foreach ($response as $row) {
            if (isset($row['message'])) {
                throw new Exception('MikroTik authentication failed: '.$row['message']);
            }
        }
    }

    /**
     * Send a command array to the router and return parsed response rows.
     *
     * @param  list<string>  $words
     * @return list<array<string, string>>
     *
     * @throws Exception
     */
    private function sendCommand(array $words): array
    {
        foreach ($words as $word) {
            $this->writeWord($word);
        }

        $this->writeWord('');

        return $this->readResponse();
    }

    /**
     * Write a single API word to the socket.
     *
     * @throws Exception
     */
    private function writeWord(string $word): void
    {
        $len = strlen($word);

        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF).chr($len & 0xFF));
        } else {
            $len |= 0xC00000;
            fwrite($this->socket, chr(($len >> 16) & 0xFF).chr(($len >> 8) & 0xFF).chr($len & 0xFF));
        }

        fwrite($this->socket, $word);
    }

    /**
     * Read the full response from the socket until a !done or !fatal.
     *
     * @return list<array<string, string>>
     *
     * @throws Exception
     */
    private function readResponse(): array
    {
        $rows = [];
        $currentRow = [];

        while (true) {
            $word = $this->readWord();

            if ($word === '!done') {
                if (! empty($currentRow)) {
                    $rows[] = $currentRow;
                }
                break;
            }

            if ($word === '!fatal') {
                $msg = $this->readWord();
                throw new Exception('MikroTik fatal: '.$msg);
            }

            if ($word === '!re' || $word === '!trap') {
                if (! empty($currentRow)) {
                    $rows[] = $currentRow;
                }
                $currentRow = [];

                continue;
            }

            if (str_starts_with($word, '=')) {
                [$key, $value] = explode('=', ltrim($word, '='), 2) + ['', ''];
                $currentRow[$key] = $value;
            }
        }

        return $rows;
    }

    /**
     * Read a single word from the socket.
     *
     * @throws Exception
     */
    private function readWord(): string
    {
        $firstByte = ord($this->readBytes(1));

        if ($firstByte & 0x80) {
            if (($firstByte & 0xC0) === 0x80) {
                $len = (($firstByte & 0x3F) << 8) | ord($this->readBytes(1));
            } elseif (($firstByte & 0xE0) === 0xC0) {
                $len = (($firstByte & 0x1F) << 16)
                    | (ord($this->readBytes(1)) << 8)
                    | ord($this->readBytes(1));
            } else {
                throw new Exception('MikroTik API: unsupported word length encoding.');
            }
        } else {
            $len = $firstByte;
        }

        return $len > 0 ? $this->readBytes($len) : '';
    }

    /**
     * Read exactly $length bytes from the socket.
     *
     * @throws Exception
     */
    private function readBytes(int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = fread($this->socket, $length - strlen($data));

            if ($chunk === false || $chunk === '') {
                throw new Exception('MikroTik API: connection lost while reading.');
            }

            $data .= $chunk;
        }

        return $data;
    }

    /**
     * Check if a response contains a !trap error.
     *
     * @param  list<array<string, string>>  $response
     */
    private function hasError(array $response): bool
    {
        foreach ($response as $row) {
            if (isset($row['message'])) {
                Log::warning('MikroTik API error: '.$row['message']);

                return true;
            }
        }

        return false;
    }

    /**
     * Get system resource stats (CPU, memory, uptime, etc.).
     *
     * @return array<string, string>
     *
     * @throws Exception
     */
    public function getSystemResources(): array
    {
        $this->ensureConnected();

        $response = $this->sendCommand(['/system/resource/print']);

        return $response[0] ?? [];
    }

    /**
     * Get all active hotspot sessions.
     *
     * @return list<array<string, string>>
     *
     * @throws Exception
     */
    public function getActiveHotspotSessions(): array
    {
        $this->ensureConnected();

        return $this->sendCommand(['/ip/hotspot/active/print']);
    }

    /**
     * Get all hotspot user entries.
     *
     * @return list<array<string, string>>
     *
     * @throws Exception
     */
    public function getHotspotUsers(): array
    {
        $this->ensureConnected();

        return $this->sendCommand(['/ip/hotspot/user/print']);
    }

    /**
     * Kick (disconnect) an active hotspot session by its .id.
     *
     * @throws Exception
     */
    public function kickHotspotSession(string $sessionId): void
    {
        $this->ensureConnected();

        $this->sendCommand([
            '/ip/hotspot/active/remove',
            '=.id='.$sessionId,
        ]);
    }

    /**
     * Get all hotspot user profiles.
     *
     * @return list<array<string, string>>
     *
     * @throws Exception
     */
    public function getHotspotProfiles(): array
    {
        $this->ensureConnected();

        return $this->sendCommand(['/ip/hotspot/user/profile/print']);
    }

    /**
     * Get interface stats (bytes in/out, packets).
     *
     * @return list<array<string, string>>
     *
     * @throws Exception
     */
    public function getInterfaceStats(): array
    {
        $this->ensureConnected();

        return $this->sendCommand(['/interface/print', 'stats']);
    }

    /**
     * Ping a host from the router and return results.
     *
     * @return list<array<string, string>>
     *
     * @throws Exception
     */
    public function pingHost(string $host, int $count = 4): array
    {
        $this->ensureConnected();

        return $this->sendCommand([
            '/ping',
            '=address='.$host,
            '=count='.(string) $count,
        ]);
    }

    /**
     * Add or update a hotspot user entry (MAC-based).
     *
     * @throws Exception
     */
    public function setHotspotUserComment(string $username, string $comment): void
    {
        $this->ensureConnected();

        $users = $this->sendCommand([
            '/ip/hotspot/user/print',
            '?name='.$username,
        ]);

        foreach ($users as $user) {
            if (isset($user['.id'])) {
                $this->sendCommand([
                    '/ip/hotspot/user/set',
                    '=.id='.$user['.id'],
                    '=comment='.$comment,
                ]);
            }
        }
    }

    /**
     * Create or update a hotspot user profile with bandwidth limits.
     * Profile name matches the BillingPlan name.
     *
     * @throws Exception
     */
    public function syncHotspotProfile(string $profileName, int $uploadMbps, int $downloadMbps): void
    {
        $this->ensureConnected();

        $rateLimit = "{$uploadMbps}M/{$downloadMbps}M";

        $existing = $this->sendCommand([
            '/ip/hotspot/user/profile/print',
            '?name='.$profileName,
        ]);

        if (! empty($existing)) {
            $id = $existing[0]['.id'] ?? null;
            if ($id) {
                $this->sendCommand([
                    '/ip/hotspot/user/profile/set',
                    '=.id='.$id,
                    '=rate-limit='.$rateLimit,
                ]);
            }
        } else {
            $this->sendCommand([
                '/ip/hotspot/user/profile/add',
                '=name='.$profileName,
                '=rate-limit='.$rateLimit,
                '=shared-users=1',
            ]);
        }
    }

    /**
     * Configure the router's hotspot for proper Captive Portal Detection (CPD).
     *
     * Strategy (works on RouterOS v6 + v7):
     *  1. Upload a local login.html to flash/hotspot/ that immediately JS-redirects
     *     to the external Laravel portal URL, passing $(mac)/$(ip)/$(link-orig-esc)
     *     as query parameters.
     *  2. Set the hotspot profile html-directory to the local flash/hotspot folder.
     *  3. Enable cookie login for seamless re-login after session expiry.
     *
     * CPD probes (HTTP) from Android/Windows/iOS hit MikroTik, receive a redirect
     * to the LOCAL HTTP page (on the hotspot gateway IP), which makes the OS
     * recognise a captive portal and pop up the browser window. The local page
     * then JS-redirects to the external HTTPS Laravel portal.
     *
     * @throws Exception
     */
    public function configureCaptivePortal(string $portalUrl): void
    {
        $this->ensureConnected();

        $html = $this->buildLoginHtml($portalUrl);

        $this->sendCommand([
            '/file/add',
            '=name=hotspot/login.html',
            '=contents='.$html,
        ]);

        $profiles = $this->sendCommand(['/ip/hotspot/profile/print']);

        foreach ($profiles as $profile) {
            if (! isset($profile['.id'])) {
                continue;
            }

            $this->sendCommand([
                '/ip/hotspot/profile/set',
                '=.id='.$profile['.id'],
                '=html-directory=hotspot',
                '=login-by=http-chap,http-pap,cookie',
                '=http-cookie-lifetime=30m',
            ]);
        }

        $this->addWalledGardenIfMissing($portalUrl);
    }

    /**
     * Build the minimal CPD-compatible login.html for the MikroTik hotspot.
     * MikroTik expands $(mac), $(ip), $(link-orig-esc) server-side before serving.
     */
    public function buildLoginHtml(string $portalUrl): string
    {
        $base = rtrim($portalUrl, '/');
        $redirect = $base.'?mac=$(mac)&ip=$(ip)&username=$(username)&orig=$(link-orig-esc)';

        return '<!DOCTYPE html><html><head>'
            .'<meta charset="UTF-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Connecting...</title>'
            .'<script>window.location.replace("'.$redirect.'");</script>'
            .'<meta http-equiv="refresh" content="0;url='.$redirect.'">'
            .'</head><body style="font-family:sans-serif;text-align:center;padding:3em">'
            .'<p>Redirecting to WiFi portal&hellip;</p>'
            .'<p><a href="'.$redirect.'">Tap here if not redirected</a></p>'
            .'</body></html>';
    }

    /**
     * Add walled-garden entries for the portal domain (skip if already present).
     *
     * @throws Exception
     */
    private function addWalledGardenIfMissing(string $portalUrl): void
    {
        $host = parse_url($portalUrl, PHP_URL_HOST) ?? '';

        if (! $host) {
            return;
        }

        $existing = $this->sendCommand(['/ip/hotspot/walled-garden/print']);
        $existingHosts = array_column($existing, 'dst-host');

        if (! in_array($host, $existingHosts, true)) {
            $this->sendCommand([
                '/ip/hotspot/walled-garden/add',
                '=dst-host='.$host,
                '=action=allow',
                '=comment=SKYmanager Portal (CPD)',
            ]);
        }

        $wildcard = '*.'.$host;
        if (! in_array($wildcard, $existingHosts, true)) {
            $this->sendCommand([
                '/ip/hotspot/walled-garden/add',
                '=dst-host='.$wildcard,
                '=action=allow',
                '=comment=SKYmanager Portal wildcard (CPD)',
            ]);
        }
    }

    /**
     * Generate a RouterOS provisioning script for Zero-Touch Provisioning.
     * Saves a fresh random API user password to the router record before returning.
     * The script uses RouterOS scripting to detect v6 vs v7 and apply correct syntax.
     *
     * @throws Exception
     */
    public function generateProvisioningScript(Router $router): string
    {
        $apiPassword = bin2hex(random_bytes(12));
        $router->update(['ztp_api_password' => $apiPassword]);

        $vpsIp = config('services.ztp.vps_ip');
        $portalDomain = config('services.ztp.portal_domain');
        $vpnSubnet = config('services.ztp.vpn_subnet');
        $sstpSecret = config('services.ztp.sstp_secret');
        $identity = preg_replace('/[^a-zA-Z0-9\-]/', '-', $router->name);
        $portalUrl = config('app.url').'/portal';

        return implode("\n", [
            '# ============================================================',
            '# SKYmanager Zero-Touch Provisioning Script',
            "# Router : {$router->name}",
            '# Generated: '.now()->toDateTimeString(),
            '# Supports : RouterOS v6 and v7',
            '# ============================================================',
            '',
            '# --- 0. Detect RouterOS major version ---',
            ':local rosVer [/system package get "routeros" version]',
            ':local rosMajor [:tonum [:pick $rosVer 0 [:find $rosVer "."]]]',
            ':log info ("SKYmanager ZTP: RouterOS major version = " . $rosMajor)',
            '',
            '# --- 1. Identity & Security ---',
            "/system identity set name=\"{$identity}\"",
            '/user add name=sky-api password="'.$apiPassword.'" group=full comment="SKYmanager API User"',
            '/user remove [find name=admin where name!=sky-api]',
            '',
            '# --- 2. VPN Tunnel (SSTP back to VPS) ---',
            ':if ($rosMajor >= 7) do={',
            '    /interface sstp-client add name=vpn-sky connect-to="'.$vpsIp.'"\\',
            '        user="'.$identity.'" password="'.$sstpSecret.'"\\',
            '        profile=default-encryption disabled=no comment="SKYmanager VPN"',
            '} else={',
            '    /interface sstp-client add name=vpn-sky connect-to="'.$vpsIp.'"\\',
            '        user="'.$identity.'" password="'.$sstpSecret.'"\\',
            '        profile=default-encryption disabled=no comment="SKYmanager VPN"',
            '}',
            '',
            '# --- 3. Hotspot Configuration (CPD-compatible) ---',
            ':if ($rosMajor >= 7) do={',
            '    /ip hotspot profile set [find] html-directory=hotspot',
            "    /ip hotspot profile set [find] dns-name=\"{$portalDomain}\"",
            '    /ip hotspot profile set [find] login-by=http-chap,http-pap,cookie',
            '    /ip hotspot profile set [find] http-cookie-lifetime=30m',
            '} else={',
            '    /ip hotspot profile set [find] html-directory=flash/hotspot',
            "    /ip hotspot profile set [find] dns-name=\"{$portalDomain}\"",
            '    /ip hotspot profile set [find] login-by=cookie,http-chap,http-pap',
            '}',
            '',
            '# --- 3b. Upload CPD login.html (via tool fetch from portal server) ---',
            ':do {',
            "    /tool fetch url=\"{$portalUrl}/hotspot-login.html\" dst-path=hotspot/login.html",
            '} on-error={',
            '    :log warning "SKYmanager ZTP: could not fetch login.html - set up manually"',
            '}',
            '',
            '# --- 4. Walled Garden (ClickPesa + Portal) ---',
            '/ip hotspot walled-garden add dst-host="*.clickpesa.com" action=allow comment="ClickPesa"',
            '/ip hotspot walled-garden add dst-host="api.clickpesa.com" action=allow comment="ClickPesa API"',
            "/ip hotspot walled-garden add dst-host=\"*.{$portalDomain}\" action=allow comment=\"SKYmanager Portal\"",
            "/ip hotspot walled-garden add dst-host=\"{$portalDomain}\" action=allow comment=\"SKYmanager Portal (bare)\"",
            '/ip hotspot walled-garden ip add dst-address=0.0.0.0/0 protocol=tcp dst-port=443 action=allow comment="HTTPS passthrough"',
            '',
            '# --- 5. API Service (restricted to VPN subnet) ---',
            '/ip service set api port=8728 disabled=no',
            "/ip service set api address=\"{$vpnSubnet}\"",
            ':if ($rosMajor >= 7) do={',
            '    /ip service disable telnet',
            '    /ip service disable www',
            '} else={',
            '    /ip service disable telnet',
            '    /ip service disable ftp',
            '    /ip service disable www',
            '}',
            '',
            '# --- 6. Firewall: block API from WAN ---',
            '/ip firewall filter add chain=input protocol=tcp dst-port=8728 src-address=!'.$vpnSubnet.' action=drop comment="Block API outside VPN" place-before=0',
            '',
            '# ============================================================',
            '# Done. Router will connect to VPN and become reachable from VPS.',
            '# Apply CPD fix via admin panel: Routers → Apply CPD Fix.',
            '# ============================================================',
        ]);
    }

    /**
     * Check whether the SSTP VPN client tunnel is up on the connected router.
     * Must call connect() before invoking this method.
     *
     * @throws Exception
     */
    public function checkVpnStatus(): bool
    {
        $this->ensureConnected();

        $response = $this->sendCommand([
            '/interface/sstp-client/print',
            '?name=vpn-sky',
        ]);

        foreach ($response as $row) {
            if (isset($row['running']) && $row['running'] === 'true') {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a comprehensive, one-click full MikroTik setup script.
     *
     * The returned string is a complete RouterOS CLI script the customer pastes
     * into New Terminal. It covers every layer: WireGuard VPN, bridge, hotspot,
     * CPD login.html, DNS spoofing, DHCP Option 114, API user, and firewall.
     *
     * All sections have Kiswahili comments for customer friendliness.
     * The script is idempotent — safe to run multiple times.
     * RouterOS v6/v7 differences are handled via :local rosMajor detection.
     *
     * No API connection required — pure string generation from model data.
     */
    public function generateFullSetupScript(Router $router): string
    {
        $apiPassword = bin2hex(random_bytes(12));
        $router->update(['ztp_api_password' => $apiPassword]);

        $identity = preg_replace('/[^a-zA-Z0-9\-]/', '-', $router->name);
        $portalUrl = $router->user ? $router->user->portalUrl() : rtrim(config('app.url'), '/').'/portal';
        $portalDomain = config('services.ztp.portal_domain', 'micro.spotbox.online');
        $vpsIp = config('services.ztp.vps_ip', '');
        $apiSubnet = config('services.wireguard.api_subnet', '10.10.0.0/24');
        $wgEndpoint = config('services.wireguard.vps_endpoint', '');
        $wgVpsPubKey = config('services.wireguard.vps_public_key', '');
        $wgListenPort = (int) config('services.wireguard.listen_port', 51820);

        $wgAddress = $router->wg_address ?: '10.10.0.X/32';
        $hotspotIface = $router->hotspot_interface ?: 'bridge';
        $hotspotSsid = $router->hotspot_ssid ?: 'PEACE';
        $hotspotGw = $router->hotspot_gateway ?: '192.168.88.1';
        $hotspotNet = $router->hotspot_network ?: '192.168.88.0/24';
        $dhcpPool = $this->deriveDhcpPool($hotspotNet, $hotspotGw);
        $loginHtmlUrl = config('app.url').'/hotspot-login.html';

        $lines = [];

        $lines[] = '# ================================================================';
        $lines[] = '# 🛜  SKYmanager — Full Automatic MikroTik Setup Script';
        $lines[] = "#     Router   : {$router->name}";
        $lines[] = "#     VPS      : {$vpsIp}";
        $lines[] = "#     Portal   : {$portalUrl}";
        $lines[] = '#     Tarehe   : '.now()->toDateTimeString();
        $lines[] = '# ================================================================';
        $lines[] = '# ⚠️  PASTE HAPA KWENYE: MikroTik → New Terminal';
        $lines[] = '#     Script hii ni salama kurun mara nyingi (idempotent).';
        $lines[] = '# ================================================================';
        $lines[] = '';

        $lines[] = '# ── BADILISHA HIZI KULINGANA NA ROUTER YAKO ─────────────────────';
        $lines[] = '# Angalia /interface print kwa majina halisi ya interfaces';
        $lines[] = ':local wifiIface    "wlan1"         # Interface ya WiFi';
        $lines[] = ':local wanIface     "ether1"        # Interface ya WAN/Internet';
        $lines[] = ":local bridgeName   \"{$hotspotIface}\"           # Bridge interface ya hotspot";
        $lines[] = ":local hotspotIP    \"{$hotspotGw}\"      # IP ya Gateway";
        $lines[] = ":local hotspotNet   \"{$hotspotNet}\"  # Subnet ya wateja";
        $lines[] = ":local dhcpPool     \"{$dhcpPool}\"";
        $lines[] = ":local ssid         \"{$hotspotSsid}\"         # Jina la WiFi (SSID)";
        $lines[] = ":local wgAddress    \"{$wgAddress}\"      # WireGuard tunnel IP ya router hii";
        $lines[] = ":local wgEndpoint   \"{$wgEndpoint}\"  # VPS IP:Port ya WireGuard";
        $lines[] = ":local wgVpsPubKey  \"{$wgVpsPubKey}\"";
        $lines[] = ":local wgListenPort {$wgListenPort}";
        $lines[] = ":local apiSubnet    \"{$apiSubnet}\"  # VPN subnet (kwa API access)";
        $lines[] = ":local portalDomain \"{$portalDomain}\"";
        $lines[] = ":local portalUrl    \"{$portalUrl}\"";
        $lines[] = ":local loginHtmlUrl \"{$loginHtmlUrl}\"";
        $lines[] = ":local routerName   \"{$identity}\"";
        $lines[] = ':local apiUser      "sky-api"';
        $lines[] = ":local apiPassword  \"{$apiPassword}\"";
        $lines[] = '';
        $lines[] = '# ── GUNDUA TOLEO LA RouterOS (v6 au v7) — lazima kwanza ──────────';
        $lines[] = '# Hii lazima iwe KABLA ya hatua zote zinazotumia $rosMajor';
        $lines[] = ':local rosVer [/system package get "routeros" version]';
        $lines[] = ':local rosMajor [:tonum [:pick $rosVer 0 [:find $rosVer "."]]]';
        $lines[] = ':put "================================================================"';
        $lines[] = ':put ("  RouterOS toleo: " . $rosVer . "  (major: " . $rosMajor . ")")';
        $lines[] = ':put "================================================================"';
        $lines[] = ':log info ("SKYmanager: RouterOS " . $rosVer . " imegunduliwa")';
        $lines[] = '';

        // ── Identity ──────────────────────────────────────────────────────
        $lines[] = '# ── HATUA 1: Weka Jina la Router ────────────────────────────────';
        $lines[] = '/system identity set name=$routerName';
        $lines[] = ':log info ("SKYmanager: Jina la router limewekwa: " . $routerName)';
        $lines[] = '';

        // ── WireGuard interface ──────────────────────────────────────────
        $lines[] = '# ── HATUA 2: Tengeneza WireGuard Interface (VPN Tunnel) ─────────';
        $lines[] = ':if ([:len [/interface wireguard find name="wg-sky"]] = 0) do={';
        $lines[] = '    /interface wireguard add name="wg-sky" \\';
        $lines[] = '        listen-port=$wgListenPort \\';
        $lines[] = '        comment="SKYmanager WireGuard VPN"';
        $lines[] = '    :log info "SKYmanager: WireGuard interface imeundwa"';
        $lines[] = '} else={';
        $lines[] = '    :log info "SKYmanager: WireGuard interface tayari ipo"';
        $lines[] = '}';
        $lines[] = '';

        // ── WireGuard peer (VPS) ─────────────────────────────────────────
        $lines[] = '# ── HATUA 3: Ongeza VPS kama WireGuard Peer ─────────────────────';
        $lines[] = ':if ([:len [/interface wireguard peers find comment="SKYmanager-VPS"]] = 0) do={';
        $lines[] = '    /interface wireguard peers add \\';
        $lines[] = '        interface="wg-sky" \\';
        $lines[] = '        public-key=$wgVpsPubKey \\';
        $lines[] = '        endpoint-address=$wgEndpoint \\';
        $lines[] = '        allowed-address=$apiSubnet \\';
        $lines[] = '        persistent-keepalive=25s \\';
        $lines[] = '        comment="SKYmanager-VPS"';
        $lines[] = '    :log info "SKYmanager: WireGuard peer ya VPS imeongezwa"';
        $lines[] = '} else={';
        $lines[] = '    :log info "SKYmanager: WireGuard peer tayari ipo"';
        $lines[] = '}';
        $lines[] = '';

        // ── WireGuard IP ─────────────────────────────────────────────────
        $lines[] = '# ── HATUA 4: Weka IP kwenye WireGuard Interface ─────────────────';
        $lines[] = ':if ([:len [/ip address find interface="wg-sky"]] = 0) do={';
        $lines[] = '    /ip address add address=$wgAddress interface="wg-sky" comment="SKYmanager WG IP"';
        $lines[] = '    :log info "SKYmanager: WireGuard IP imewekwa"';
        $lines[] = '}';
        $lines[] = '';

        // ── Bridge setup ─────────────────────────────────────────────────
        $lines[] = '# ── HATUA 5: Tengeneza Bridge na Ongeza WiFi Interface ───────────';
        $lines[] = ':if ([:len [/interface bridge find name=$bridgeName]] = 0) do={';
        $lines[] = '    /interface bridge add name=$bridgeName comment="SKYmanager Bridge"';
        $lines[] = '    :log info "SKYmanager: Bridge imeundwa"';
        $lines[] = '}';
        $lines[] = ':if ([:len [/interface bridge port find interface=$wifiIface]] = 0) do={';
        $lines[] = '    /interface bridge port add interface=$wifiIface bridge=$bridgeName';
        $lines[] = '    :log info "SKYmanager: WiFi interface imeongezwa kwenye bridge"';
        $lines[] = '}';
        $lines[] = '';

        // ── IP on bridge ─────────────────────────────────────────────────
        $lines[] = '# ── HATUA 6: Weka IP ya Gateway kwenye Bridge ───────────────────';
        $lines[] = ':if ([:len [/ip address find interface=$bridgeName address=($hotspotIP . "/24")]] = 0) do={';
        $lines[] = '    /ip address add address=($hotspotIP . "/24") interface=$bridgeName comment="SKYmanager Hotspot GW"';
        $lines[] = '    :log info "SKYmanager: IP ya gateway imewekwa"';
        $lines[] = '}';
        $lines[] = '';

        // ── DHCP pool & server ────────────────────────────────────────────
        $lines[] = '# ── HATUA 7: Sanidi DHCP Server kwa Wateja wa WiFi ──────────────';
        $lines[] = ':if ([:len [/ip pool find name="sky-pool"]] = 0) do={';
        $lines[] = '    /ip pool add name="sky-pool" ranges=$dhcpPool';
        $lines[] = '}';
        $lines[] = ':if ([:len [/ip dhcp-server find interface=$bridgeName]] = 0) do={';
        $lines[] = '    /ip dhcp-server add name="sky-dhcp" interface=$bridgeName \\';
        $lines[] = '        address-pool="sky-pool" lease-time=1h disabled=no \\';
        $lines[] = '        comment="SKYmanager DHCP"';
        $lines[] = '    /ip dhcp-server network add address=$hotspotNet \\';
        $lines[] = '        gateway=$hotspotIP dns-server=$hotspotIP \\';
        $lines[] = '        comment="SKYmanager DHCP Network"';
        $lines[] = '    :log info "SKYmanager: DHCP server imeundwa"';
        $lines[] = '}';
        $lines[] = '';

        // ── DHCP Option 114 (RFC 7710 Captive Portal) ────────────────────
        $lines[] = '# ── HATUA 8: DHCP Option 114 (Captive Portal API — RFC 7710) ────';
        $lines[] = '#    Hii inaambia OS (Android 11+, Windows 11, iOS 14+) moja kwa';
        $lines[] = '#    moja kuwa kuna captive portal, bila kuhitaji HTTP probe.';
        $lines[] = ':if ([:len [/ip dhcp-server option find name="captive-portal"]] = 0) do={';
        $lines[] = '    :if ($rosMajor >= 7) do={';
        $lines[] = '        /ip dhcp-server option add name="captive-portal" code=114 \\';
        $lines[] = '            value=("\'s\'" . $portalUrl) \\';
        $lines[] = '            comment="SKYmanager Captive Portal RFC7710"';
        $lines[] = '    } else={';
        $lines[] = '        /ip dhcp-server option add name="captive-portal" code=114 \\';
        $lines[] = '            value=("0s" . $portalUrl) \\';
        $lines[] = '            comment="SKYmanager Captive Portal RFC7710"';
        $lines[] = '    }';
        $lines[] = '    :log info "SKYmanager: DHCP Option 114 imeongezwa"';
        $lines[] = '}';
        $lines[] = ':if ([:len [/ip dhcp-server option sets find name="sky-opt-set"]] = 0) do={';
        $lines[] = '    /ip dhcp-server option sets add name="sky-opt-set" options="captive-portal"';
        $lines[] = '    /ip dhcp-server set [find name="sky-dhcp"] dhcp-option-set="sky-opt-set"';
        $lines[] = '}';
        $lines[] = '';

        // ── DNS ───────────────────────────────────────────────────────────
        $lines[] = '# ── HATUA 9: Sanidi DNS ─────────────────────────────────────────';
        $lines[] = '/ip dns set servers="8.8.8.8,1.1.1.1" allow-remote-requests=yes';
        $lines[] = '';

        // ── DNS Static (CPD Spoofing) ────────────────────────────────────
        $lines[] = '# ── HATUA 10: DNS Static kwa CPD (Spoofing Probes) ──────────────';
        $lines[] = '#    Android, iOS, Windows, Firefox zote zinatumia URLs maalum';
        $lines[] = '#    kuangalia kama internet ipo. Tunazijibu na IP yetu ya hotspot';
        $lines[] = '#    ili zifungue popup ya captive portal.';

        $cpd_hosts = [
            'connectivitycheck.gstatic.com' => 'Android CPD',
            'connectivitycheck.android.com' => 'Android CPD',
            'clients3.google.com' => 'Android CPD',
            'clients1.google.com' => 'Android CPD',
            'captive.apple.com' => 'iOS/macOS CPD',
            'www.apple.com' => 'iOS/macOS CPD',
            'appleiphoneactivation.apple.com' => 'iOS activation',
            'www.msftconnecttest.com' => 'Windows CPD',
            'www.msftncsi.com' => 'Windows NCSI',
            'detectportal.firefox.com' => 'Firefox CPD',
            'connectivity-check.ubuntu.com' => 'Ubuntu CPD',
            'nmcheck.gnome.org' => 'GNOME CPD',
        ];

        foreach ($cpd_hosts as $host => $comment) {
            $lines[] = ":if ([:len [/ip dns static find name=\"{$host}\"]] = 0) do={";
            $lines[] = "    /ip dns static add name=\"{$host}\" address=\$hotspotIP \\";
            $lines[] = "        comment=\"SKYmanager CPD — {$comment}\"";
            $lines[] = '}';
        }
        $lines[] = ':log info "SKYmanager: DNS static entries za CPD zimeongezwa"';
        $lines[] = '';

        // ── NAT — dual rule: hotspot clients + WireGuard subnet ────────────
        $lines[] = '# ── HATUA 11: NAT (Masquerade — Wateja + WireGuard) ─────────────';
        $lines[] = '# Rule 1: Wateja wa hotspot waweze kupata internet kupitia WAN.';
        $lines[] = '# Rule 2: WireGuard traffic (VPN subnet) pia ipite kupitia WAN.';
        $lines[] = ':if ([:len [/ip firewall nat find comment="SKY-NAT Hotspot"]] = 0) do={';
        $lines[] = '    /ip firewall nat add chain=srcnat src-address=$hotspotNet \\';
        $lines[] = '        out-interface=$wanIface action=masquerade \\';
        $lines[] = '        comment="SKY-NAT Hotspot"';
        $lines[] = '    :put "  ✔ NAT ya wateja wa hotspot imeongezwa"';
        $lines[] = '}';
        $lines[] = ':if ([:len [/ip firewall nat find comment="SKY-NAT WireGuard"]] = 0) do={';
        $lines[] = '    /ip firewall nat add chain=srcnat src-address=$apiSubnet \\';
        $lines[] = '        out-interface=$wanIface action=masquerade \\';
        $lines[] = '        comment="SKY-NAT WireGuard"';
        $lines[] = '    :put "  ✔ NAT ya WireGuard subnet imeongezwa"';
        $lines[] = '}';
        $lines[] = ':log info "SKYmanager: NAT rules zimeongezwa"';
        $lines[] = '';

        // ── Hotspot ───────────────────────────────────────────────────────
        $lines[] = '# ── HATUA 12: Washa Hotspot kwenye Bridge Interface ─────────────';
        $lines[] = ':if ([:len [/ip hotspot find interface=$bridgeName]] = 0) do={';
        $lines[] = '    /ip hotspot add name="sky-hotspot" interface=$bridgeName \\';
        $lines[] = '        address-pool="sky-pool" disabled=no \\';
        $lines[] = '        comment="SKYmanager Hotspot"';
        $lines[] = '    :log info "SKYmanager: Hotspot imewashwa"';
        $lines[] = '} else={';
        $lines[] = '    :log info "SKYmanager: Hotspot tayari ipo"';
        $lines[] = '}';
        $lines[] = '';

        // ── Upload login.html ─────────────────────────────────────────────
        $lines[] = '# ── HATUA 13: Pakia login.html (CPD Redirect Page) ──────────────';
        $lines[] = '#    File hii ndiyo inayomredirect mtumiaji kwenye portal.';
        $lines[] = '#    MikroTik itaibadilisha $(mac), $(ip), $(link-orig-esc).';
        $lines[] = ':do {';
        $lines[] = '    /tool fetch url=$loginHtmlUrl dst-path="hotspot/login.html" \\';
        $lines[] = '        keep-result=yes';
        $lines[] = '    :log info "SKYmanager: login.html imepakiwa vizuri"';
        $lines[] = '} on-error={';
        $lines[] = '    :log warning "SKYmanager: Imeshindwa kupakua login.html — sanidi mwenyewe"';
        $lines[] = '    :put "⚠️  login.html haikupakiwa. Angalia muunganiko wa internet."';
        $lines[] = '}';
        $lines[] = '';

        // ── Hotspot profile ───────────────────────────────────────────────
        $lines[] = '# ── HATUA 14: Sanidi Hotspot Profile (CPD + DNS name) ───────────';
        $lines[] = ':if ($rosMajor >= 7) do={';
        $lines[] = '    /ip hotspot profile set [find] \\';
        $lines[] = '        html-directory="hotspot" \\';
        $lines[] = '        dns-name=$portalDomain \\';
        $lines[] = '        login-by="http-chap,http-pap,cookie" \\';
        $lines[] = '        http-cookie-lifetime=30m';
        $lines[] = '} else={';
        $lines[] = '    /ip hotspot profile set [find] \\';
        $lines[] = '        html-directory="flash/hotspot" \\';
        $lines[] = '        dns-name=$portalDomain \\';
        $lines[] = '        login-by="cookie,http-chap,http-pap"';
        $lines[] = '}';
        $lines[] = ':log info "SKYmanager: Hotspot profile imesanidiwa"';
        $lines[] = '';

        // ── Walled Garden ────────────────────────────────────────────────
        $lines[] = '# ── HATUA 15: Walled Garden (Portal + ClickPesa) ────────────────';
        $lines[] = '#    Hizi ni sites ambazo wateja wanaweza kupata KABLA ya kulipa.';

        $wg_entries = [
            "*.{$portalDomain}" => 'SKYmanager Portal',
            $portalDomain => 'SKYmanager Portal (bare)',
            '*.clickpesa.com' => 'ClickPesa Payment',
            'api.clickpesa.com' => 'ClickPesa API',
        ];
        if ($vpsIp) {
            $wg_entries[$vpsIp] = 'SKYmanager VPS';
        }

        foreach ($wg_entries as $host => $comment) {
            $lines[] = ":if ([:len [/ip hotspot walled-garden find dst-host=\"{$host}\"]] = 0) do={";
            $lines[] = "    /ip hotspot walled-garden add dst-host=\"{$host}\" \\";
            $lines[] = "        action=allow comment=\"SKYmanager — {$comment}\"";
            $lines[] = '}';
        }
        $lines[] = '/ip hotspot walled-garden ip add dst-address=0.0.0.0/0 protocol=tcp dst-port=443 action=allow comment="SKYmanager — HTTPS passthrough"';
        $lines[] = ':log info "SKYmanager: Walled Garden imesanidiwa"';
        $lines[] = '';

        // ── WiFi SSID ─────────────────────────────────────────────────────
        $lines[] = '# ── HATUA 16: Weka Jina la WiFi (SSID) ─────────────────────────';
        $lines[] = ':do {';
        $lines[] = '    /interface wireless set [find] ssid=$ssid disabled=no';
        $lines[] = '    :put ("  ✔ SSID imewekwa: " . $ssid)';
        $lines[] = '    :log info ("SKYmanager: SSID = " . $ssid)';
        $lines[] = '} on-error={';
        $lines[] = '    :put "  ↩ Wireless interface haikupatikana — imerukwa"';
        $lines[] = '    :log warning "SKYmanager: Wireless interface haikupatikana"';
        $lines[] = '}';
        $lines[] = '';

        // ── API user with custom group (proper policy) ─────────────────────
        $lines[] = '# ── HATUA 17: Tengeneza API User ya SKYmanager ──────────────────';
        $lines[] = '# Tunatengeneza group ya pekee: read,write,policy,test,api,winbox';
        $lines[] = '# Hii ni salama zaidi kuliko group=full.';
        $lines[] = ':if ([:len [/user group find name="sky-managers"]] = 0) do={';
        $lines[] = '    /user group add name="sky-managers" \\';
        $lines[] = '        policy="read,write,policy,test,api,winbox" \\';
        $lines[] = '        comment="SKYmanager API Group"';
        $lines[] = '    :put "  ✔ API group imeundwa: sky-managers"';
        $lines[] = '} else={';
        $lines[] = '    :put "  ↩ API group tayari ipo — imerukwa"';
        $lines[] = '}';
        $lines[] = ':if ([:len [/user find name=$apiUser]] = 0) do={';
        $lines[] = '    /user add name=$apiUser password=$apiPassword \\';
        $lines[] = '        group="sky-managers" \\';
        $lines[] = '        comment="SKYmanager API User (usifute au kubadilisha!)"';
        $lines[] = '    :put ("  ✔ API user imeundwa: " . $apiUser)';
        $lines[] = '    :log info "SKYmanager: API user imeundwa"';
        $lines[] = '} else={';
        $lines[] = '    /user set [find name=$apiUser] password=$apiPassword group="sky-managers"';
        $lines[] = '    :put "  ✔ API user password imesasishwa"';
        $lines[] = '    :log info "SKYmanager: API user imesasishwa"';
        $lines[] = '}';
        $lines[] = '';

        // ── Remove default admin (safe idempotent check) ─────────────────
        $lines[] = '# ── HATUA 18: Futa admin ya Default (Usalama) ───────────────────';
        $lines[] = '# Hakikisha sky-api imefanikiwa kuundwa (HATUA 17) kabla ya hii.';
        $lines[] = ':if ([:len [/user find name="admin"]] > 0) do={';
        $lines[] = '    /user remove [find name="admin"]';
        $lines[] = '    :put "  ✔ Admin ya default imefutwa (usalama)"';
        $lines[] = '    :log info "SKYmanager: Default admin imefutwa"';
        $lines[] = '} else={';
        $lines[] = '    :put "  ↩ Admin ya default haipo — imerukwa"';
        $lines[] = '}';
        $lines[] = '';

        // ── Lock down services (safe wrappers for both v6 and v7) ─────────
        $lines[] = '# ── HATUA 19: Zuia Huduma Zisizo Hitajika (API + Winbox tu) ─────';
        $lines[] = '/ip service set api port=8728 disabled=no address=$apiSubnet';
        $lines[] = '/ip service set winbox disabled=no';
        $lines[] = '# :do {} on-error={} inazuia kosa kama service haipo kwenye toleo hilo';
        $lines[] = ':do { /ip service set telnet disabled=yes } on-error={}';
        $lines[] = ':do { /ip service set ftp disabled=yes } on-error={}';
        $lines[] = ':do { /ip service set www disabled=yes } on-error={}';
        $lines[] = ':do { /ip service set api-ssl disabled=yes } on-error={}';
        $lines[] = ':put "  ✔ Services zimesanidiwa (API + Winbox tu)"';
        $lines[] = '';

        // ── Firewall: accept rules first, then block ─────────────────────
        $lines[] = '# ── HATUA 20: Firewall (Kubali + Zuia — kwa mpangilio sahihi) ────';
        $lines[] = '# MUHIMU: Accept rules lazima ziwe KABLA ya drop rules.';
        $lines[] = '# 1. Kubali established/related (internet ifanye kazi vizuri)';
        $lines[] = '# 2. Kubali WireGuard UDP port (VPN connection ya SKYmanager)';
        $lines[] = '# 3. Kubali API kutoka VPN subnet tu (usalama)';
        $lines[] = '# 4. Kubali traffic ya hotspot clients kuelekea internet';
        $lines[] = '# 5. ZUIA API kutoka nje ya VPN (usalama mkubwa)';
        $lines[] = ':if ([:len [/ip firewall filter find comment="SKY-FW Accept Established"]] = 0) do={';
        $lines[] = '    /ip firewall filter add chain=forward \\';
        $lines[] = '        connection-state=established,related action=accept \\';
        $lines[] = '        comment="SKY-FW Accept Established" place-before=0';
        $lines[] = '    :put "  ✔ Firewall: Accept established/related"';
        $lines[] = '}';
        $lines[] = ':if ([:len [/ip firewall filter find comment="SKY-FW Accept WireGuard"]] = 0) do={';
        $lines[] = '    /ip firewall filter add chain=input protocol=udp \\';
        $lines[] = '        dst-port=$wgListenPort action=accept \\';
        $lines[] = '        comment="SKY-FW Accept WireGuard"';
        $lines[] = '    :put "  ✔ Firewall: Accept WireGuard UDP port"';
        $lines[] = '}';
        $lines[] = ':if ([:len [/ip firewall filter find comment="SKY-FW Accept API from VPN"]] = 0) do={';
        $lines[] = '    /ip firewall filter add chain=input protocol=tcp dst-port=8728 \\';
        $lines[] = '        src-address=$apiSubnet action=accept \\';
        $lines[] = '        comment="SKY-FW Accept API from VPN"';
        $lines[] = '    :put "  ✔ Firewall: Accept API from WireGuard VPN"';
        $lines[] = '}';
        $lines[] = ':if ([:len [/ip firewall filter find comment="SKY-FW Accept Hotspot"]] = 0) do={';
        $lines[] = '    /ip firewall filter add chain=forward src-address=$hotspotNet \\';
        $lines[] = '        action=accept comment="SKY-FW Accept Hotspot"';
        $lines[] = '    :put "  ✔ Firewall: Accept hotspot clients"';
        $lines[] = '}';
        $lines[] = ':if ([:len [/ip firewall filter find comment="SKY-FW Block API WAN"]] = 0) do={';
        $lines[] = '    /ip firewall filter add chain=input protocol=tcp dst-port=8728 \\';
        $lines[] = '        src-address=!$apiSubnet action=drop \\';
        $lines[] = '        comment="SKY-FW Block API WAN"';
        $lines[] = '    :put "  ✔ Firewall: Block API from WAN"';
        $lines[] = '}';
        $lines[] = ':log info "SKYmanager: Firewall rules zimeongezwa"';
        $lines[] = '';

        // ── Final success message with WireGuard key + credentials ─────────
        $lines[] = '# ── MWISHO: Ujumbe wa Mafanikio + Credentials ─────────────────';
        $lines[] = '# Soma output hapa chini kwa makini — una taarifa muhimu.';
        $lines[] = ':local wgPubKey [/interface wireguard get [find name="wg-sky"] public-key]';
        $lines[] = '';
        $lines[] = ':put ""';
        $lines[] = ':put "================================================================"';
        $lines[] = ':put "  ✅  SKYmanager imeunganishwa VIZURI! Hongera!"';
        $lines[] = ':put "================================================================"';
        $lines[] = ':put ""';
        $lines[] = ':put "┌─ �  WireGuard Public Key ya Router Hii: ──────────────────"';
        $lines[] = ':put $wgPubKey';
        $lines[] = ':put "└───────────────────────────────────────────────────────────────"';
        $lines[] = ':put ""';
        $lines[] = ':put "Amri ya VPS (iendesha kwenye Linux VPS yako):"';
        $lines[] = ':put ("  sudo wg set wg0 peer " . $wgPubKey . " allowed-ips " . $wgAddress . " persistent-keepalive 25")';
        $lines[] = ':put ""';
        $lines[] = ':put "┌─ 🔑  SKYmanager API Credentials: ────────────────────────────"';
        $lines[] = ':put ("  API User    : " . $apiUser)';
        $lines[] = ':put ("  API Password: " . $apiPassword)';
        $lines[] = ':put "  API Port    : 8728"';
        $lines[] = ':put ("  Router IP   : " . [/ip address get [find interface=\"wg-sky\"] address])';
        $lines[] = ':put "└───────────────────────────────────────────────────────────────"';
        $lines[] = ':put ""';
        $lines[] = ':put "┌─ 🌐  Hatua Inayofuata: ───────────────────────────────────────"';
        $lines[] = ':put "  1. Nakili Public Key hapo juu"';
        $lines[] = ':put "  2. Endesha amri ya VPS hapo juu kwenye Linux VPS yako"';
        $lines[] = ':put "  3. Rudi SKYmanager Dashboard → Routers"';
        $lines[] = ':put "  4. Bonyeza \"Test Connection\" — utaona Online ✅"';
        $lines[] = ':put "└───────────────────────────────────────────────────────────────"';
        $lines[] = ':put ""';
        $lines[] = ':put "================================================================"';

        $script = implode("\n", $lines);

        Log::info('SKYmanager: Full setup script generated', [
            'router' => $router->name,
            'ip' => $router->ip_address,
        ]);

        return $script;
    }

    /**
     * Derive a DHCP pool range from the hotspot network and gateway IP.
     * Example: network=192.168.88.0/24, gateway=192.168.88.1 → 192.168.88.10-192.168.88.254
     */
    private function deriveDhcpPool(string $network, string $gateway): string
    {
        $base = substr($network, 0, (int) strrpos($network, '.'));

        return $base.'.10-'.$base.'.254';
    }

    /**
     * @throws Exception
     */
    private function ensureConnected(): void
    {
        if (! $this->isConnected || ! $this->socket) {
            throw new Exception('MikrotikApiService: not connected. Call connect() first.');
        }
    }
}
