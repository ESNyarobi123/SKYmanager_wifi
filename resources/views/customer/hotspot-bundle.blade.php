<x-layouts::customer :title="__('Hotspot bundle')">
    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Hotspot bundle') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">
                {{ __('Router: :name', ['name' => $router->name]) }}
            </p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 p-5 space-y-3 text-sm">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('App bundle version') }}</p>
                    <p class="font-mono text-gray-900 dark:text-neutral-100">{{ $manifest['app_bundle_version'] ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('Router last sync version') }}</p>
                    <p class="font-mono text-gray-900 dark:text-neutral-100">{{ $manifest['bundle_version'] ?? '—' }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('Bundle hash (SHA-256)') }}</p>
                    <p class="font-mono text-xs break-all text-gray-900 dark:text-neutral-100">{{ $manifest['bundle_hash'] ?? '—' }}</p>
                </div>
                @if(array_key_exists('live_bundle_hash', $manifest) && $manifest['live_bundle_hash'] !== null)
                    <div class="sm:col-span-2">
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('Live computed hash') }}</p>
                        <p class="font-mono text-xs break-all text-gray-900 dark:text-neutral-100">{{ $manifest['live_bundle_hash'] }}</p>
                    </div>
                @endif
                @if(($manifest['stale_vs_database'] ?? false) === true)
                    <div class="sm:col-span-2 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 px-3 py-2 text-amber-800 dark:text-amber-200 text-xs">
                        {{ __('Stored hash differs from live generated content — click Regenerate on My Routers or re-open this page to sync.') }}
                    </div>
                @endif
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('Folder segment') }}</p>
                    <p class="font-mono text-gray-900 dark:text-neutral-100">{{ $manifest['folder_segment'] ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ __('Generated at') }}</p>
                    <p class="text-gray-900 dark:text-neutral-100">{{ $manifest['generated_at'] ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-neutral-700 bg-white dark:bg-neutral-800 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-neutral-700 font-medium text-sm text-gray-800 dark:text-neutral-200">
                {{ __('Bundle files') }}
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-neutral-700">
                @foreach($manifest['files'] ?? [] as $fname)
                    @php
                        $previewUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                            'customer.plans.hotspot-bundle-file',
                            now()->addMinutes(30),
                            ['routerId' => $router->id, 'file' => $fname]
                        );
                        $downloadUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                            'customer.plans.hotspot-bundle-file',
                            now()->addMinutes(30),
                            ['routerId' => $router->id, 'file' => $fname, 'download' => 1]
                        );
                    @endphp
                    <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm">
                        <span class="font-mono text-gray-800 dark:text-neutral-200">{{ $fname }}</span>
                        <span class="flex gap-2">
                            <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="text-sky-600 hover:underline text-xs font-medium">{{ __('Preview') }}</a>
                            <a href="{{ $downloadUrl }}" class="text-sky-600 hover:underline text-xs font-medium">{{ __('Download') }}</a>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-neutral-700 bg-gray-50 dark:bg-neutral-900/40 px-4 py-3 text-xs text-gray-600 dark:text-neutral-400 space-y-2">
            <p>
                {{ __('These are the same files your MikroTik setup script downloads automatically (steps 13–14). Use My Routers → copy script → paste in New Terminal for a full install; use the links here only if you need to inspect or download a single file.') }}
            </p>
            <p class="text-gray-500 dark:text-neutral-500">
                {{ __('Regenerate the setup script from My Routers after changing plans, branding, or bundle version.') }}
            </p>
        </div>
    </div>
</x-layouts::customer>
