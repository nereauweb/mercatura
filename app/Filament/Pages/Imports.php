<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Contracts\ImportConnector;
use App\Filament\Widgets\ImportRunsWidget;
use App\Jobs\ImportPrintingsJob;
use App\Jobs\ImportProductsJob;
use App\Support\ImportConnectors;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * The legacy "manage imports" screen through the ImportConnector contract:
 * dispatches the product and printing jobs with their flags, shows the
 * queue and the last runs. Connector-specific pages (raw data, maps,
 * markups) belong to the connector packages (docs/02_V2B_ADMIN.md §6).
 * No free-form Artisan runner: the two maintenance actions are explicit.
 */
final class Imports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownOnSquareStack;

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.imports';

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.imports');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.imports.title');
    }

    public function getTitle(): string
    {
        return __('admin.imports.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('imports.run') ?? false;
    }

    /** @return list<ImportConnector> */
    public function connectors(): array
    {
        return app(ImportConnectors::class)->all();
    }

    public function hasEnabledConnector(): bool
    {
        return app(ImportConnectors::class)->enabled() !== [];
    }

    public function queuedJobs(): int
    {
        return (int) DB::table('jobs')->count();
    }

    public function failedJobs(): int
    {
        return (int) DB::table('failed_jobs')->count();
    }

    /** @return array<string, string> */
    private function sourceOptions(): array
    {
        $options = ['all' => __('admin.imports.all_sources')];
        foreach (app(ImportConnectors::class)->enabled() as $connector) {
            $options[$connector->key()] = $connector->label();
        }

        return $options;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dispatchProducts')->label(__('admin.imports.dispatch_products'))->icon(Heroicon::OutlinedCube)
                ->visible(fn (): bool => $this->hasEnabledConnector())
                ->schema([
                    Select::make('process_source')->label(__('admin.imports.source'))->options(fn (): array => $this->sourceOptions())->default('all')->required()->native(false),
                    Toggle::make('download_data')->label(__('admin.imports.flags.download_data'))->default(true),
                    Toggle::make('update_live')->label(__('admin.imports.flags.update_live'))->default(true),
                    Toggle::make('process_product_data')->label(__('admin.imports.flags.process_product_data'))->default(true),
                    Toggle::make('full_products_update')->label(__('admin.imports.flags.full_products_update')),
                    Toggle::make('update_categories')->label(__('admin.imports.flags.update_categories')),
                ])
                ->action(function (array $data): void {
                    ImportProductsJob::dispatch([
                        'download_data' => (bool) $data['download_data'], 'update_live' => (bool) $data['update_live'],
                        'process_product_data' => (bool) $data['process_product_data'], 'full_products_update' => (bool) $data['full_products_update'],
                        'update_categories' => (bool) $data['update_categories'],
                        'process_source' => (string) $data['process_source'],
                    ]);
                    Notification::make()->title(__('admin.imports.dispatched'))->success()->send();
                }),
            Action::make('dispatchCustomizations')->label(__('admin.imports.dispatch_printings'))->icon(Heroicon::OutlinedPaintBrush)->color('gray')
                ->visible(fn (): bool => $this->hasEnabledConnector())
                ->schema([
                    Select::make('process_source')->label(__('admin.imports.source'))->options(fn (): array => $this->sourceOptions())->default('all')->required()->native(false),
                    Toggle::make('download_data')->label(__('admin.imports.flags.download_data'))->default(true),
                    Toggle::make('update_live')->label(__('admin.imports.flags.update_live'))->default(true),
                    Toggle::make('process_print_data')->label(__('admin.imports.flags.process_print_data'))->default(true),
                ])
                ->action(function (array $data): void {
                    ImportPrintingsJob::dispatch([
                        'download_data' => (bool) $data['download_data'], 'update_live' => (bool) $data['update_live'],
                        'process_print_data' => (bool) $data['process_print_data'], 'process_source' => (string) $data['process_source'],
                    ]);
                    Notification::make()->title(__('admin.imports.dispatched'))->success()->send();
                }),
            Action::make('restartQueue')->label(__('admin.imports.restart_queue'))->icon(Heroicon::OutlinedArrowPath)->color('gray')->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('queue:restart');
                    Notification::make()->title(__('admin.imports.restart_queue_done'))->success()->send();
                }),
            Action::make('reindex')->label(__('admin.imports.reindex'))->icon(Heroicon::OutlinedMagnifyingGlass)->color('gray')->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('app:RegenerateSearchIndex');
                    Notification::make()->title(__('admin.imports.reindex_done'))->success()->send();
                }),
        ];
    }

    /** @return array<class-string> */
    protected function getFooterWidgets(): array
    {
        return [ImportRunsWidget::class];
    }
}
