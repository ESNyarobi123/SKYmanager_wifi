<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RadiusService
{
    private const AUTH_PORT = 1812;

    private const ACCT_PORT = 1813;

    private const CODE_ACCESS_REQUEST = 1;

    private const CODE_ACCESS_ACCEPT = 2;

    private const CODE_ACCESS_REJECT = 3;

    private const ATTR_USER_NAME = 1;

    private const ATTR_USER_PASSWORD = 2;

    private const ATTR_NAS_IP = 4;

    private const ATTR_NAS_PORT = 5;

    private const ATTR_SERVICE_TYPE = 6;

    private const TIMEOUT = 5;

    /**
     * Test RADIUS authentication for a username/password pair.
     *
     * @return array{success: bool, code: int, message: string, latency_ms: float}
     */
    public function testAuth(
        string $server,
        string $secret,
        string $username,
        string $password,
        int $port = self::AUTH_PORT
    ): array {
        $start = microtime(true);
        $identifier = rand(0, 255);
        $authenticator = random_bytes(16);

        $encryptedPassword = $this->encryptPassword($password, $secret, $authenticator);

        $attrs = '';
        $attrs .= $this->buildAttribute(self::ATTR_USER_NAME, $username);
        $attrs .= $this->buildAttribute(self::ATTR_USER_PASSWORD, $encryptedPassword);
        $attrs .= $this->buildAttribute(self::ATTR_NAS_IP, inet_pton('127.0.0.1'));
        $attrs .= $this->buildAttribute(self::ATTR_NAS_PORT, pack('N', 0));
        $attrs .= $this->buildAttribute(self::ATTR_SERVICE_TYPE, pack('N', 8));

        $length = 20 + strlen($attrs);
        $packet = pack('CCn', self::CODE_ACCESS_REQUEST, $identifier, $length)
            .$authenticator
            .$attrs;

        $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        if (! $socket) {
            return ['success' => false, 'code' => 0, 'message' => 'Failed to create UDP socket.', 'latency_ms' => 0];
        }

        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => self::TIMEOUT, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => self::TIMEOUT, 'usec' => 0]);

        $sent = @socket_sendto($socket, $packet, strlen($packet), 0, $server, $port);

        if ($sent === false) {
            socket_close($socket);

            return ['success' => false, 'code' => 0, 'message' => "Could not send to {$server}:{$port} — check server/port.", 'latency_ms' => 0];
        }

        $response = '';
        $from = '';
        $fromPort = 0;
        $bytes = @socket_recvfrom($socket, $response, 4096, 0, $from, $fromPort);
        socket_close($socket);

        $latency = round((microtime(true) - $start) * 1000, 2);

        if ($bytes === false || strlen($response) < 4) {
            return ['success' => false, 'code' => 0, 'message' => 'No response from RADIUS server (timeout or unreachable).', 'latency_ms' => $latency];
        }

        $code = ord($response[0]);
        $message = match ($code) {
            self::CODE_ACCESS_ACCEPT => 'Access-Accept — authentication successful.',
            self::CODE_ACCESS_REJECT => 'Access-Reject — invalid credentials.',
            default => "Unexpected response code: {$code}.",
        };

        return [
            'success' => $code === self::CODE_ACCESS_ACCEPT,
            'code' => $code,
            'message' => $message,
            'latency_ms' => $latency,
        ];
    }

    /**
     * Query the radacct table for currently online sessions.
     *
     * @return Collection<int, object>
     */
    public function getOnlineSessions(): Collection
    {
        return DB::connection('radius')
            ->table('radacct')
            ->whereNull('acctstoptime')
            ->orderByDesc('acctstarttime')
            ->get();
    }

    /**
     * Disconnect a RADIUS session by its acctsessionid via CoA (or DB stop).
     */
    public function stopSession(int $radacctid): void
    {
        DB::connection('radius')
            ->table('radacct')
            ->where('radacctid', $radacctid)
            ->update([
                'acctstoptime' => now(),
                'acctterminatecause' => 'Admin-Reset',
            ]);
    }

    /**
     * Encrypt User-Password as per RFC 2865 §5.2.
     */
    private function encryptPassword(string $password, string $secret, string $authenticator): string
    {
        $password = str_pad($password, (int) (ceil(strlen($password) / 16) * 16), "\x00");
        $encrypted = '';
        $last = $authenticator;

        for ($i = 0; $i < strlen($password); $i += 16) {
            $b = md5($secret.$last, true);
            $chunk = substr($password, $i, 16);
            $xored = $b ^ $chunk;
            $encrypted .= $xored;
            $last = $xored;
        }

        return $encrypted;
    }

    /**
     * Build a RADIUS TLV attribute.
     */
    private function buildAttribute(int $type, string $value): string
    {
        return chr($type).chr(strlen($value) + 2).$value;
    }
}
