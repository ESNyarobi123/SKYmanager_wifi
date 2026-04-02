<?php

namespace App\Console\Commands;

use App\Models\Router;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('skymanager:backup {--db : Dump the database only} {--configs : Export router configs only}')]
#[Description('Backup SKYmanager: database dump + router config export')]
class SkyManagerBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $backupDir = storage_path('backups/'.now()->format('Y-m-d_H-i-s'));
        @mkdir($backupDir, 0755, true);

        if (! $this->option('configs')) {
            $this->dumpDatabase($backupDir);
        }

        if (! $this->option('db')) {
            $this->exportRouterConfigs($backupDir);
        }

        $this->info("✅  Backup saved to: {$backupDir}");

        return self::SUCCESS;
    }

    private function dumpDatabase(string $dir): void
    {
        $db = config('database.connections.'.config('database.default'));
        $driver = $db['driver'] ?? 'sqlite';
        $file = "{$dir}/database.sql";

        if ($driver === 'sqlite') {
            $src = $db['database'];
            copy($src, "{$dir}/database.sqlite");
            $this->line('  📦  SQLite database copied.');

            return;
        }

        if ($driver === 'mysql') {
            $cmd = sprintf(
                'mysqldump -u%s -p%s %s > %s 2>/dev/null',
                escapeshellarg($db['username']),
                escapeshellarg($db['password']),
                escapeshellarg($db['database']),
                escapeshellarg($file)
            );
            exec($cmd, $out, $code);
            $code === 0
                ? $this->line("  📦  MySQL dump: {$file}")
                : $this->warn("  ⚠️  mysqldump failed (code {$code}) — ensure mysqldump is in PATH.");

            return;
        }

        $this->warn("  ⚠️  Database driver '{$driver}' not supported by backup command.");
    }

    private function exportRouterConfigs(string $dir): void
    {
        $routers = Router::all();

        if ($routers->isEmpty()) {
            $this->line('  ℹ️   No routers to export.');

            return;
        }

        $configDir = "{$dir}/routers";
        @mkdir($configDir, 0755, true);

        foreach ($routers as $router) {
            $data = [
                'id' => $router->id,
                'name' => $router->name,
                'ip_address' => $router->ip_address,
                'api_port' => $router->api_port,
                'hotspot_ssid' => $router->hotspot_ssid,
                'hotspot_interface' => $router->hotspot_interface,
                'hotspot_gateway' => $router->hotspot_gateway,
                'hotspot_network' => $router->hotspot_network,
                'wg_address' => $router->wg_address,
                'exported_at' => now()->toIso8601String(),
            ];
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $router->name);
            file_put_contents("{$configDir}/{$safeName}.json", json_encode($data, JSON_PRETTY_PRINT));
        }

        $this->line("  🌐  Exported {$routers->count()} router config(s) to {$configDir}");
    }
}
