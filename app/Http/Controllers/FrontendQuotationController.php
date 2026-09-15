<?php

namespace App\Http\Controllers;

use App\Contracts\NewsletterProvider;
use App\Contracts\TransactionalMailer;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Rules\Captcha;
use App\Support\CaughtExceptionLogger;
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

        $quotation = Quotation::create([
            'customer_email' => $session_quotation['customer']['email'],
            'customer_type' => $session_quotation['customer']['customer_type'],
            'customer_company' => $session_quotation['customer']['company'],
            'customer_name' => $session_quotation['customer']['name'],
            'customer_surname' => $session_quotation['customer']['surname'],
            'customer_phone' => $session_quotation['customer']['phone'],
            'customer_activity' => $session_quotation['customer']['activity'],
        ]);
        FrontendDebugLog::preventivo('store:quotation_created', [
            'quotation_id' => $quotation->id,
        ]);

        $quotation_items = [];
        foreach ($session_quotation['products'] as $quotation_product) {
            $quotation_product['quantity'] = intval($quotation_product['quantity']);
            $quotation->items()->create([
                'sku' => $quotation_product['sku'],
                'name' => $quotation_product['name'],
                'quantity' => $quotation_product['quantity'],
                'customization' => $quotation_product['printing'] ?? __('frontend.quotation.no'),
                'image' => $quotation_product['image'] ?? '',
                'color' => $quotation_product['color'] ?? '',
                'size' => $quotation_product['size'] ?? '',
                'notes' => $quotation_product['notes'] ?? '',
            ]);
            array_push($quotation_items, $quotation_product);
        }
        $mailer = app(TransactionalMailer::class);
        // user notification
        $mailer->attribute('quotation_date', date('d/m/Y H:i', strtotime($quotation->created_at)));
        $mailer->attribute('customer_type', $session_quotation['customer']['customer_type']);
        $mailer->attribute('customer_company', $session_quotation['customer']['company']);
        $mailer->attribute('customer_activity', $session_quotation['customer']['activity']);
        $mailer->attribute('customer_name', $session_quotation['customer']['name']);
        $mailer->attribute('customer_surname', $session_quotation['customer']['surname']);
        $mailer->attribute('customer_email', $session_quotation['customer']['email']);
        $mailer->attribute('customer_phone', $session_quotation['customer']['phone']);
        $mailer->attribute('quotation_items', $quotation_items);
        $mailer->attribute('gdpr', $request->consent_gdpr == '1' ? 'Sì' : 'No');
        $mailer->attribute('newsletter', $request->subscribe_newsletter == '1' ? 'Sì' : 'No');
        // links
        $mailer->attribute('contacts_link', route('frontend.contacts.index'));
        $mailer->attribute('gdpr_link', url('/').'/contenuti/privacy-policy');
        $mailer->to($session_quotation['customer']['email']);
        $mailer->send('quotation_customer');
        $mailer->reset();
        // admin notification
        $mailer->attribute('quotation_date', date('d/m/Y H:i', strtotime($quotation->created_at)));
        $mailer->attribute('customer_type', $session_quotation['customer']['customer_type']);
        $mailer->attribute('customer_company', $session_quotation['customer']['company']);
        $mailer->attribute('customer_activity', $session_quotation['customer']['activity']);
        $mailer->attribute('customer_name', $session_quotation['customer']['name']);
        $mailer->attribute('customer_surname', $session_quotation['customer']['surname']);
        $mailer->attribute('customer_email', $session_quotation['customer']['email']);
        $mailer->attribute('customer_phone', $session_quotation['customer']['phone']);
        $mailer->attribute('quotation_items', $quotation_items);
        $mailer->attribute('gdpr', $request->consent_gdpr == '1' ? 'Sì' : 'No');
        $mailer->attribute('newsletter', $request->subscribe_newsletter == '1' ? 'Sì' : 'No');
        // links
        $mailer->attribute('contacts_link', route('frontend.contacts.index'));
        $mailer->attribute('gdpr_link', url('/').'/contenuti/privacy-policy');
        $mailer->to(config('emails.technical'));
        $mailer->to(config('emails.merchant'));
        $mailer->send('quotation_admin');

        if ($request->subscribe_newsletter == '1') {
            FrontendDebugLog::newsletter('quotation:subscribe_newsletter:start', [
                'source' => 'quotation',
                'quotation_id' => $quotation->id,
            ]);
            try {
                app(NewsletterProvider::class)->subscribe([
                    'email' => $session_quotation['customer']['email'],
                    'name' => $session_quotation['customer']['name'],
                    'surname' => $session_quotation['customer']['surname'],
                    'phone' => $session_quotation['customer']['phone'],
                    'customer_type' => $session_quotation['customer']['customer_type'],
                    'company' => $session_quotation['customer']['company'],
                    'activity' => $session_quotation['customer']['activity'],
                ]);
                FrontendDebugLog::newsletter('quotation:subscribe_newsletter:brevo_ok', [
                    'source' => 'quotation',
                    'quotation_id' => $quotation->id,
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
            } catch (\Exception $e) {
                CaughtExceptionLogger::error('FrontendQuotationController::store newsletter subscribe failed', $e, [
                    'quotation_id' => $quotation->id,
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
                FrontendDebugLog::newsletter('quotation:subscribe_newsletter:provider_error', [
                    'source' => 'quotation',
                    'quotation_id' => $quotation->id,
                    'error' => $e->getMessage(),
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
            }
        }

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
