<x-filament-panels::page>
    <x-filament::section>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.content.sitemap_last') }}</dt>
                <dd class="text-lg font-semibold">{{ $this->getGeneratedAt()?->format('d/m/Y H:i') ?? __('admin.content.sitemap_never') }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.content.sitemap_urls') }}</dt>
                <dd class="text-lg font-semibold">{{ $this->getUrlCount() }}</dd>
            </div>
        </dl>
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.content.sitemap_schedule') }}</p>
    </x-filament::section>
</x-filament-panels::page>
