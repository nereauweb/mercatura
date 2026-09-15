<?php

namespace App\Http\Controllers;

use App\Contracts\PaymentConfirmation;
use App\Models\Order;
use App\Models\OrderItemCustomization;
use App\Services\OrderPaymentCompletion;
use App\Support\FrontendDebugLog;
use App\Support\PaymentGateways;
use enshrined\svgSanitize\Sanitizer as SvgSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FrontendOrderController extends Controller
{
    public function list(Request $request)
    {
        return view('frontend.customer.orders');
    }

    public function show(Request $request, $id)
    {
        $order = Order::find($id);

        return view('frontend.customer.order', compact('order'));
    }

    public function stripe_payment_success(Request $request, $id)
    {
        return $this->confirmPayment($request, (int) $id, 'stripe');
    }

    public function stripe_payment_cancel(Request $request, $id)
    {
        $order = Order::find($id);
        FrontendDebugLog::carrelloPagamento('stripe_payment_cancel', [
            'order_id' => $id,
            'payment_gateway' => 'stripe',
            'order_found' => (bool) $order,
            'order_status' => $order?->status,
            'payment_status' => $order?->payment_status,
        ]);

        return view('frontend.pages.checkout.payment_cancel');
    }

    public function paypal_payment_success(Request $request, $id)
    {
        return $this->confirmPayment($request, (int) $id, 'paypal');
    }

    /**
     * Customer returned from a hosted payment: the gateway verifies the
     * provider's state, the order is marked paid once (OrderPaymentCompletion).
     */
    private function confirmPayment(Request $request, int $id, string $method)
    {
        FrontendDebugLog::carrelloPagamento('payment_success:start', ['order_id' => $id, 'payment_gateway' => $method]);
        $order = Order::find($id);
        if (! $order) {
            abort(404);
        }

        $result = app(PaymentGateways::class)->for($method)->confirm($order, $request->query());
        if ($result !== PaymentConfirmation::Confirmed) {
            FrontendDebugLog::carrelloPagamento('payment_success:not_confirmed', ['order_id' => $id, 'result' => $result->value]);

            return view('frontend.pages.checkout.payment_verification', [
                'verification_message' => __($result->messageKey(), ['method' => __('frontend.checkout.payment_'.$method)]),
            ]);
        }

        OrderPaymentCompletion::markPaidIfNeeded($order);
        $request->session()->put('cart', []);
        FrontendDebugLog::carrelloPagamento('payment_success:completed', [
            'order_id' => $order->id,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
        ]);

        return view('frontend.pages.checkout.payment_success', compact('order'));
    }

    public function paypal_payment_cancel(Request $request, $id)
    {
        $order = Order::find($id);
        FrontendDebugLog::carrelloPagamento('paypal_payment_cancel', [
            'order_id' => $id,
            'payment_gateway' => 'paypal',
            'order_found' => (bool) $order,
            'order_status' => $order?->status,
            'payment_status' => $order?->payment_status,
        ]);

        return view('frontend.pages.checkout.payment_cancel');
    }

    public function bank_transfer_order_registered(Request $request)
    {
        $order = Order::where('user_id', Auth::id())->orderBy('created_at', 'desc')->first();
        FrontendDebugLog::carrelloPagamento('bank_transfer_registered_view', [
            'user_id' => Auth::id(),
            'order_id' => $order?->id,
            'order_status' => $order?->status,
            'payment_method' => $order?->payment_method,
        ]);

        return view('frontend.pages.checkout.success', compact('order'));
    }

    /**
     * Stati dell'ordine che consentono ancora il caricamento/aggiornamento dei file dal cliente.
     * Quando l'ordine è stato completato o cancellato l'upload viene bloccato.
     */
    private const ORDER_STATUSES_ALLOWING_UPLOAD = [
        'draft',
        'requested',
        'payment_notified',
        'paid',
        'processing',
    ];

    /**
     * Mime-types accettati per gli allegati degli ordini (incl. file di stampa).
     */
    private const ALLOWED_UPLOAD_MIMETYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/tiff',
        'image/svg+xml',
        'image/svg',
        'application/pdf',
        'application/postscript',
        'application/illustrator',
        'application/octet-stream', // AI/EPS talvolta riportati come octet-stream dal client
    ];

    private const ALLOWED_UPLOAD_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'tif', 'tiff', 'svg', 'pdf', 'ai', 'eps',
    ];

    public function uploadOrderImage(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if (! in_array($order->status, self::ORDER_STATUSES_ALLOWING_UPLOAD, true)) {
            return back()->withErrors([
                'image' => 'Non è possibile caricare file per questo ordine nello stato attuale.',
            ])->withInput();
        }

        $request->validate([
            'image' => [
                'required',
                'file',
                'min:1',
                'max:10240',
                'mimetypes:'.implode(',', self::ALLOWED_UPLOAD_MIMETYPES),
                'mimes:'.implode(',', self::ALLOWED_UPLOAD_EXTENSIONS),
            ],
        ]);

        $upload = $request->file('image');
        $mediaFile = $this->sanitizeSvgUpload($upload);

        $media = $order->addMedia($mediaFile)
            ->usingFileName($this->buildSafeFilename('order_'.$order->id, $upload))
            ->toMediaCollection('order_files');

        FrontendDebugLog::carrelloPagamento('order_upload:file_added', [
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'media_id' => $media->id,
            'mime' => $media->mime_type,
            'size' => $media->size,
        ]);

        return back()->with('success', 'File caricato con successo.');
    }

    public function uploadCustomizationFile(Request $request, $id)
    {
        $printing = OrderItemCustomization::with('item.order')->findOrFail($id);
        $order = optional($printing->item)->order;

        if (! $order || $order->user_id !== Auth::id()) {
            abort(403);
        }

        if (! in_array($order->status, self::ORDER_STATUSES_ALLOWING_UPLOAD, true)) {
            return back()->withErrors([
                'variant_image' => 'Non è possibile aggiornare i file di stampa per questo ordine nello stato attuale.',
            ])->withInput();
        }

        $request->validate([
            'variant_image' => [
                'required',
                'file',
                'min:1',
                'max:20480',
                'mimetypes:'.implode(',', self::ALLOWED_UPLOAD_MIMETYPES),
                'mimes:'.implode(',', self::ALLOWED_UPLOAD_EXTENSIONS),
            ],
        ]);

        $file = $request->file('variant_image');
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_UPLOAD_EXTENSIONS, true)) {
            return back()->withErrors([
                'variant_image' => 'Formato file non supportato.',
            ])->withInput();
        }

        $directory = 'orders/'.$order->id.'/printings';
        $filename = 'printing_'.$printing->id.'_'.now()->timestamp.'_'.Str::random(8).'.'.$extension;

        if ($this->isSvgExtension($extension)) {
            $clean = $this->sanitizeSvgContents(file_get_contents($file->getRealPath()));
            if ($clean === null) {
                return back()->withErrors([
                    'variant_image' => 'Il file SVG non è valido o contiene elementi non consentiti.',
                ])->withInput();
            }
            Storage::disk('public')->put($directory.'/'.$filename, $clean);
            $path = $directory.'/'.$filename;
        } else {
            $path = $file->storeAs($directory, $filename, 'public');
        }

        $previous = $printing->file;
        $printing->update(['file' => $path]);
        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        FrontendDebugLog::carrelloPagamento('printing_upload:file_saved', [
            'order_id' => $order->id,
            'printing_id' => $printing->id,
            'user_id' => Auth::id(),
            'extension' => $extension,
            'path' => $path,
        ]);

        return back()->with('success', 'File di stampa caricato con successo.');
    }

    /**
     * Restituisce true se l'estensione indica un SVG (incl. svgz).
     */
    private function isSvgExtension(string $extension): bool
    {
        return in_array(strtolower($extension), ['svg', 'svgz'], true);
    }

    /**
     * Costruisce un filename "safe" per Spatie Media Library:
     * rimuove path traversal, mantiene l'estensione originale e aggiunge suffisso random.
     */
    private function buildSafeFilename(string $prefix, \Symfony\Component\HttpFoundation\File\UploadedFile $upload): string
    {
        $extension = strtolower($upload->extension() ?: $upload->getClientOriginalExtension() ?: 'bin');
        if (! in_array($extension, self::ALLOWED_UPLOAD_EXTENSIONS, true)) {
            $extension = 'bin';
        }

        return $prefix.'_'.now()->timestamp.'_'.Str::random(8).'.'.$extension;
    }

    /**
     * Se l'upload è un SVG ne scrive una versione sanificata in un file temporaneo
     * e restituisce il nuovo UploadedFile. In caso contrario restituisce l'upload originale.
     * Se la sanificazione fallisce solleva ValidationException.
     */
    private function sanitizeSvgUpload(\Symfony\Component\HttpFoundation\File\UploadedFile $upload): \Symfony\Component\HttpFoundation\File\UploadedFile
    {
        $extension = strtolower($upload->extension() ?: $upload->getClientOriginalExtension());
        if (! $this->isSvgExtension($extension)) {
            return $upload;
        }

        $clean = $this->sanitizeSvgContents(file_get_contents($upload->getRealPath()));
        if ($clean === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'image' => 'Il file SVG non è valido o contiene elementi non consentiti.',
            ]);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'svg_sanitized_');
        file_put_contents($tmpPath, $clean);

        return new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $tmpPath,
            $upload->getClientOriginalName() ?: 'upload.svg',
            'image/svg+xml',
            null,
            true // test-mode: evita i controlli su is_uploaded_file
        );
    }

    /**
     * Sanifica un contenuto SVG rimuovendo script, event handler e reference esterne.
     * Ritorna la stringa sanificata o null se la sanificazione non è stata possibile.
     */
    private function sanitizeSvgContents(string $contents): ?string
    {
        $sanitizer = new SvgSanitizer;
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($contents);

        return $clean === false ? null : $clean;
    }

    public function deleteOrderImage(Request $request, $id, $mediaId)
    {
        $order = Order::findOrFail($id);

        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if (! in_array($order->status, self::ORDER_STATUSES_ALLOWING_UPLOAD, true)) {
            return back()->withErrors([
                'image' => 'Non è possibile rimuovere file per questo ordine nello stato attuale.',
            ]);
        }

        $media = $order->media()->where('id', $mediaId)->firstOrFail();
        $media->delete();

        FrontendDebugLog::carrelloPagamento('order_upload:file_deleted', [
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'media_id' => (int) $mediaId,
        ]);

        return back()->with('success', 'File eliminato con successo.');
    }
}
