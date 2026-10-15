<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages\Pages;

use App\Filament\Resources\Messages\MessageResource;
use App\Support\Reports\ReportAccess;
use Filament\Resources\Pages\ListRecords;

final class ListMessages extends ListRecords
{
    protected static string $resource = MessageResource::class;

    /** Warns when reports are configured public but run as auth for lack of a captcha. */
    public function getSubheading(): ?string
    {
        return app(ReportAccess::class)->degraded() ? __('admin.message.public_degraded') : null;
    }
}
