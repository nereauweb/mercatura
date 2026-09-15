<?php

declare(strict_types=1);

namespace App\Filament\Resources\System\ImportLogs;

use App\Filament\Resources\System\ImportLogs\Pages\ListImportLogs;
use App\Models\ImportLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** import_logs, read-only, refreshed every 30 s while an import runs (legacy AdminImportLogTable). */
final class ImportLogResource extends Resource
{
    protected static ?string $model = ImportLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 90;

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.imports');
    }

    public static function getModelLabel(): string
    {
        return __('admin.imports.log');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.imports.logs');
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('imports.run') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->poll('30s')
            ->columns([
                TextColumn::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('import_id')->label(__('admin.imports.import_id'))->sortable()->searchable(),
                TextColumn::make('context')->label(__('admin.imports.context'))->badge()->color('gray'),
                TextColumn::make('event')->label(__('admin.imports.event')),
                TextColumn::make('type')->label(__('admin.imports.type'))->badge()->color(fn (?string $state): string => match ($state) {
                    'error' => 'danger',
                    'warn' => 'warning',
                    'success' => 'success',
                    default => 'gray',
                }),
                TextColumn::make('message')->label(__('admin.imports.message'))->searchable()->wrap()->limit(160),
                TextColumn::make('ref')->label(__('admin.imports.reference'))->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('admin.imports.type'))->options(['info' => 'info', 'success' => 'success', 'warn' => 'warn', 'error' => 'error']),
                SelectFilter::make('context')->label(__('admin.imports.context'))->options(fn (): array => ImportLog::query()->select('context')->distinct()->orderBy('context')->pluck('context', 'context')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListImportLogs::route('/')];
    }
}
