<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\System\ImportLogs\ImportLogResource;
use App\Models\ImportLog;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** The last 20 import runs (import_logs "start" rows) with completion, duration and errors, as the legacy screen computed them. */
final class ImportRunsWidget extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.imports.history'))
            ->poll('30s')
            ->query(fn (): Builder => ImportLog::query()
                ->where('event', 'start')
                ->whereIn('context', ['job_import_products', 'job_import_printings', 'full_import'])
                ->select('import_logs.*')
                ->selectSub(DB::table('import_logs as e')->selectRaw('MAX(e.created_at)')->whereColumn('e.import_id', 'import_logs.import_id')->where('e.event', 'end'), 'completed_at')
                ->selectSub(DB::table('import_logs as x')->selectRaw('COUNT(*)')->whereColumn('x.import_id', 'import_logs.import_id')->where('x.type', 'error'), 'error_count')
                ->orderByDesc('created_at'))
            ->paginated([20])
            ->columns([
                TextColumn::make('created_at')->label(__('admin.imports.run'))->dateTime('d/m/Y H:i'),
                TextColumn::make('context')->label(__('admin.imports.context'))->badge()->color('gray'),
                TextColumn::make('import_id')->label(__('admin.imports.import_id')),
                IconColumn::make('completed')->label(__('admin.imports.completed'))->boolean()->getStateUsing(fn (ImportLog $record): bool => $record->getAttribute('completed_at') !== null),
                TextColumn::make('duration')->label(__('admin.imports.duration'))
                    ->getStateUsing(fn (ImportLog $record): ?string => $record->getAttribute('completed_at') ? (string) (int) $record->created_at->diffInMinutes(\Illuminate\Support\Carbon::parse((string) $record->getAttribute('completed_at')), true) : null)
                    ->placeholder('-'),
                TextColumn::make('error_count')->label(__('admin.imports.errors'))->badge()->color(fn ($state): string => (int) $state > 0 ? 'danger' : 'gray'),
            ])
            ->recordActions([
                Action::make('log')->label(__('admin.imports.view_log'))->icon('heroicon-o-document-magnifying-glass')
                    ->url(fn (ImportLog $record): string => ImportLogResource::getUrl('index', ['tableSearch' => $record->import_id])),
            ]);
    }
}
