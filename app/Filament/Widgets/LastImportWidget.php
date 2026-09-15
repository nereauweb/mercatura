<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ImportLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Rows of the most recent import run (import_logs), as the legacy dashboard showed. */
final class LastImportWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.last_import'))
            ->emptyStateHeading(__('admin.dashboard.no_import'))
            ->query(function (): Builder {
                $lastImportId = ImportLog::query()->max('import_id');

                return ImportLog::query()->where('import_id', $lastImportId ?? -1)->latest('id')->limit(10);
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label(__('admin.dashboard.import.date'))->dateTime('d/m/Y H:i'),
                TextColumn::make('context')->label(__('admin.dashboard.import.context')),
                TextColumn::make('event')->label(__('admin.dashboard.import.event')),
                TextColumn::make('type')->label(__('admin.dashboard.import.type'))->badge()->color(fn (?string $state): string => match ($state) {
                    'error' => 'danger',
                    'warn' => 'warning',
                    'success' => 'success',
                    default => 'gray',
                }),
                TextColumn::make('message')->label(__('admin.dashboard.import.message'))->limit(80)->wrap(),
            ]);
    }
}
