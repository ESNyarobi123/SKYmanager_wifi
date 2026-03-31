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

        if (isset($response[0]) && str_starts_with((string) ($response[0]['!trap'] ?? ''), '!trap')) {
            throw new Exception('MikroTik authentication failed.');
        }

        if (isset($response[0]['ret'])) {
            $challenge = pack('H*', $response[0]['ret']);
            $md5 = md5("\x00".$password.$challenge);

            $this->sendCommand([
                '/login',
                '=name='.$username,
                '=response=00'.$md5,
            ]);
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
     * Generate a RouterOS provisioning script for Zero-Touch Provisioning.
     * Saves a fresh random API user password to the router record before returning.
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

        return implode("\n", [
            '# ============================================================',
            '# SKYmanager Zero-Touch Provisioning Script',
            "# Router : {$router->name}",
            '# Generated: '.now()->toDateTimeString(),
            '# ============================================================',
            '',
            '# --- 1. Identity & Security ---',
            "/system identity set name=\"{$identity}\"",
            '/user add name=sky-api password="'.$apiPassword.'" group=full comment="SKYmanager API User"',
            '/user remove [find name=admin where name!=sky-api]',
            '',
            '# --- 2. VPN Tunnel (SSTP back to VPS) ---',
            '/interface sstp-client add name=vpn-sky connect-to="'.$vpsIp.'" user="'.$identity.'" password="'.$sstpSecret.'" profile=default-encryption disabled=no comment="SKYmanager VPN"',
            '',
            '# --- 3. Hotspot Configuration ---',
            '/ip hotspot profile set [find] html-directory=flash/hotspot',
            "/ip hotspot profile set [find] dns-name=\"{$portalDomain}\"",
            '/ip hotspot profile set [find] login-by=http-chap,http-pap',
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
            '/ip service disable telnet',
            '/ip service disable ftp',
            '/ip service disable www',
            '',
            '# --- 6. Firewall: block API from WAN ---',
            '/ip firewall filter add chain=input protocol=tcp dst-port=8728 src-address=!'.$vpnSubnet.' action=drop comment="Block API outside VPN" place-before=0',
            '',
            '# ============================================================',
            '# Done. Router will connect to VPN and become reachable from VPS.',
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
     * @throws Exception
     */
    private function ensureConnected(): void
    {
        if (! $this->isConnected || ! $this->socket) {
            throw new Exception('MikrotikApiService: not connected. Call connect() first.');
        }
    }
}
