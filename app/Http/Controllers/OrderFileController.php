<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItemCustomization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Order files are private (the `order-files` disk for the attachments, the
 * `local` disk for the customizations' artwork) and leave the server only
 * here, always as a download: to the customer who owns the order, and in the
 * panel to whoever may view orders (OrderPolicy).
 */
final class OrderFileController extends Controller
{
    public function customerAttachment(int $id, int $media): StreamedResponse
    {
        $order = Order::query()->where('user_id', Auth::id())->findOrFail($id);

        return $this->attachment($order, $media);
    }

    public function customerArtwork(int $printing): StreamedResponse
    {
        $customization = $this->customization($printing);
        $order = $customization->item?->order;
        abort_unless($order instanceof Order && $order->user_id === Auth::id(), 404);

        return $this->artwork($customization);
    }

    public function adminAttachment(int $media): StreamedResponse
    {
        $file = Media::query()->where('model_type', (new Order)->getMorphClass())->where('collection_name', Order::FILES)->findOrFail($media);
        $order = Order::query()->findOrFail($file->model_id);
        Gate::authorize('view', $order);

        return $this->download($file->disk, $file->getPathRelativeToRoot(), $file->file_name);
    }

    public function adminArtwork(int $printing): StreamedResponse
    {
        $customization = $this->customization($printing);
        $order = $customization->item?->order;
        abort_unless($order instanceof Order, 404);
        Gate::authorize('view', $order);

        return $this->artwork($customization);
    }

    private function attachment(Order $order, int $media): StreamedResponse
    {
        $file = $order->getMedia(Order::FILES)->firstWhere('id', $media);
        abort_unless($file instanceof Media, 404);

        return $this->download($file->disk, $file->getPathRelativeToRoot(), $file->file_name);
    }

    private function customization(int $printing): OrderItemCustomization
    {
        return OrderItemCustomization::query()->with('item.order')->findOrFail($printing);
    }

    private function artwork(OrderItemCustomization $customization): StreamedResponse
    {
        abort_unless(is_string($customization->file) && $customization->file !== '', 404);

        return $this->download(OrderItemCustomization::FILE_DISK, $customization->file, basename($customization->file));
    }

    private function download(string $disk, string $path, string $name): StreamedResponse
    {
        abort_unless(Storage::disk($disk)->exists($path), 404);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: 'file';

        return Storage::disk($disk)->download($path, $base.($extension !== '' ? '.'.$extension : ''), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
