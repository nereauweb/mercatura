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
 * here (docs/06_USER_REPORTS.md §4), for whoever may view the message: as a
 * download, or (preview) shown inline when it is an image. Images were
 * re-encoded from their pixels on upload; PDFs are never shown inline.
 */
final class MessageAttachmentController extends Controller
{
    /** MIME types the panel may show inline (re-encoded images only). */
    public const PREVIEWABLE = ['image/png', 'image/jpeg', 'image/webp'];

    public function __invoke(Media $media): StreamedResponse
    {
        $this->authorizeAttachment($media);

        $extension = strtolower(pathinfo($media->file_name, PATHINFO_EXTENSION));
        $name = Str::slug(pathinfo($media->name, PATHINFO_FILENAME)) ?: 'allegato';

        return Storage::disk($media->disk)->download($media->getPathRelativeToRoot(), $name.'.'.$extension, [
            'Content-Type' => (string) $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function preview(Media $media): StreamedResponse
    {
        $this->authorizeAttachment($media);
        abort_unless(in_array($media->mime_type, self::PREVIEWABLE, true), 404);

        return Storage::disk($media->disk)->response($media->getPathRelativeToRoot(), null, [
            'Content-Type' => (string) $media->mime_type,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    private function authorizeAttachment(Media $media): void
    {
        $message = $media->model;
        abort_unless($message instanceof Message && $media->collection_name === Message::ATTACHMENTS, 404);
        Gate::authorize('view', $message);
    }
}
