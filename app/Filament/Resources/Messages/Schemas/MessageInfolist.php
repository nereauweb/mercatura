<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Schemas;

use App\Enums\MessageType;
use App\Filament\Resources\Messages\AttachmentPreview;
use App\Models\Message;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class MessageInfolist
{
    /**
     * The attachments with a thumbnail for images and the download link (escaped by AttachmentPreview).
     *
     * @return list<string>
     */
    private static function attachmentLinks(Message $record): array
    {
        return $record->getMedia(Message::ATTACHMENTS)->map(fn ($media): string => AttachmentPreview::html($media))->values()->all();
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.message.sections.sender'))
                    ->columns(3)
                    ->components([
                        TextEntry::make('type')->label(__('admin.message.type'))->badge(),
                        TextEntry::make('reporter_role')->label(__('admin.message.reporter_role'))->placeholder('-'),
                        TextEntry::make('user_id')->label(__('admin.message.account'))->placeholder('-')
                            ->formatStateUsing(fn (Message $record): string => (string) ($record->user->email ?? '-')),
                        TextEntry::make('created_at')->label(__('admin.common.created_at'))->dateTime('d/m/Y H:i'),
                        TextEntry::make('read_at')->label(__('admin.common.read_at'))->dateTime('d/m/Y H:i')->placeholder('-'),
                        TextEntry::make('company')->label(__('admin.customer.company'))->placeholder('-'),
                        TextEntry::make('name')->label(__('admin.customer.name'))->placeholder('-'),
                        TextEntry::make('surname')->label(__('admin.customer.surname'))->placeholder('-'),
                        TextEntry::make('activity')->label(__('admin.customer.activity'))->placeholder('-'),
                        TextEntry::make('email')->label(__('admin.customer.email'))->copyable(),
                        TextEntry::make('phone')->label(__('admin.customer.phone'))->placeholder('-'),
                        IconEntry::make('subscribe_newsletter')->label(__('admin.message.subscribe_newsletter'))->boolean()
                            ->visible(fn (Message $record): bool => $record->type !== MessageType::Report),
                    ]),
                Section::make(__('admin.message.sections.content'))
                    ->components([
                        TextEntry::make('kind')->label(__('admin.message.kind'))->badge()
                            ->visible(fn (Message $record): bool => $record->type === MessageType::Report),
                        TextEntry::make('subject')->label(__('admin.message.subject'))->weight('bold'),
                        TextEntry::make('message')->label(__('admin.message.message'))->prose(),
                    ]),
                Section::make(__('admin.message.sections.report'))
                    ->columns(3)
                    ->visible(fn (Message $record): bool => $record->type === MessageType::Report)
                    ->components([
                        TextEntry::make('page_url')->label(__('admin.message.page_url'))->placeholder('-')
                            ->url(fn (Message $record): ?string => $record->page_url, shouldOpenInNewTab: true)->columnSpanFull(),
                        TextEntry::make('forwarded_at')->label(__('admin.message.forwarded'))->dateTime('d/m/Y H:i')->placeholder('-'),
                        TextEntry::make('forwarded_by')->label(__('admin.message.forwarded_by'))->placeholder('-')
                            ->formatStateUsing(fn (Message $record): string => (string) ($record->forwarder->name ?? '-')),
                        TextEntry::make('forward_reference')->label(__('admin.message.forward_reference'))->placeholder('-')->copyable(),
                        TextEntry::make('context.area')->label(__('admin.message.area'))->placeholder('-')
                            ->formatStateUsing(fn (string $state): string => __('admin.message.areas.'.$state)),
                        TextEntry::make('context.route')->label(__('admin.message.route'))->placeholder('-'),
                        TextEntry::make('context.viewport')->label(__('admin.message.viewport'))->placeholder('-'),
                        TextEntry::make('context.user_agent')->label(__('admin.message.user_agent'))->placeholder('-')->columnSpanFull(),
                        TextEntry::make('screenshot_consent_at')->label(__('admin.message.screenshot_consent'))->placeholder('-')
                            ->formatStateUsing(fn (Message $record): string => $record->screenshot_consent_at === null ? '-'
                                : $record->screenshot_consent_at->format('d/m/Y H:i').' · '.__('admin.message.screenshot_consent_version', ['version' => (string) $record->screenshot_consent_version])),
                        TextEntry::make('context.js_errors')->label(__('admin.message.js_errors'))->placeholder('-')
                            ->listWithLineBreaks()->columnSpanFull(),
                        TextEntry::make('attachments')->label(__('admin.message.attachments'))->placeholder('-')->columnSpanFull()
                            ->state(fn (Message $record): array => self::attachmentLinks($record))
                            ->listWithLineBreaks()->html(),
                    ]),
            ]);
    }
}
