<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use App\Models\Message;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

final class ViewMessage extends ViewRecord
{
    protected static string $resource = MessageResource::class;

    /** Opening a message marks it read, as the legacy admin did. */
    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);
        if ($record instanceof Message && $record->read_at === null) {
            $record->forceFill(['read_at' => now()])->save();
        }

        return $record;
    }
}
