<?php

declare(strict_types=1);

namespace App\Filament\Resources\Content\LegacyRedirects;

use App\Filament\Resources\Content\LegacyRedirects\Pages\ManageLegacyRedirects;
use App\Models\LegacyRedirect;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/** legacy_redirects (docs/ARCHITECTURE.md §12): stored 301/302/410 for old paths, plus CSV import of a redirect map. */
final class LegacyRedirectResource extends Resource
{
    protected static ?string $model = LegacyRedirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.content');
    }

    public static function getModelLabel(): string
    {
        return __('admin.content.redirect');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.content.redirects');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('from_path')->label(__('admin.content.from_path'))->required()->maxLength(512)->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (string $state): string => LegacyRedirect::normalizePath($state)),
            TextInput::make('to_path')->label(__('admin.content.to_path'))->maxLength(512)
                ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null && $state !== '' ? (str_starts_with($state, 'http') ? $state : LegacyRedirect::normalizePath($state)) : null),
            Select::make('status_code')->label(__('admin.content.status_code'))->options([301 => '301', 302 => '302', 410 => '410'])->default(301)->required()->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_path')
            ->columns([
                TextColumn::make('from_path')->label(__('admin.content.from_path'))->searchable()->sortable()->copyable(),
                TextColumn::make('to_path')->label(__('admin.content.to_path'))->searchable()->placeholder('-'),
                TextColumn::make('status_code')->label(__('admin.content.status_code'))->badge()->color(fn (int $state): string => $state === 410 ? 'danger' : 'gray'),
                TextColumn::make('hits')->label(__('admin.content.hits'))->sortable(),
                TextColumn::make('last_hit_at')->label(__('admin.content.last_hit_at'))->dateTime('d/m/Y H:i')->placeholder('-')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status_code')->label(__('admin.content.status_code'))->options([301 => '301', 302 => '302', 410 => '410']),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->headerActions([
                CreateAction::make(),
                Action::make('importCsv')->label(__('admin.content.import_csv'))->icon('heroicon-o-arrow-up-tray')->color('gray')
                    ->schema([
                        FileUpload::make('csv')->label(__('admin.content.csv'))->required()->disk('local')->directory('tmp/redirects')->visibility('private')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->helperText(__('admin.content.import_csv_hint')),
                    ])
                    ->action(function (array $data): void {
                        $count = self::importCsv(Storage::disk('local')->path((string) $data['csv']));
                        Notification::make()->title(__('admin.content.import_csv_done', ['count' => $count]))->success()->send();
                    }),
            ]);
    }

    /** Rows: from, to, [code]. A header line is skipped when its first cell is not a path. */
    public static function importCsv(string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }
        $count = 0;
        while (($row = fgetcsv($handle, 2048, ',', '"', '\\')) !== false) {
            if (count($row) === 1 && str_contains((string) $row[0], ';')) {
                $row = str_getcsv((string) $row[0], ';', '"', '\\');
            }
            $from = trim((string) ($row[0] ?? ''));
            if ($from === '' || (! str_starts_with($from, '/') && ! str_starts_with($from, 'http'))) {
                continue;
            }
            $to = trim((string) ($row[1] ?? ''));
            $code = (int) ($row[2] ?? 301) ?: 301;
            LegacyRedirect::record($from, $to !== '' ? $to : '/', in_array($code, [301, 302, 410], true) ? $code : 301);
            $count++;
        }
        fclose($handle);

        return $count;
    }

    public static function getPages(): array
    {
        return ['index' => ManageLegacyRedirects::route('/')];
    }
}
