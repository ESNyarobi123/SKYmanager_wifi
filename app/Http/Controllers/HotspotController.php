<?php

namespace App\Http\Controllers;

use App\Models\Router;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotspotController extends Controller
{
    /**
     * Serve a minimal local captive portal HTML page for a specific router (legacy).
     *
     * New deployments should use the multi-file hotspot bundle under
     * `/hotspot-bundle/{router}/{file}?token=…` (see HotspotBundleService).
     *
     * Still supported for routers that only have flat `hotspot/login.html` from an older script.
     *
     * Called in two contexts:
     *  1. Legacy MikroTik fetch: `/tool fetch` with `?router_id={id}` → `hotspot/login.html`.
     *  2. Direct browser access (fallback / testing).
     *
     * The returned HTML embeds the router_id and VPS URL as JS constants.
     * MikroTik macro variables — $(mac), $(ip), $(link-login-only), $(link-orig) —
     * are left as literal strings; MikroTik expands them when serving to clients.
     */
    public function loginHtml(Request $request): Response
    {
        $routerId = (string) $request->query('router_id', '');
        $router = $routerId ? Router::find($routerId) : null;

        $html = view('hotspot.login', [
            'vpsUrl' => rtrim(config('app.url'), '/'),
            'routerId' => $routerId,
            'routerName' => $router?->hotspot_ssid ?: ($router?->name ?: 'WiFi'),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
