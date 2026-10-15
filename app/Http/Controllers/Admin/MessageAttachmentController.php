<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Report attachments live on the private `reports` disk and leave it only
 * here (docs/06_USER_REPORTS.md §4): for whoever may view the message, always
 * as a download, never rendered by the browser inside the site.
 */
final class MessageAttachmentController extends Controller
{
    public function __invoke(Media $media): StreamedResponse
    {
        $message = $media->model;
        abort_unless($message instanceof Message && $media->collection_name === Message::ATTACHMENTS, 404);
        Gate::authorize('view', $message);

        $extension = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION));
        $name = Str::slug(pathinfo($media->name, PATHINFO_FILENAME)) ?: 'allegato';

        return Storage::disk($media->disk)->download($media->getPathRelativeToRoot(), $name.'.'.$extension, [
            'Content-Type' => (string) $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
