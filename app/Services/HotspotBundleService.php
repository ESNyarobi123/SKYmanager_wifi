<?php

namespace App\Services;

use App\Models\Router;
use App\Models\User;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class HotspotBundleService
{
    public const BUNDLE_FILES = [
        'login.html',
        'rlogin.html',
        'redirect.html',
        'alogin.html',
        'flogin.html',
        'status.html',
        'logout.html',
        'md5.js',
    ];

    public function __construct(
        private StandalonePortalHtmlService $standalonePortal,
    ) {}

    public static function folderSegment(Router $router): string
    {
        return 'sky-'.$router->getKey();
    }

    public function htmlDirectoryForRouterOs(int $rosMajor, Router $router): string
    {
        $segment = self::folderSegment($router);

        return $rosMajor >= 7 ? "hotspot/{$segment}" : "flash/hotspot/{$segment}";
    }

    public function dstPathForFile(string $file, int $rosMajor, Router $router): string
    {
        $this->assertAllowedFile($file);

        return $this->htmlDirectoryForRouterOs($rosMajor, $router).'/'.$file;
    }

    public function isAllowedFile(string $file): bool
    {
        return in_array($file, self::BUNDLE_FILES, true);
    }

    public function assertAllowedFile(string $file): void
    {
        if (! $this->isAllowedFile($file)) {
            throw new InvalidArgumentException("Invalid hotspot bundle file: {$file}");
        }
    }

    /**
     * @return array<string, string>
     */
    public function generateAllFiles(Router $router, User $customer): array
    {
        $out = [];
        foreach (self::BUNDLE_FILES as $name) {
            $out[$name] = $this->renderFile($router, $customer, $name);
        }

        return $out;
    }

    public function computeBundleHash(Router $router, User $customer): string
    {
        $files = $this->generateAllFiles($router, $customer);
        ksort($files);
        $payload = '';
        foreach ($files as $name => $body) {
            $payload .= $name."\n".$body."\n";
        }

        return hash('sha256', $payload);
    }

    /**
     * Recompute SHA-256 over ordered file contents and persist router metadata.
     *
     * @return array{bundle_hash: string, bundle_version: string, folder: string}
     */
    public function syncBundleMetadata(Router $router, User $customer): array
    {
        $hash = $this->computeBundleHash($router, $customer);
        $version = (string) config('skymanager.portal_bundle_version');
        $folder = self::folderSegment($router);

        $router->forceFill([
            'portal_bundle_version' => $version,
            'portal_bundle_hash' => $hash,
            'portal_folder_name' => $folder,
            'portal_generated_at' => now(),
        ])->save();

        return [
            'bundle_hash' => $hash,
            'bundle_version' => $version,
            'folder' => $folder,
        ];
    }

    public function renderFile(Router $router, User $customer, string $file): string
    {
        $this->assertAllowedFile($file);

        if ($file === 'login.html') {
            return view('hotspot.standalone-login', array_merge(
                $this->standalonePortal->viewDataForRouter($router, $customer),
                ['bundleMode' => true]
            ))->render();
        }

        if ($file === 'md5.js') {
            return $this->md5JsContents();
        }

        $viewKey = pathinfo($file, PATHINFO_FILENAME);

        return view('hotspot.bundle.'.$viewKey)->render();
    }

    public function md5JsContents(): string
    {
        $path = resource_path('hotspot/md5.js');

        if (! File::exists($path)) {
            return "/* md5.js missing on VPS — copy from RouterOS default hotspot or redeploy SKYmanager */\n";
        }

        return File::get($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function manifestPayload(Router $router, User $customer, bool $refresh = false, bool $compareLive = false): array
    {
        if ($refresh || $router->portal_bundle_hash === null || $router->portal_bundle_hash === '') {
            $this->syncBundleMetadata($router, $customer);
        }
        $router->refresh();

        $liveHash = null;
        $stale = null;
        if ($compareLive) {
            $liveHash = $this->computeBundleHash($router, $customer);
            $stale = ! hash_equals((string) $router->portal_bundle_hash, $liveHash);
        }

        return [
            'router_id' => $router->id,
            'bundle_version' => $router->portal_bundle_version,
            'bundle_hash' => $router->portal_bundle_hash,
            'folder_segment' => $router->portal_folder_name ?? self::folderSegment($router),
            'html_directory_routeros_7' => $this->htmlDirectoryForRouterOs(7, $router),
            'html_directory_routeros_6' => $this->htmlDirectoryForRouterOs(6, $router),
            'files' => self::BUNDLE_FILES,
            'generated_at' => $router->portal_generated_at?->toIso8601String(),
            'app_bundle_version' => config('skymanager.portal_bundle_version'),
            'live_bundle_hash' => $liveHash,
            'stale_vs_database' => $stale,
        ];
    }

    public function publicFileUrl(Router $router, string $file, string $token): string
    {
        $this->assertAllowedFile($file);
        $base = rtrim(config('app.url'), '/');

        return $base.'/hotspot-bundle/'.$router->id.'/'.rawurlencode($file).'?token='.rawurlencode($token);
    }

    /**
     * RouterOS /tool fetch lines (no leading slash on command — caller adds).
     *
     * @return list<string>
     */
    public function routerFetchCommandStrings(Router $router, string $token, int $rosMajor): array
    {
        $lines = [];
        foreach (self::BUNDLE_FILES as $name) {
            $url = $this->publicFileUrl($router, $name, $token);
            $dst = $this->dstPathForFile($name, $rosMajor, $router);
            $lines[] = '/tool fetch url="'.$url.'" dst-path="'.$dst.'" keep-result=yes';
        }

        return $lines;
    }

    /**
     * Idempotent .rsc fragment: fetch all bundle files + set hotspot html-directory.
     */
    public function buildInstallRsc(Router $router, string $token, int $rosMajor): string
    {
        $dir = $this->htmlDirectoryForRouterOs($rosMajor, $router);
        $segment = self::folderSegment($router);

        $lines = [
            '# ============================================================',
            '# SKYmanager hotspot bundle — install.rsc',
            '# Router: '.$router->name,
            '# Target html-directory: '.$dir,
            '# App bundle version: '.config('skymanager.portal_bundle_version'),
            '# Router bundle hash (DB): '.($router->portal_bundle_hash ?? '—'),
            '# Generated: '.now()->toDateTimeString(),
            '# ============================================================',
            ':put "SKYmanager: fetching hotspot bundle into '.$dir.'"',
            '',
        ];

        foreach (self::BUNDLE_FILES as $name) {
            $url = $this->publicFileUrl($router, $name, $token);
            $dst = $this->dstPathForFile($name, $rosMajor, $router);
            $lines[] = ':do { /tool fetch url="'.$url.'" dst-path="'.$dst.'" keep-result=yes } on-error={ :put "SKYmanager FETCH ERROR: '.$name.'" }';
        }

        $lines[] = '';
        $lines[] = '/ip hotspot profile set [find] html-directory="'.$dir.'"';
        $lines[] = ':put "SKYmanager: html-directory set to '.$dir.'"';
        $lines[] = ':put "SKYmanager: bundle '.$segment.' complete"';

        return implode("\n", $lines);
    }
}
