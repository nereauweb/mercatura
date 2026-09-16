<?php

namespace App\Http\Controllers;

use App\Actions\Quotations\SendQuotation;
use App\Models\ProductVariant;
use App\Rules\Captcha;
use App\Support\CustomerFormRules;
use App\Support\FrontendDebugLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FrontendQuotationController extends Controller
{
    public function index(Request $request)
    {
        return view('frontend.customer.quotations');
    }

    public function configure(Request $request, string $id)
    {
        FrontendDebugLog::preventivo('configure:start', [
            'article_id' => $id,
        ]);
        $article = ProductVariant::find($id);
        $quotation = $request->session()->has('quotation') ? session('quotation') : [];

        return view('frontend.pages.quotation', compact('article', 'quotation'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        FrontendDebugLog::preventivo('create:start', [
            'consent_gdpr' => $request->has('consent_gdpr') ? (bool) $request->boolean('consent_gdpr') : null,
            'subscribe_newsletter' => $request->has('subscribe_newsletter') ? (bool) $request->boolean('subscribe_newsletter') : null,
            'consent_terms' => $request->has('consent_terms') ? (bool) $request->boolean('consent_terms') : null,
        ]);
        $newProduct = $request->input('new_product');
        if (is_array($newProduct) && array_key_exists('quantity', $newProduct)) {
            $normalizedQuantity = $this->normalizeIntegerString($newProduct['quantity']);
            if ($normalizedQuantity !== null) {
                $newProduct['quantity'] = $normalizedQuantity;
                $request->merge(['new_product' => $newProduct]);
            }
        }
        $request->validate([
            'new_product' => 'required|array',
            'new_product.quantity' => 'required|integer|min:1',
        ]);
        $new_data = $request->except('_token');
        $new_data['new_product']['id'] = Str::random(9);
        $quotation = $request->session()->has('quotation') ? session('quotation') : [];
        $quotation['products'][$new_data['new_product']['id']] = $new_data['new_product'];
        $quotation['customer'] = $new_data['customer'];
        $quotation['consent_gdpr'] = $request->consent_gdpr;
        $quotation['subscribe_newsletter'] = $request->subscribe_newsletter;
        $request->session()->put('quotation', $quotation);
        FrontendDebugLog::preventivo('create:session_updated', [
            'items_count' => count($quotation['products'] ?? []),
        ]);

        return redirect()->route('frontend.home')->with('success', 'Prodotto aggiunto alla richiesta di preventivo');
        // return response()->json($debug);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        FrontendDebugLog::preventivo('store:start', [
            'consent_gdpr' => $request->has('consent_gdpr') ? (bool) $request->boolean('consent_gdpr') : null,
            'subscribe_newsletter' => $request->has('subscribe_newsletter') ? (bool) $request->boolean('subscribe_newsletter') : null,
            'consent_terms' => $request->has('consent_terms') ? (bool) $request->boolean('consent_terms') : null,
        ]);
        $request->validate(array_merge(
            CustomerFormRules::quotationCustomerRules($request),
            [
                'consent_gdpr' => ['required', 'accepted'],
                ...Captcha::rules('request_quotation'),
            ],
        ));
        FrontendDebugLog::preventivo('store:validated_customer');
        $session_quotation = $request->session()->has('quotation') ? session('quotation') : [];

        $new_data = $request->except('_token');

        if (empty($session_quotation['products']) && ! isset($new_data['new_product'])) {
            FrontendDebugLog::preventivo('store:abort_no_products');

            return redirect()->route('frontend.quotation.show')
                ->with('error', 'Nessun prodotto presente nella richiesta di preventivo')
                ->withInput();
        }

        if (isset($new_data['new_product'])) {
            $new_data['new_product']['id'] = Str::random(9);
            $session_quotation['products'][$new_data['new_product']['id']] = $new_data['new_product'];
            $session_quotation['customer'] = $new_data['customer'];
        }

        if (isset($new_data['customer'])) {
            $session_quotation['customer'] = $new_data['customer'];
        }

        if (isset($new_data['new_product']) && (int) ($new_data['new_product']['quantity'] ?? 0) === 0) {
            return back()->withErrors([
                'quotation' => 'Impostare quantità maggiore di 0',
            ])->withInput();
        }
        FrontendDebugLog::preventivo('store:consents_ok', [
            'consent_gdpr' => true,
            'subscribe_newsletter' => $request->boolean('subscribe_newsletter'),
            'consent_terms' => $request->has('consent_terms') ? (bool) $request->boolean('consent_terms') : null,
        ]);

        $quotation = app(SendQuotation::class)->handle(
            (array) ($session_quotation['customer'] ?? []),
            array_values($session_quotation['products']),
            $request->consent_gdpr == '1',
            $request->subscribe_newsletter == '1',
        );
        $quotation_items = array_values($session_quotation['products']);

        $request->session()->pull('quotation.products', 'default');
        FrontendDebugLog::preventivo('store:completed', [
            'quotation_id' => $quotation->id,
            'items_count' => count($quotation_items),
        ]);

        return view('frontend.pages.quotation-sent');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        $quotation = $request->session()->has('quotation') ? session('quotation') : [];
        FrontendDebugLog::preventivo('show', [
            'items_count' => count($quotation['products'] ?? []),
        ]);

        return view('frontend.pages.quotation', compact('quotation'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, string $id)
    {
        $quotation = $request->session()->has('quotation') ? session('quotation') : [];
        $edit_quotation_item = isset($quotation['products'][$id]) ? $quotation['products'][$id] : [];

        // return $quotation;
        return view('frontend.pages.quotation', compact('edit_quotation_item', 'quotation'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $quotation = $request->session()->has('quotation') ? session('quotation') : [];
        if (empty($quotation['products'])) {
            return redirect()->route('frontend.quotation.show')->with('error', 'Nessun prodotto presente nella richiesta di preventivo');
        }
        if (! isset($quotation['products'][$id])) {
            return redirect()->route('frontend.quotation.show')->with('error', 'Prodotto non trovato');
        }
        $normalizedUpdateQuantity = $this->normalizeIntegerString($request->input('update_quantity'));
        if ($normalizedUpdateQuantity !== null) {
            $request->merge(['update_quantity' => $normalizedUpdateQuantity]);
        }
        $validated = $request->validate([
            'update_quantity' => ['required', 'integer', 'min:1'],
            'update_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $quotation['products'][$id]['quantity'] = $validated['update_quantity'];
        $quotation['products'][$id]['notes'] = $validated['update_notes'] ?? '';
        $request->session()->put('quotation', $quotation);

        return redirect()->route('frontend.home')->with('success', 'Preventivo aggiornato');
    }

    private function normalizeIntegerString($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);
        if ($trimmed === '' || ! preg_match('/^[+-]?\d+$/', $trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $request->session()->pull('quotation.products.'.$id, 'default');

        return redirect()->route('frontend.quotation.show')->with('success', 'Prodotto rimosso dalla richiesta di preventivo');
    }
}
