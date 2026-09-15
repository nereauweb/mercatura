<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section :heading="__('admin.imports.connectors')">
            @if(! $this->hasEnabledConnector())
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.imports.no_connector') }}</p>
            @endif
            <ul class="mt-2 space-y-2">
                @foreach($this->connectors() as $connector)
                    <li class="flex items-center justify-between text-sm">
                        <span><span class="font-medium">{{ $connector->label() }}</span> <span class="text-gray-500">({{ $connector->key() }})</span></span>
                        <x-filament::badge :color="$connector->enabled() ? 'success' : 'gray'">{{ $connector->enabled() ? __('admin.imports.enabled') : __('admin.imports.disabled') }}</x-filament::badge>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
        <x-filament::section :heading="__('admin.imports.queue')">
            <dl class="grid grid-cols-2 gap-4">
                <div><dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.imports.queued_jobs') }}</dt><dd class="text-2xl font-semibold">{{ $this->queuedJobs() }}</dd></div>
                <div><dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.imports.failed_jobs') }}</dt><dd class="text-2xl font-semibold {{ $this->failedJobs() > 0 ? 'text-danger-600' : '' }}">{{ $this->failedJobs() }}</dd></div>
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
