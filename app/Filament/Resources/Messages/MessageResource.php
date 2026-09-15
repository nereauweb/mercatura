<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages;

use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Resources\Messages\Pages\ViewMessage;
use App\Filament\Resources\Messages\Schemas\MessageInfolist;
use App\Filament\Resources\Messages\Tables\MessagesTable;
use App\Models\Message;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Contact form messages, read-only; opening one marks it read. */
final class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): string
    {
        return __('admin.nav.sales');
    }

    public static function getModelLabel(): string
    {
        return __('admin.message.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.message.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Message::query()->whereNull('read_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): string
    {
        return __('admin.common.unread');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return MessageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MessagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessages::route('/'),
            'view' => ViewMessage::route('/{record}'),
        ];
    }
}
