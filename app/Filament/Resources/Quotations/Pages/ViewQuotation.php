<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Resources\Quotations\QuotationResource;
use App\Models\Quotation;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

final class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->successNotificationTitle(__('admin.quotation.deleted'))];
    }

    public function getTitle(): string
    {
        return __('admin.quotation.number', ['id' => $this->getRecord()->getKey()]);
    }

    /** Opening a quotation marks it read, as the legacy admin did. */
    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);
        if ($record instanceof Quotation && $record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }

        return $record;
    }
}
