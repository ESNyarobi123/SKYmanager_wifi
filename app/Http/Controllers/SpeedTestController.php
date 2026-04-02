<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpeedTestController extends Controller
{
    /**
     * Latency ping endpoint — returns minimal JSON.
     */
    public function ping(): JsonResponse
    {
        return response()->json(['pong' => true, 'ts' => now()->toISOString()]);
    }

    /**
     * Download test — stream random bytes to the client.
     * ?size=N where N is megabytes (default 5, max 20).
     */
    public function download(Request $request): StreamedResponse
    {
        $sizeMb = min((int) $request->query('size', 5), 20);
        $bytes = $sizeMb * 1024 * 1024;
        $chunkSize = 65536;

        return response()->stream(function () use ($bytes, $chunkSize) {
            $sent = 0;
            while ($sent < $bytes) {
                $chunk = min($chunkSize, $bytes - $sent);
                echo str_repeat('0', $chunk);
                $sent += $chunk;
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => $bytes,
            'Cache-Control' => 'no-store, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Upload test — receive bytes from the client and discard them.
     */
    public function upload(Request $request): JsonResponse
    {
        $size = strlen($request->getContent());

        return response()->json(['received_bytes' => $size]);
    }
}
