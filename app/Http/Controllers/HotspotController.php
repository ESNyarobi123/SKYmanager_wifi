<?php

namespace App\Http\Controllers;

use App\Services\MikrotikApiService;
use Illuminate\Http\Response;

class HotspotController extends Controller
{
    /**
     * Serve the CPD-compatible hotspot login.html page.
     *
     * This endpoint is fetched by MikroTik via `/tool fetch` during ZTP, and also
     * served directly to browsers via the hotspot html-directory fallback.
     *
     * The HTML contains MikroTik macro variables ($(mac), $(ip), etc.) which
     * MikroTik expands server-side when it serves the file from flash/hotspot/.
     */
    public function loginHtml(): Response
    {
        $portalUrl = config('app.url').'/portal';

        $service = app(MikrotikApiService::class);
        $html = $service->buildLoginHtml($portalUrl);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
