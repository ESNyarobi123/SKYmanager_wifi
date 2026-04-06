<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Log;

class RouterCredentialSyncService
{
    /**
     * Issue a new ZTP/API password, bump credential version, clear mismatch flags.
     */
    public function rotateZtpPassword(Router $router): string
    {
        $password = bin2hex(random_bytes(12));

        $router->forceFill([
            'ztp_api_password' => $password,
            'api_credential_version' => ($router->api_credential_version ?? 0) + 1,
            'api_credentials_updated_at' => now(),
            'credential_mismatch_suspected' => false,
            'last_error_code' => null,
            'last_error_message' => null,
        ])->save();

        Log::info('SKYmanager: ZTP API password rotated', [
            'router_id' => $router->id,
            'credential_version' => $router->api_credential_version,
        ]);

        return $password;
    }

    public function markCredentialMismatch(Router $router, string $reason): void
    {
        $router->forceFill([
            'credential_mismatch_suspected' => true,
            'last_error_code' => 'cred_mismatch',
            'last_error_message' => $reason,
        ])->save();
    }

    public function clearCredentialMismatch(Router $router): void
    {
        $router->forceFill(['credential_mismatch_suspected' => false])->save();
    }
}
