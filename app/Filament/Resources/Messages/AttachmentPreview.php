<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages;

use App\Http\Controllers\Admin\MessageAttachmentController;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * HTML of a report attachment in the panel (docs/06_USER_REPORTS.md §4): a
 * thumbnail for images, opening the full image in a new tab, and the
 * download link; PDFs only the link. Every value is escaped here, the
 * callers render it as HTML.
 */
final class AttachmentPreview
{
    public static function html(Media $media, bool $download = true): string
    {
        $meta = sprintf('<span class="text-gray-500">%s · %s</span>', e((string) $media->mime_type), e($media->human_readable_size));
        $link = $download
            ? sprintf(' <a href="%s" style="text-decoration:underline;font-weight:600">%s</a>', e(route('admin.messages.attachment', $media->id)), e(__('admin.message.download')))
            : '';
        $name = sprintf('<span class="font-medium">%s</span>', e($media->name));

        if (! in_array($media->mime_type, MessageAttachmentController::PREVIEWABLE, true)) {
            return sprintf('<span style="display:inline-flex;flex-wrap:wrap;align-items:center;gap:.5rem">%s %s%s</span>', $name, $meta, $link);
        }

        $preview = e(route('admin.messages.attachment.preview', $media->id));

        return sprintf(
            '<span style="display:inline-flex;flex-direction:column;gap:.25rem"><a href="%s" target="_blank" rel="noopener" title="%s"><img src="%s" alt="%s" loading="lazy" style="display:block;max-height:12rem;width:auto;max-width:100%%;border-radius:.5rem;border:1px solid rgba(127,127,127,.3);object-fit:contain"></a><span style="display:inline-flex;flex-wrap:wrap;align-items:center;gap:.5rem">%s %s%s</span></span>',
            $preview,
            e(__('admin.message.open_full')),
            $preview,
            e($media->name),
            $name,
            $meta,
            $link,
        );
    }
}
