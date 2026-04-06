<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\User;
use App\Services\HotspotBundleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class HotspotBundleController extends Controller
{
    public function manifest(Request $request, Router $router, HotspotBundleService $bundles): BaseResponse
    {
        $this->assertPortalToken($request, $router);

        $customer = $router->user ?? new User;
        $payload = $bundles->manifestPayload(
            $router,
            $customer,
            $request->boolean('refresh'),
            $request->boolean('compare')
        );

        return response()->json($payload, 200, [
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function installRsc(Request $request, Router $router, HotspotBundleService $bundles): Response
    {
        $this->assertPortalToken($request, $router);

        $customer = $router->user ?? new User;
        $bundles->syncBundleMetadata($router, $customer);
        $router->refresh();

        $rosMajor = max(6, min(7, (int) $request->query('ros', 7)));
        $text = $bundles->buildInstallRsc($router, (string) $router->local_portal_token, $rosMajor);

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    public function file(Request $request, Router $router, string $file, HotspotBundleService $bundles): Response
    {
        if (! $bundles->isAllowedFile($file)) {
            abort(404);
        }

        $this->assertPortalToken($request, $router);

        $customer = $router->user ?? new User;
        $body = $bundles->renderFile($router, $customer, $file);

        $contentType = str_ends_with($file, '.js')
            ? 'application/javascript; charset=UTF-8'
            : 'text/html; charset=UTF-8';

        return response($body, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function assertPortalToken(Request $request, Router $router): void
    {
        $token = (string) $request->query('token', '');

        if ($router->local_portal_token === null || $router->local_portal_token === '') {
            abort(403, 'Portal token not provisioned for this router.');
        }

        if (! hash_equals($router->local_portal_token, $token)) {
            abort(403, 'Invalid portal token.');
        }
    }
}
