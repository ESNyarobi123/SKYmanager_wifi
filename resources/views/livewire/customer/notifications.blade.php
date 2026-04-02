<div>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200">{{ __('Notifications') }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-neutral-500">
                @if($this->unreadCount > 0)
                    <span class="text-sky-600 dark:text-sky-400 font-medium">{{ $this->unreadCount }}</span> {{ __('unread notification(s)') }}
                @else
                    {{ __('All caught up!') }}
                @endif
            </p>
        </div>
        @if($this->unreadCount > 0)
            <flux:button wire:click="markAllRead" variant="ghost" size="sm">
                <x-lucide name="check-circle" class="size-3.5 me-1.5"/>
                {{ __('Mark all as read') }}
            </flux:button>
        @endif
    </div>

    <div class="flex flex-col bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-neutral-800 dark:border-neutral-700 overflow-hidden">
        @if($this->notifications->isNotEmpty())
            <div class="divide-y divide-gray-100 dark:divide-neutral-700">
                @foreach($this->notifications as $notification)
                    @php
                        $isUnread = is_null($notification->read_at);
                        $data = $notification->data;
                        $message = $data['message'] ?? $data['body'] ?? $data['text'] ?? 'New notification';
                        $title = $data['title'] ?? $data['subject'] ?? null;
                        $type = $data['type'] ?? 'info';
                        $iconColors = [
                            'success' => 'text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-800/30',
                            'warning' => 'text-amber-600 dark:text-amber-400 bg-amber-100 dark:bg-amber-800/30',
                            'error'   => 'text-red-600 dark:text-red-400 bg-red-100 dark:bg-red-800/30',
                            'info'    => 'text-sky-600 dark:text-sky-400 bg-sky-100 dark:bg-sky-800/30',
                        ];
                        $lucideIcons = ['success' => 'check-circle', 'warning' => 'activity', 'error' => 'x-circle', 'info' => 'bell'];
                        $colorClass = $iconColors[$type] ?? $iconColors['info'];
                        $lucideIcon = $lucideIcons[$type] ?? 'bell';
                    @endphp
                    <div
                        class="flex items-start gap-4 px-5 py-4 {{ $isUnread ? 'bg-sky-50/60 dark:bg-sky-900/10' : '' }} hover:bg-gray-50 dark:hover:bg-neutral-700/30 transition-colors"
                        wire:key="notification-{{ $notification->id }}"
                    >
                        <div class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg {{ $colorClass }}">
                            <x-lucide name="{{ $lucideIcon }}" class="size-4"/>
                        </div>
                        <div class="flex-1 min-w-0">
                            @if($title)
                                <p class="text-sm font-semibold text-gray-800 dark:text-neutral-200">{{ $title }}</p>
                            @endif
                            <p class="text-sm {{ $title ? 'text-gray-600 dark:text-neutral-400 mt-0.5' : 'font-medium text-gray-800 dark:text-neutral-200' }}">
                                {{ $message }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-neutral-500 mt-1">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if($isUnread)
                                <span class="size-2 rounded-full bg-sky-500 shrink-0"></span>
                                <button
                                    wire:click="markRead('{{ $notification->id }}')"
                                    class="text-xs text-gray-400 hover:text-gray-600 dark:text-neutral-500 dark:hover:text-neutral-300 transition-colors"
                                >
                                    {{ __('Mark read') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="inline-flex items-center justify-center size-14 rounded-2xl bg-gray-100 dark:bg-neutral-700 mb-4">
                    <x-lucide name="bell" class="size-7 text-gray-400 dark:text-neutral-500"/>
                </div>
                <p class="text-sm font-medium text-gray-500 dark:text-neutral-400">{{ __('No notifications yet') }}</p>
                <p class="text-xs text-gray-400 dark:text-neutral-500 mt-1">{{ __("We'll notify you about subscription expiry, payments, and more.") }}</p>
            </div>
        @endif
    </div>

</div>
