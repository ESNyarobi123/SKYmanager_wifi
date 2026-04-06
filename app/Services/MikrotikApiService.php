<?php

namespace App\Services;

use App\Models\Router;
use App\Models\User;
use App\Support\WireguardProvisioning;
use Exception;
use Illuminate\Support\Carbon;
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
     * Authorize a hotspot client by MAC address after successful payment.
     *
     * Adds a /ip hotspot user entry with mac-address binding so MikroTik
     * auto-logs the device in without a password challenge.
     * Called from LocalPortalController after ClickPesa confirms payment.
     *
     * @throws Exception
     */
    public function authorizeHotspotMac(
        string $mac,
        string $profileName,
        int $durationMinutes,
        ?int $dataMb = null,
        ?string $rateLimit = null
    ): void {
        $this->ensureConnected();

        $mac = strtoupper($mac);

        $existing = $this->sendCommand([
            '/ip/hotspot/user/print',
            '?mac-address='.$mac,
        ]);

        $limitUptime = $durationMinutes > 0 ? $durationMinutes.'m' : '0s';
        $limitBytes = $dataMb ? (string) ($dataMb * 1048576) : '';

        $params = [
            '=name='.$mac,
            '=mac-address='.$mac,
            '=profile='.$profileName,
            '=limit-uptime='.$limitUptime,
            '=comment=SKYmanager auto-auth',
        ];

        if ($limitBytes) {
            $params[] = '=limit-bytes-total='.$limitBytes;
        }

        if (! empty($existing) && isset($existing[0]['.id'])) {
            array_unshift($params, '=.id='.$existing[0]['.id']);
            $this->sendCommand(array_merge(['/ip/hotspot/user/set'], $params));
        } else {
            $this->sendCommand(array_merge(['/ip/hotspot/user/add'], $params));
        }

        $this->ensureHotspotProfile($profileName, $rateLimit);
    }

    /**
     * Ensure a hotspot user profile exists with the correct rate limits.
     * Creates or updates the profile named after the billing plan.
     * $rateLimit: MikroTik "Uk/Dk" string, e.g. "512k/2048k". Null = unlimited.
     *
     * @throws Exception
     */
    public function ensureHotspotProfile(string $name, ?string $rateLimit = null): void
    {
        $this->ensureConnected();

        $existing = $this->sendCommand([
            '/ip/hotspot/user/profile/print',
            '?name='.$name,
        ]);

        $params = [
            '=name='.$name,
            '=shared-users=1',
        ];

        if ($rateLimit && $rateLimit !== '0/0') {
            $params[] = '=rate-limit='.$rateLimit;
        }

        if (! empty($existing) && isset($existing[0]['.id'])) {
            array_unshift($params, '=.id='.$existing[0]['.id']);
            $this->sendCommand(array_merge(['/ip/hotspot/user/profile/set'], $params));
        } else {
            $this->sendCommand(array_merge(['/ip/hotspot/user/profile/add'], $params));
        }
    }

    /**
     * Connect to a router using its ZTP sky-api credentials (wg_address + ztp_api_password).
     * Falls back to the regular api_username/api_password if ZTP credentials are missing.
     *
     * @throws Exception
     */
    public function connectZtp(Router $router): static
    {
        $ip = $router->wg_address
            ? explode('/', $router->wg_address)[0]
            : $router->ip_address;

        $username = $router->api_username ?: 'sky-api';
        $password = $router->ztp_api_password ?: $router->api_password;

        $this->socket = @fsockopen($ip, $router->api_port ?: 8728, $errno, $errstr, 5);

        if (! $this->socket) {
            throw new Exception("Cannot reach router [{$router->name}] at {$ip}: {$errstr} ({$errno})");
        }

        stream_set_timeout($this->socket, 5);
        $this->login($username, $password);
        $this->isConnected = true;

        return $this;
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
     * True when the wg-sky WireGuard interface reports running (RouterOS 7+).
     *
     * @throws Exception
     */
    public function checkWireguardSkyRunning(): bool
    {
        $this->ensureConnected();

        $rows = $this->sendCommand([
            '/interface/wireguard/print',
            '?name=wg-sky',
        ]);
        $row = $rows[0] ?? [];

        return isset($row['running']) && $row['running'] === 'true';
    }

    /**
     * Read SKYmanager-VPS peer handshake while an API session is already open.
     *
     * @return array{handshake_at: ?Carbon, raw: array<string, string>}
     *
     * @throws Exception
     */
    public function readWireguardPeerHandshakeAfterConnect(): array
    {
        $this->ensureConnected();

        $rows = $this->sendCommand([
            '/interface/wireguard/peers/print',
            '?comment=SKYmanager-VPS',
        ]);
        $row = $rows[0] ?? [];
        $handshake = null;
        if (isset($row['last-handshake'])) {
            $ts = (int) $row['last-handshake'];
            if ($ts > 0) {
                $handshake = Carbon::createFromTimestamp($ts);
            }
        }

        return ['handshake_at' => $handshake, 'raw' => $row];
    }

    /**
     * Tunnel probe for persistence during a single ZTP API session.
     *
     * @return array{tunnel_up: bool, handshake_at: ?Carbon}
     *
     * @throws Exception
     */
    public function probeTunnelUpWhileConnected(Router $router): array
    {
        if (($router->preferred_vpn_mode ?? '') === 'none') {
            return ['tunnel_up' => true, 'handshake_at' => null];
        }

        $useWgSection = WireguardProvisioning::shouldGenerateWireguardSection($router);

        if ($useWgSection) {
            $handshake = null;
            try {
                $handshake = $this->readWireguardPeerHandshakeAfterConnect()['handshake_at'];
            } catch (\Throwable) {
            }

            $running = false;
            try {
                $running = $this->checkWireguardSkyRunning();
            } catch (\Throwable) {
            }

            $recent = $handshake && $handshake->gt(now()->subMinutes(10));
            $staleButRunning = $running && $handshake && $handshake->gt(now()->subHours(24));
            $tunnelUp = $recent || $staleButRunning;

            return ['tunnel_up' => $tunnelUp, 'handshake_at' => $handshake];
        }

        $tunnelUp = false;
        try {
            $tunnelUp = $this->checkVpnStatus();
        } catch (\Throwable) {
        }

        return ['tunnel_up' => $tunnelUp, 'handshake_at' => null];
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
     *
     * @param  bool  $forceRotateApiPassword  When false, an existing ztp_api_password is reused (safer for repeat runs).
     */
    public function generateFullSetupScript(Router $router, bool $forceRotateApiPassword = false): string
    {
        $router->refresh();
        $shouldRotate = $forceRotateApiPassword || ! $router->ztp_api_password;
        if ($shouldRotate) {
            $apiPassword = bin2hex(random_bytes(12));
            $router->update([
                'ztp_api_password' => $apiPassword,
                'api_credentials_updated_at' => now(),
                'api_credential_version' => ($router->api_credential_version ?? 0) + 1,
            ]);
        } else {
            $apiPassword = (string) $router->ztp_api_password;
        }

        $router->ensureLocalPortalToken();
        $router->refresh();
        $customer = $router->user ?? new User;
        $bundles = app(HotspotBundleService::class);
        $bundles->syncBundleMetadata($router, $customer);
        $router->refresh();
        $portalToken = (string) $router->local_portal_token;
        $skySegment = $router->portal_folder_name ?? HotspotBundleService::folderSegment($router);

        // Sanitise router name; trim dashes; fallback prevents empty identity error.
        $identity = trim(preg_replace('/[^a-zA-Z0-9\-]/', '-', $router->name ?? ''), '-');
        $identity = $identity ?: 'SKYmanager-Router';

        $portalUrl = $router->user ? $router->user->portalUrl() : url('/portal');
        $portalDomain = config('services.ztp.portal_domain', 'micro.spotbox.online');
        $vpsIp = config('services.ztp.vps_ip', '');
        $apiSubnet = (string) config('services.wireguard.api_subnet', '10.10.0.0/24');
        $wgEndpoint = trim((string) config('services.wireguard.vps_endpoint', ''));
        $wgVpsPubKey = trim((string) config('services.wireguard.vps_public_key', ''));
        $wgListenPort = WireguardProvisioning::listenPort();
        $vpsWgIface = WireguardProvisioning::vpsInterfaceName();

        $hasWg = WireguardProvisioning::shouldGenerateWireguardSection($router);
        $wgHardFail = WireguardProvisioning::wireguardHardRequiredButIncomplete($router);

        $wgAddress = '';
        if ($hasWg) {
            $wgAddress = trim((string) $router->wg_address);
            if ($wgAddress !== '' && ! str_contains($wgAddress, '/')) {
                $wgAddress .= '/32';
            }
        }

        $hotspotIface = $router->hotspot_interface ?: 'bridge';
        $hotspotSsid = str_replace('"', '', $router->hotspot_ssid ?: 'PEACE');
        $hotspotGw = $router->hotspot_gateway ?: '192.168.88.1';
        $hotspotNet = $router->hotspot_network ?: '192.168.88.0/24';
        $dhcpPool = $this->deriveDhcpPool($hotspotNet, $hotspotGw);
        $apiUser = $router->api_username ?: 'sky-api';
        $apiPort = (int) ($router->api_port ?: 8728);
        $wifiIf = $router->wifi_interface ?: 'wlan1';
        $wanIf = $router->wan_interface ?: 'ether1';

        $scriptWarnings = [];
        if (! $router->wifi_interface) {
            $scriptWarnings[] = ['code' => 'assumed_wifi', 'message' => 'WiFi interface not set — defaulting to '.$wifiIf.' for bridge port. Set WiFi interface in Advanced if this is wrong (/interface print).'];
        }
        if (! $router->wan_interface) {
            $scriptWarnings[] = ['code' => 'assumed_wan', 'message' => 'WAN interface not set — defaulting to '.$wanIf.' for masquerade/NAT. Set WAN interface in Advanced if uplink uses another port.'];
        }
        if (! $router->hotspot_interface) {
            $scriptWarnings[] = ['code' => 'assumed_bridge', 'message' => 'Hotspot/bridge name not set — using '.$hotspotIface.'. Override in Advanced if your LAN bridge has another name.'];
        }
        if ($router->use_default_network_settings ?? true) {
            $scriptWarnings[] = ['code' => 'default_lan', 'message' => 'Using default LAN template gateway '.$hotspotGw.' and network '.$hotspotNet.' — confirm with /ip address print or override in Advanced.'];
        }

        if ($wgHardFail) {
            $scriptWarnings[] = [
                'code' => 'wg_required_missing',
                'message' => WireguardProvisioning::preciseWireguardWarningMessage($router),
            ];
        }

        if (($router->preferred_vpn_mode ?? '') === 'auto' && ! WireguardProvisioning::isServerConfigComplete()) {
            $scriptWarnings[] = [
                'code' => 'wg_skipped_auto',
                'message' => 'WireGuard server env incomplete — script runs without VPN (auto mode). Missing: '.implode(', ', WireguardProvisioning::missingServerEnvComponents()).'.',
            ];
        }

        if (($router->preferred_vpn_mode ?? '') === 'auto'
            && WireguardProvisioning::isServerConfigComplete()
            && ! WireguardProvisioning::isRouterWgAddressUsable($router->wg_address)) {
            $scriptWarnings[] = [
                'code' => 'wg_skipped_auto_no_ip',
                'message' => 'Auto mode: WG server env is OK but router wg_address is not set — script runs without VPN until you set a WireGuard tunnel IP (Advanced on claim).',
            ];
        }

        // DHCP Option 114: hex-encode the URL so RouterOS never sees the 's' type
        // prefix which triggers "Unknown data type!" on RouterOS 7.x in some builds.
        $option114 = '0x'.bin2hex($portalUrl);

        $L = [];

        // ── Header ────────────────────────────────────────────────────────
        $L[] = '# ================================================================';
        $L[] = '# SKYmanager -- Full Automatic MikroTik Setup Script';
        $L[] = "# Router  : {$router->name}";
        $L[] = "# VPS     : {$vpsIp}";
        $L[] = "# Portal  : {$portalUrl}";
        $L[] = '# Hotspot bundle: '.config('skymanager.portal_bundle_version').' sha256:'.($router->portal_bundle_hash ?? '—');
        $L[] = '# Hotspot folder: hotspot/'.$skySegment.' (ROS7) flash/hotspot/'.$skySegment.' (ROS6)';
        $L[] = '# Tarehe  : '.now()->toDateTimeString();
        if (! $shouldRotate) {
            $L[] = '# NOTE: ZTP API password kept unchanged (use repair/rotate to issue a new one).';
        }
        foreach ($scriptWarnings as $w) {
            $msg = is_array($w) ? (string) ($w['message'] ?? json_encode($w)) : (string) $w;
            $L[] = '# WARNING: '.$msg;
        }
        $L[] = '# Interfaces: WiFi='.$wifiIf.' WAN='.$wanIf.' API port='.$apiPort.' API user='.$apiUser;
        $L[] = '# ================================================================';
        $L[] = '# PASTE KWENYE: MikroTik > New Terminal';
        $L[] = '# Script hii ni salama kurun mara nyingi (idempotent).';
        $L[] = '# Portal: hatua za mwisho (13-14) hupakia BUNDLE KAMILI — faili zote (login.html, rlogin, md5.js, …), si faili moja pekee.';
        $L[] = '# ================================================================';
        $L[] = '# ANGALIZO: Thibitisha majina ya interface (/interface print).';
        $L[] = '# WiFi='.$wifiIf.' WAN='.$wanIf.' — badilisha kwenye SKYmanager Advanced ikiwa si sahihi.';
        $L[] = '# HATUA 7 (DHCP): Ikiwa kuna "server or relay with such interface already exists", interface tayari ina DHCP — script haina ongeza sky-dhcp; ongeza option 114 kwa mikono au futa server inayolingana.';
        $L[] = '# VPS WireGuard interface (SKYmanager): '.$vpsWgIface.' (from WG_INTERFACE_NAME / default wg0)';
        $L[] = '# ================================================================';
        $L[] = '';

        // ── RouterOS version detection ────────────────────────────────────
        // These are the ONLY two RouterOS :local variables in this script.
        // ALL other values are embedded directly from PHP to avoid the
        // "ambiguous value / empty variable / invalid value" class of errors.
        $L[] = ':local rosVer [/system resource get version]';
        $L[] = ':local rosMajor [:tonum [:pick $rosVer 0 [:find $rosVer "."]]]';
        $L[] = ':put "================================================================"';
        $L[] = ':put ("RouterOS: " . $rosVer)';
        $L[] = ':put "================================================================"';
        $L[] = '';

        // ── HATUA 1: Identity ─────────────────────────────────────────────
        $L[] = '# ---- HATUA 1: Jina la Router';
        $L[] = ":do { /system identity set name=\"{$identity}\" } on-error={ :put \"ONYO: Identity haikuwekwa\" }";
        $L[] = '';

        // ── HATUA 2-4: WireGuard (skipped when not configured) ───────────
        if ($hasWg) {
            $L[] = '# ---- HATUA 2: WireGuard Interface';
            $L[] = ':if ([:len [/interface wireguard find name="wg-sky"]] = 0) do={';
            $L[] = "    /interface wireguard add name=\"wg-sky\" listen-port={$wgListenPort} comment=\"SKYmanager VPN\"";
            $L[] = '}';
            $L[] = '';

            $L[] = '# ---- HATUA 3: WireGuard Peer (VPS)';
            $L[] = ':if ([:len [/interface wireguard peers find comment="SKYmanager-VPS"]] = 0) do={';
            $L[] = "    /interface wireguard peers add interface=\"wg-sky\" public-key=\"{$wgVpsPubKey}\" endpoint-address=\"{$wgEndpoint}\" endpoint-port={$wgListenPort} allowed-address=\"{$apiSubnet}\" persistent-keepalive=25s comment=\"SKYmanager-VPS\"";
            $L[] = '}';
            $L[] = '';

            $L[] = '# ---- HATUA 4: WireGuard IP';
            $L[] = ':if ([:len [/ip address find interface="wg-sky"]] = 0) do={';
            $L[] = "    /ip address add address=\"{$wgAddress}\" interface=\"wg-sky\" comment=\"SKYmanager WG IP\"";
            $L[] = '}';
            $L[] = '';
        } else {
            if ($wgHardFail) {
                $L[] = '# ---- HATUA 2-4: WireGuard INABIDI — .env (WG_*) au wg_address si kamili. Tengeneza kisha unda script upya.';
            } else {
                $L[] = '# ---- HATUA 2-4: WireGuard haipo (VPN mode none / auto bila env / mazingira hayajatosha)';
            }
            $L[] = '';
        }

        // ── HATUA 5: Bridge + WiFi port ───────────────────────────────────
        $L[] = '# ---- HATUA 5: Bridge na WiFi Interface';
        $L[] = ":if ([:len [/interface bridge find name=\"{$hotspotIface}\"]] = 0) do={";
        $L[] = "    /interface bridge add name=\"{$hotspotIface}\" comment=\"SKYmanager Bridge\"";
        $L[] = '}';
        $L[] = ':do {';
        $L[] = "    :if ([:len [/interface bridge port find interface=\"{$wifiIf}\" bridge=\"{$hotspotIface}\"]] = 0) do={";
        $L[] = "        /interface bridge port add interface=\"{$wifiIf}\" bridge=\"{$hotspotIface}\"";
        $L[] = '    }';
        $L[] = '} on-error={ :put "ONYO: Badilisha WiFi interface kwenye SKYmanager Advanced au script hii" }';
        $L[] = '';

        // ── HATUA 6: IP address on bridge ─────────────────────────────────
        $L[] = '# ---- HATUA 6: IP ya Gateway kwenye Bridge';
        $L[] = ":if ([:len [/ip address find address=\"{$hotspotGw}/24\"]] = 0) do={";
        $L[] = "    /ip address add address=\"{$hotspotGw}/24\" interface=\"{$hotspotIface}\" comment=\"SKYmanager Hotspot GW\"";
        $L[] = '}';
        $L[] = '';

        // ── HATUA 7: DHCP ─────────────────────────────────────────────────
        $L[] = '# ---- HATUA 7: DHCP Server';
        $L[] = ':local skyDhcpConflict 0';
        $L[] = ":if ([:len [/ip dhcp-server find interface=\"{$hotspotIface}\"]] > 0) do={";
        $L[] = '    :if ([:len [/ip dhcp-server find name="sky-dhcp"]] = 0) do={';
        $L[] = '        :set skyDhcpConflict 1';
        $L[] = "        :put (\"WARNING: DHCP server already exists on {$hotspotIface} — skipping sky-dhcp to avoid 'server or relay with such interface already exists'. Add RFC7710 option 114 to the existing server or remove the conflict.\")";
        $L[] = '    }';
        $L[] = '}';
        $L[] = ':if ([:len [/ip pool find name="sky-pool"]] = 0) do={';
        $L[] = "    /ip pool add name=\"sky-pool\" ranges=\"{$dhcpPool}\"";
        $L[] = '}';
        $L[] = ':if ($skyDhcpConflict = 0) do={';
        $L[] = '    :if ([:len [/ip dhcp-server find name="sky-dhcp"]] = 0) do={';
        $L[] = "        /ip dhcp-server add name=\"sky-dhcp\" interface=\"{$hotspotIface}\" address-pool=\"sky-pool\" lease-time=1h disabled=no comment=\"SKYmanager DHCP\"";
        $L[] = "        /ip dhcp-server network add address=\"{$hotspotNet}\" gateway=\"{$hotspotGw}\" dns-server=\"{$hotspotGw}\" comment=\"SKYmanager DHCP Network\"";
        $L[] = '    }';
        $L[] = '}';
        $L[] = '';

        // ── HATUA 8: DHCP Option 114 (RFC 7710) ──────────────────────────
        $L[] = '# ---- HATUA 8: DHCP Option 114 (RFC 7710 -- CPD auto-popup)';
        $L[] = "# URL: {$portalUrl}";
        $L[] = ':if ([:len [/ip dhcp-server option find name="captive-portal"]] = 0) do={';
        $L[] = "    /ip dhcp-server option add name=\"captive-portal\" code=114 value=\"{$option114}\" comment=\"SKYmanager RFC7710\"";
        $L[] = '}';
        $L[] = ':if ([:len [/ip dhcp-server option sets find name="sky-opt-set"]] = 0) do={';
        $L[] = '    /ip dhcp-server option sets add name="sky-opt-set" options="captive-portal"';
        $L[] = '    :if ([:len [/ip dhcp-server find name="sky-dhcp"]] > 0) do={';
        $L[] = '        /ip dhcp-server set [find name="sky-dhcp"] dhcp-option-set="sky-opt-set"';
        $L[] = '    }';
        $L[] = '}';
        $L[] = '';

        // ── HATUA 9: DNS ──────────────────────────────────────────────────
        $L[] = '# ---- HATUA 9: DNS';
        $L[] = '/ip dns set servers="8.8.8.8,1.1.1.1" allow-remote-requests=yes';
        $L[] = '';

        // ── HATUA 10: DNS Static (CPD Spoofing) ──────────────────────────
        // Hardcode the IP literal -- avoids "invalid value for argument address"
        // that occurs when RouterOS 7 refuses to coerce a string-typed local to IP.
        $L[] = '# ---- HATUA 10: DNS Static kwa CPD (Captive Portal Detection)';
        $L[] = '# Android, iOS, Windows, Firefox zinatumia URLs hizi kupima internet.';

        $cpdHosts = [
            'connectivitycheck.gstatic.com',
            'connectivitycheck.android.com',
            'clients3.google.com',
            'clients1.google.com',
            'captive.apple.com',
            'www.apple.com',
            'appleiphoneactivation.apple.com',
            'www.msftconnecttest.com',
            'www.msftncsi.com',
            'detectportal.firefox.com',
            'connectivity-check.ubuntu.com',
            'nmcheck.gnome.org',
        ];

        foreach ($cpdHosts as $host) {
            $L[] = ":if ([:len [/ip dns static find name=\"{$host}\"]] = 0) do={";
            $L[] = "    /ip dns static add name=\"{$host}\" address={$hotspotGw} comment=\"SKYmanager CPD\"";
            $L[] = '}';
        }
        $L[] = '';

        // ── HATUA 11: NAT ─────────────────────────────────────────────────
        $L[] = '# ---- HATUA 11: NAT (Masquerade)';
        $L[] = ':if ([:len [/ip firewall nat find comment="SKY-NAT-Hotspot"]] = 0) do={';
        $L[] = "    /ip firewall nat add chain=srcnat src-address=\"{$hotspotNet}\" out-interface=\"{$wanIf}\" action=masquerade comment=\"SKY-NAT-Hotspot\"";
        $L[] = '}';
        if ($hasWg) {
            $L[] = ':if ([:len [/ip firewall nat find comment="SKY-NAT-WireGuard"]] = 0) do={';
            $L[] = "    /ip firewall nat add chain=srcnat src-address=\"{$apiSubnet}\" out-interface=\"{$wanIf}\" action=masquerade comment=\"SKY-NAT-WireGuard\"";
            $L[] = '}';
        }
        $L[] = '';

        // ── HATUA 12: Hotspot ─────────────────────────────────────────────
        $L[] = '# ---- HATUA 12: Hotspot';
        $L[] = ":if ([:len [/ip hotspot find interface=\"{$hotspotIface}\"]] = 0) do={";
        $L[] = "    /ip hotspot add name=\"sky-hotspot\" interface=\"{$hotspotIface}\" address-pool=\"sky-pool\" disabled=no";
        $L[] = '}';
        $L[] = '';

        // ── HATUA 13: Full hotspot bundle (popup-safe subdirectory) ───────
        $L[] = '# ---- HATUA 13: Pakia bundle kamili (login, rlogin, md5.js, nk.)';
        $L[] = '# LEGACY: routers zilizoweka tu hotspot/login.html bado: '.url('/hotspot-login.html').'?router_id='.$router->id;
        $L[] = ':put "SKYmanager: inapakia portal bundle — subfolder '.$skySegment.'..."';
        $L[] = ':if ($rosMajor >= 7) do={';
        foreach (HotspotBundleService::BUNDLE_FILES as $bundleFile) {
            $fetchUrl = $bundles->publicFileUrl($router, $bundleFile, $portalToken);
            $dstPath = 'hotspot/'.$skySegment.'/'.$bundleFile;
            $L[] = "    :do { /tool fetch url=\"{$fetchUrl}\" dst-path=\"{$dstPath}\" keep-result=yes } on-error={ :put \"SKY FETCH ERROR: {$bundleFile}\" }";
        }
        $L[] = '} else={';
        foreach (HotspotBundleService::BUNDLE_FILES as $bundleFile) {
            $fetchUrl = $bundles->publicFileUrl($router, $bundleFile, $portalToken);
            $dstPath = 'flash/hotspot/'.$skySegment.'/'.$bundleFile;
            $L[] = "    :do { /tool fetch url=\"{$fetchUrl}\" dst-path=\"{$dstPath}\" keep-result=yes } on-error={ :put \"SKY FETCH ERROR: {$bundleFile}\" }";
        }
        $L[] = '}';
        $L[] = ':put "SKYmanager: bundle fetch kumalizika (angalia errors hapo juu)"';
        $L[] = '';

        // ── HATUA 14: Hotspot profile ─────────────────────────────────────
        // dns-name = hotspot gateway IP (local) so the portal opens instantly
        // without needing VPS DNS resolution.
        // login-by includes 'mac' for MAC-binding auto-login after payment.
        $L[] = '# ---- HATUA 14: Hotspot Profile (bundle subdirectory)';
        $L[] = ':if ($rosMajor >= 7) do={';
        $L[] = "    /ip hotspot profile set [find] html-directory=\"hotspot/{$skySegment}\" login-by=\"mac,http-chap,http-pap,cookie\" dns-name=\"{$hotspotGw}\" http-cookie-lifetime=1d";
        $L[] = '} else={';
        $L[] = "    /ip hotspot profile set [find] html-directory=\"flash/hotspot/{$skySegment}\" login-by=\"mac,cookie,http-chap,http-pap\" dns-name=\"{$hotspotGw}\"";
        $L[] = '}';
        $L[] = '';

        // ── HATUA 15: Walled Garden ───────────────────────────────────────
        // Allow VPS domain (for API calls from local portal) + ClickPesa.
        // No CDN domains needed — portal JS is self-contained.
        $vpsHost = parse_url(config('app.url'), PHP_URL_HOST) ?: $portalDomain;
        $L[] = '# ---- HATUA 15: Walled Garden (VPS API + malipo)';
        $wgEntries = ["*.{$vpsHost}", $vpsHost, '*.clickpesa.com', 'api.clickpesa.com'];
        if ($vpsIp) {
            $wgEntries[] = $vpsIp;
        }
        foreach ($wgEntries as $wgHost) {
            $L[] = ":if ([:len [/ip hotspot walled-garden find dst-host=\"{$wgHost}\"]] = 0) do={";
            $L[] = "    /ip hotspot walled-garden add dst-host=\"{$wgHost}\" action=allow comment=\"SKYmanager\"";
            $L[] = '}';
        }
        $L[] = ':if ([:len [/ip hotspot walled-garden ip find comment="SKY-WG-HTTPS"]] = 0) do={';
        $L[] = '    /ip hotspot walled-garden ip add dst-address=0.0.0.0/0 protocol=tcp dst-port=443 action=allow comment="SKY-WG-HTTPS"';
        $L[] = '}';
        $L[] = '';

        // ── HATUA 16: WiFi SSID ───────────────────────────────────────────
        $L[] = '# ---- HATUA 16: WiFi SSID';
        $L[] = ':do {';
        $L[] = "    /interface wireless set [find] ssid=\"{$hotspotSsid}\" disabled=no";
        $L[] = "    :put \"SSID imewekwa: {$hotspotSsid}\"";
        $L[] = '} on-error={ :put "ONYO: Wireless interface haikupatikana -- imerukwa" }';
        $L[] = '';

        // ── HATUA 17: API user ────────────────────────────────────────────
        $L[] = '# ---- HATUA 17: API User ya SKYmanager';
        $L[] = ':if ([:len [/user group find name="sky-managers"]] = 0) do={';
        $L[] = '    /user group add name="sky-managers" policy="read,write,policy,test,api,winbox" comment="SKYmanager API Group"';
        $L[] = '}';
        $L[] = ":if ([:len [/user find name=\"{$apiUser}\"]] = 0) do={";
        $L[] = "    /user add name=\"{$apiUser}\" password=\"{$apiPassword}\" group=\"sky-managers\" comment=\"SKYmanager API\"";
        $L[] = "    :put \"API user imeundwa: {$apiUser}\"";
        $L[] = '} else={';
        $L[] = "    /user set [find name=\"{$apiUser}\"] password=\"{$apiPassword}\" group=\"sky-managers\"";
        $L[] = '    :put "API user password imesasishwa"';
        $L[] = '}';
        $L[] = '';

        // ── HATUA 18: Remove default admin ───────────────────────────────
        $L[] = '# ---- HATUA 18: Futa admin ya default (usalama)';
        $L[] = ":if ([:len [/user find name=\"{$apiUser}\"]] > 0) do={";
        $L[] = '    :if ([:len [/user find name="admin"]] > 0) do={';
        $L[] = '        /user remove [find name="admin"]';
        $L[] = '        :put "Admin ya default imefutwa"';
        $L[] = '    }';
        $L[] = '}';
        $L[] = '';

        // ── HATUA 19: Lock down services ──────────────────────────────────
        $L[] = '# ---- HATUA 19: Zuia Huduma Zisizo Hitajika';
        $L[] = ":do { /ip service set api port={$apiPort} disabled=no address=\"{$apiSubnet}\" } on-error={}";
        $L[] = ':do { /ip service set winbox disabled=no } on-error={}';
        $L[] = ':do { /ip service set telnet disabled=yes } on-error={}';
        $L[] = ':do { /ip service set ftp disabled=yes } on-error={}';
        $L[] = ':do { /ip service set www disabled=yes } on-error={}';
        $L[] = ':do { /ip service set api-ssl disabled=yes } on-error={}';
        $L[] = '';

        // ── HATUA 20: Firewall ────────────────────────────────────────────
        $L[] = '# ---- HATUA 20: Firewall (accept kabla ya drop)';
        $L[] = ':if ([:len [/ip firewall filter find comment="SKY-FW-Established"]] = 0) do={';
        $L[] = '    /ip firewall filter add chain=forward connection-state=established,related action=accept comment="SKY-FW-Established" place-before=0';
        $L[] = '}';
        $L[] = ':if ([:len [/ip firewall filter find comment="SKY-FW-WireGuard"]] = 0) do={';
        $L[] = "    /ip firewall filter add chain=input protocol=udp dst-port={$wgListenPort} action=accept comment=\"SKY-FW-WireGuard\"";
        $L[] = '}';
        if ($hasWg) {
            $L[] = ':if ([:len [/ip firewall filter find comment="SKY-FW-API-VPN"]] = 0) do={';
            $L[] = "    /ip firewall filter add chain=input protocol=tcp dst-port={$apiPort} src-address=\"{$apiSubnet}\" action=accept comment=\"SKY-FW-API-VPN\"";
            $L[] = '}';
            $L[] = ':if ([:len [/ip firewall filter find comment="SKY-FW-API-Block"]] = 0) do={';
            $L[] = "    /ip firewall filter add chain=input protocol=tcp dst-port={$apiPort} src-address=!{$apiSubnet} action=drop comment=\"SKY-FW-API-Block\"";
            $L[] = '}';
        }
        $L[] = ':if ([:len [/ip firewall filter find comment="SKY-FW-Hotspot"]] = 0) do={';
        $L[] = "    /ip firewall filter add chain=forward src-address=\"{$hotspotNet}\" action=accept comment=\"SKY-FW-Hotspot\"";
        $L[] = '}';
        $L[] = '';

        // ── Final success message ──────────────────────────────────────────
        $L[] = '# ---- MWISHO: Mafanikio!';
        if ($hasWg) {
            $L[] = ':local wgPubKey [/interface wireguard get [find name="wg-sky"] public-key]';
            $L[] = ':put ""';
            $L[] = ':put "================================================================"';
            $L[] = ':put "   SKYmanager Setup IMEKAMILIKA! Hongera!"';
            $L[] = ':put "================================================================"';
            $L[] = ':put ""';
            $L[] = ':put "WireGuard Public Key ya Router Hii:"';
            $L[] = ':put $wgPubKey';
            $L[] = ':put ""';
            $L[] = ':put "Amri ya VPS (iendesha kwenye Linux VPS; interface: '.$vpsWgIface.'):"';
            $L[] = ':put ("  sudo wg set '.$vpsWgIface.' peer \" . $wgPubKey . \" allowed-ips '.$wgAddress.' persistent-keepalive 25")';
            $L[] = ':put ""';
            $L[] = ':put "Allowed IP kwa router hii (thibitisha kwenye VPS):"';
            $L[] = ":put \"  {$wgAddress}\"";
            $L[] = ':put ""';
            $L[] = ':put "VPS troubleshooting:"';
            $L[] = ':put "  - Thibitisha jina la interface: ip link (lazima lianane na WG_INTERFACE_NAME / '.$vpsWgIface.')"';
            $L[] = ':put "  - sudo wg show '.$vpsWgIface.'"';
            $L[] = ':put "  - Fwanya UDP/'.(string) $wgListenPort.' kwenda VPS (ufw/security groups)"';
            $L[] = ':put "  - Saa ya router na VPS ziwe sahihi (NTP)"';
        } else {
            $L[] = ':put ""';
            $L[] = ':put "================================================================"';
            $L[] = ':put "   SKYmanager Setup IMEKAMILIKA! Hongera!"';
            $L[] = ':put "================================================================"';
        }
        $L[] = ':put ""';
        $L[] = ':put "================================================================"';
        $L[] = ':put "   SKYmanager API Credentials"';
        $L[] = ':put "================================================================"';
        $L[] = ":put \"  Mtumiaji  : {$apiUser}\"";
        $L[] = ":put \"  Nenosiri  : {$apiPassword}\"";
        $L[] = ':put "  Bandari   : '.$apiPort.'"';
        $L[] = ':put ""';
        $L[] = ':put "Hatua Inayofuata:"';
        $L[] = ':put "  1. Nakili Public Key hapo juu"';
        if ($hasWg) {
            $L[] = ':put "  2. Endesha amri ya VPS hapo juu kwenye Linux VPS"';
        }
        $L[] = ':put "  3. Rudi SKYmanager Dashboard > Routers > Test Connection"';
        $L[] = ':put "================================================================"';

        $script = implode("\n", $L);

        $router->refresh();
        app(RouterOnboardingService::class)->recordScriptGenerated(
            $router,
            $scriptWarnings,
            $wgHardFail ? 'wg_required_missing' : null
        );
        $router->forceFill(['bundle_deployment_mode' => 'bundle'])->save();

        Log::info('SKYmanager: Full setup script generated', [
            'router' => $router->name,
            'ip' => $router->ip_address,
        ]);

        return $script;
    }

    /**
     * @return array{handshake_at: ?Carbon, raw: array<string, string>}
     */
    public function fetchWireguardPeerState(Router $router): array
    {
        $this->connectZtp($router);

        try {
            return $this->readWireguardPeerHandshakeAfterConnect();
        } finally {
            $this->disconnect();
        }
    }

    /**
     * After connect(), verify expected hotspot bundle files exist and profile html-directory matches.
     *
     * @return array{ok: bool, issues: list<string>, profile_html_directory: ?string}
     */
    public function verifyHotspotBundle(Router $router): array
    {
        $folder = $router->portal_folder_name;
        if ($folder === null || $folder === '') {
            return [
                'ok' => false,
                'issues' => ['No portal_folder_name — run hotspot bundle sync or generate the setup script.'],
                'profile_html_directory' => null,
            ];
        }

        $this->connectZtp($router);

        try {
            $files = $this->sendCommand(['/file/print']);
            $names = [];
            foreach ($files as $row) {
                if (isset($row['name'])) {
                    $names[] = (string) $row['name'];
                }
            }

            $issues = [];

            foreach (HotspotBundleService::BUNDLE_FILES as $fname) {
                $suffix = $folder.'/'.$fname;
                $found = false;
                foreach ($names as $n) {
                    if ($n === $suffix || str_ends_with($n, '/'.$fname) || str_ends_with($n, $suffix)) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) {
                    $issues[] = "Missing file on router: {$suffix}";
                }
            }

            $profiles = $this->sendCommand(['/ip/hotspot/profile/print']);
            $htmlDir = null;
            foreach ($profiles as $p) {
                if (isset($p['html-directory'])) {
                    $htmlDir = (string) $p['html-directory'];
                    break;
                }
            }

            if ($htmlDir === null) {
                $issues[] = 'Could not read hotspot profile html-directory.';
            } else {
                $expected7 = 'hotspot/'.$folder;
                $expected6 = 'flash/hotspot/'.$folder;
                if ($htmlDir !== $expected7 && $htmlDir !== $expected6 && ! str_contains($htmlDir, $folder)) {
                    $issues[] = "html-directory \"{$htmlDir}\" does not reference expected folder \"{$folder}\".";
                }
            }

            return [
                'ok' => $issues === [],
                'issues' => $issues,
                'profile_html_directory' => $htmlDir,
            ];
        } finally {
            $this->disconnect();
        }
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
