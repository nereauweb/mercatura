<?php

namespace App\Http\Controllers;

use App\Contracts\NewsletterProvider;
use App\Contracts\TransactionalMailer;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\ImportData\VariantPrintingColor;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Rules\Captcha;
use App\Support\CaughtExceptionLogger;
use App\Support\Connectors\PrintingPipeline;
use App\Support\CustomerFormRules;
use App\Support\FrontendDebugLog;
use App\Support\PaymentGateways;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FrontendCartController extends Controller
{
    public function cart(Request $request)
    {
        $session_cart = $request->session()->has('cart') ? session('cart') : [];
        $cart = $this->session_data_to_cart($session_cart);
        $is_cart = true;

        return view('frontend.pages.cart', compact('cart', 'is_cart'));
    }

    public function add_to_cart(Request $request)
    {
        $request->validate([
            'add_to_cart' => ['required', 'string'],
        ]);
        $decodedCartItem = json_decode($request->input('add_to_cart'), true);
        if (! is_array($decodedCartItem)) {
            return back()->with('error', 'Impossibile aggiungere il prodotto al carrello. Riprova.');
        }
        $normalizedArticles = $this->normalizeCartArticles($decodedCartItem['articles'] ?? null);
        if (empty($normalizedArticles)) {
            return back()->with('error', 'Impossibile aggiungere il prodotto al carrello. Verifica le quantità selezionate.');
        }
        $decodedCartItem['articles'] = $normalizedArticles;
        $decodedCartItem['printings'] = $this->normalizeIntegerList($decodedCartItem['printings'] ?? []);
        $decodedCartItem['has_packaging'] = $this->normalizeCheckboxLike($decodedCartItem['has_packaging'] ?? 0);

        $session_cart = $request->session()->has('cart') ? session('cart') : [];
        $cart_item_id = Str::random(9);
        $session_cart[$cart_item_id] = $decodedCartItem;
        $request->session()->put('cart', $session_cart);
        FrontendDebugLog::carrelloPagamento('add_to_cart', [
            'cart_item_id' => $cart_item_id,
        ]);

        return redirect()->route('frontend.cart.index')->with('success', 'Carrello aggiornato');
    }

    public function remove_from_cart(Request $request, $id)
    {
        $session_cart = $request->session()->has('cart') ? session('cart') : [];
        unset($session_cart[$id]);
        $request->session()->put('cart', $session_cart);
        FrontendDebugLog::carrelloPagamento('remove_from_cart', [
            'cart_item_id' => $id,
        ]);

        return redirect()->route('frontend.cart.index')->with('success', 'Carrello aggiornato');
    }

    public function checkout(Request $request)
    {
        FrontendDebugLog::carrelloPagamento('checkout:start');
        $session_cart = $request->session()->has('cart') ? session('cart') : [];
        if (empty($session_cart)) {
            FrontendDebugLog::carrelloPagamento('checkout:empty_cart');

            return back()->withErrors([
                'checkout' => 'Carrello vuoto',
            ]);
        }
        FrontendDebugLog::carrelloPagamento('checkout:redirect_account');

        return redirect()->route('frontend.checkout.account');
    }

    public function account(Request $request)
    {
        FrontendDebugLog::carrelloPagamento('account:start');
        $session_cart = $request->session()->has('cart') ? session('cart') : [];
        if (empty($session_cart)) {
            FrontendDebugLog::carrelloPagamento('account:empty_cart');

            return redirect()->route('frontend.cart.index')->withErrors([
                'checkout' => 'Carrello vuoto',
            ]);
        }
        $cart = $this->session_data_to_cart($session_cart);
        $is_cart = false;
        if (\Auth::check()) {
            $checkoutBlockCode = $this->checkoutBlockCode(Auth::user());
            if ($checkoutBlockCode !== null) {
                FrontendDebugLog::carrelloPagamento('account:blocked_checkout', [
                    'reason' => $checkoutBlockCode,
                ]);

                return redirect()->route('frontend.cart.index')->withErrors([
                    'checkout' => $this->checkoutBlockMessage($checkoutBlockCode),
                ]);
            }
            $province_list = Customer::PROVINCES;

            return view('frontend.pages.checkout.account', compact('cart', 'is_cart', 'province_list'));
        }

        return view('frontend.pages.checkout.login', compact('cart', 'is_cart'));
    }

    public function attempt_login(Request $request): RedirectResponse
    {
        FrontendDebugLog::carrelloPagamento('checkout_login:start');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            ...Captcha::rules('login'),
        ]);
        unset($credentials[Captcha::field()]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            FrontendDebugLog::carrelloPagamento('checkout_login:success');

            return redirect()->intended(route('frontend.checkout.account'));
        }

        FrontendDebugLog::carrelloPagamento('checkout_login:invalid_credentials');

        return back()->withErrors([
            'auth' => 'Login fallito: credenziali errate',
        ])->onlyInput('email');
    }

    public function register_form(Request $request)
    {
        if (\Auth::check()) {
            return redirect()->route('frontend.checkout.account');
        }
        $session_cart = $request->session()->has('cart') ? session('cart') : [];
        if (empty($session_cart)) {
            return redirect()->route('frontend.cart.index')->withErrors([
                'checkout' => 'Carrello vuoto',
            ]);
        }
        $cart = $this->session_data_to_cart($session_cart);
        $is_cart = false;
        $province_list = Customer::PROVINCES;

        return view('frontend.pages.checkout.register', compact('cart', 'is_cart', 'province_list'));
    }

    public function register(Request $request)
    {
        FrontendDebugLog::registrazioneCliente('checkout_register:start');

        $validated = $request->validate(array_merge(
            CustomerFormRules::registerAuthRules($request),
            Captcha::rules('register'),
        ));
        FrontendDebugLog::registrazioneCliente('checkout_register:consents_validated', [
            'consent_gdpr' => (bool) $request->boolean('consent_gdpr'),
            'consent_terms' => (bool) $request->boolean('consent_terms'),
            'subscribe_newsletter' => (bool) $request->boolean('subscribe_newsletter'),
        ]);
        $user = User::create([
            'name' => $request->name.' '.$request->surname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        if (! $user) {
            FrontendDebugLog::registrazioneCliente('checkout_register:user_create_failed');

            return back()->withErrors(['msg' => 'Errore nella creazione utente'])->withInput();
        }
        $user->assignRole('customer');
        $billing_address = CustomerAddress::create([
            'address' => $request->bill_address,
            'province' => $request->bill_province,
            'city' => $request->bill_city,
            'zip_code' => $request->bill_zip_code,
            'country' => $request->bill_country,
            'notes' => $request->bill_notes,
        ]);
        $shipping_address = CustomerAddress::create([
            'address' => $request->shipping_address,
            'province' => $request->shipping_province,
            'city' => $request->shipping_city,
            'zip_code' => $request->shipping_zip_code,
            'country' => $request->shipping_country,
            'notes' => $request->shipping_notes,
        ]);
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_type' => $request->customer_type ?? null,
            'email' => $request->email,
            'phone' => $request->phone ?? null,
            'name' => $request->name ?? null,
            'surname' => $request->surname ?? null,
            'company' => $request->company ?? null,
            'activity' => $request->activity ?? null,
            'tax_code' => $request->tax_code ?? null,
            'vat_code' => Customer::normalizedVatForStore($request->customer_type, $request->vat_code ?? null),
            'sdi_code' => $request->sdi_code ?? null,
            'ipa_code' => Customer::normalizedIpaForStore($request->customer_type, $request->ipa_code ?? null),
            'cig_code' => Customer::normalizedCigForStore($request->customer_type, $request->cig_code ?? null),
            'pec' => $request->pec ?? null,
            'shipping_address_id' => $shipping_address->id,
            'billing_address_id' => $billing_address->id,
        ]);
        FrontendDebugLog::registrazioneCliente('checkout_register:customer_created', [
            'user_id' => $user->id,
            'customer_id' => $customer?->id,
        ]);
        Auth::login($user);

        $mailer = app(TransactionalMailer::class);
        $mailer->attribute('name', $user->name);
        $mailer->to($user->email);
        $mailer->send('welcome');

        FrontendDebugLog::newsletter('checkout_register:subscribe_newsletter:start', [
            'source' => 'checkout_register',
            'user_id' => $user->id,
            'opt_in' => (bool) $request->boolean('subscribe_newsletter'),
        ]);
        if ($request->boolean('subscribe_newsletter')) {
            try {
                app(NewsletterProvider::class)->subscribe([
                    'email' => $request->email,
                    'name' => $request->name,
                    'surname' => $request->surname,
                    'phone' => $request->phone,
                    'customer_type' => $request->customer_type,
                    'company' => $request->company,
                    'activity' => $request->activity,
                ]);
                FrontendDebugLog::newsletter('checkout_register:subscribe_newsletter:brevo_ok', [
                    'source' => 'checkout_register',
                    'user_id' => $user->id,
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
            } catch (\Exception $e) {
                CaughtExceptionLogger::error('FrontendCartController::checkout_register newsletter subscribe failed', $e, [
                    'user_id' => $user->id,
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
                FrontendDebugLog::newsletter('checkout_register:subscribe_newsletter:provider_error', [
                    'source' => 'checkout_register',
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
            }
        }

        $session_cart = $request->session()->has('cart') ? session('cart') : [];
        if (empty($session_cart)) {
            FrontendDebugLog::registrazioneCliente('checkout_register:empty_cart_after_register');

            return redirect()->route('frontend.cart.index')->withErrors([
                'checkout' => 'Carrello vuoto',
            ]);
        }
        $cart = $this->session_data_to_cart($session_cart);
        $is_cart = false;
        FrontendDebugLog::registrazioneCliente('checkout_register:payment_view', [
            'user_id' => $user->id,
        ]);

        return view('frontend.pages.checkout.payment', compact('cart', 'is_cart'));
    }

    public function payment(Request $request)
    {
        FrontendDebugLog::carrelloPagamento('payment:start', [
            'user_id' => Auth::id(),
            'consent_gdpr' => $request->has('consent_gdpr') ? $request->boolean('consent_gdpr') : null,
            'consent_terms' => $request->has('consent_terms') ? $request->boolean('consent_terms') : null,
        ]);
        $validated = $request->validate(array_merge(
            CustomerFormRules::fiscalAndBillingRules($request),
            CustomerFormRules::profileEmailRules(Auth::id()),
        ));
        FrontendDebugLog::carrelloPagamento('payment:validated', [
            'user_id' => Auth::id(),
        ]);

        $customer = Auth::user()->customer;
        $customer->update([
            'customer_type' => $request->customer_type,
            'email' => $request->email,
            'phone' => $request->phone,
            'name' => $request->name,
            'surname' => $request->surname,
            'company' => $request->company,
            'activity' => $request->activity,
            'tax_code' => $request->tax_code,
            'vat_code' => Customer::normalizedVatForStore($request->customer_type, $request->vat_code),
            'sdi_code' => $request->sdi_code,
            'ipa_code' => Customer::normalizedIpaForStore($request->customer_type, $request->ipa_code),
            'cig_code' => Customer::normalizedCigForStore($request->customer_type, $request->cig_code),
            'pec' => $request->pec,
        ]);
        $customer->billing_address->update([
            'address' => $request->bill_address,
            'province' => $request->bill_province,
            'city' => $request->bill_city,
            'zip_code' => $request->bill_zip_code,
            'country' => $request->bill_country,
            'notes' => $request->bill_notes,
        ]);
        $customer->shipping_address->update([
            'address' => $request->shipping_address,
            'province' => $request->shipping_province,
            'city' => $request->shipping_city,
            'zip_code' => $request->shipping_zip_code,
            'country' => $request->shipping_country,
            'notes' => $request->shipping_notes,
        ]);

        $session_cart = $request->session()->has('cart') ? session('cart') : [];
        if (empty($session_cart)) {
            FrontendDebugLog::carrelloPagamento('payment:empty_cart');

            return redirect()->route('frontend.cart.index')->withErrors([
                'checkout' => 'Carrello vuoto',
            ]);
        }
        $cart = $this->session_data_to_cart($session_cart);
        $is_cart = false;
        FrontendDebugLog::carrelloPagamento('payment:render_checkout', [
            'user_id' => Auth::id(),
            'cart_items' => count($cart['items'] ?? []),
            'total_taxed_price' => $cart['total_taxed_price'] ?? null,
        ]);

        return view('frontend.pages.checkout.payment', compact('cart', 'is_cart'));
    }

    public function store_order(Request $request)
    {
        $checkoutBlockCode = $this->checkoutBlockCode(Auth::user());
        if ($checkoutBlockCode !== null) {
            return redirect()->route('frontend.cart.index')->withErrors([
                'checkout' => $this->checkoutBlockMessage($checkoutBlockCode),
            ]);
        }
        FrontendDebugLog::carrelloPagamento('store_order:start', [
            'user_id' => Auth::id(),
            'consent_gdpr' => $request->has('consent_gdpr') ? $request->boolean('consent_gdpr') : null,
            'consent_terms' => $request->has('consent_terms') ? $request->boolean('consent_terms') : null,
            'payment_method_raw' => $request->input('payment_method'),
        ]);
        $request->validate([
            'payment_method' => ['required', Rule::in(config('mercatura.checkout.payment_methods', []))],
            'consent_gdpr' => ['accepted'],
            'consent_terms' => ['accepted'],
        ]);
        $session_cart = $request->session()->has('cart') ? session('cart') : [];
        if (empty($session_cart)) {
            FrontendDebugLog::carrelloPagamento('store_order:empty_cart');

            return redirect()->route('frontend.cart.index')->withErrors([
                'checkout' => 'Carrello vuoto',
            ]);
        }
        $cart = $this->session_data_to_cart($session_cart);
        $user = Auth::user();
        $customer = $user->customer;
        $order = Order::create([
            'user_id' => $user->id,
            'customer_id' => $customer->id,
            'items_price' => $cart['items_price'],
            'delivery_cost' => $cart['delivery_cost'],
            'total_price' => $cart['total_price'],
            'total_tax' => $cart['tax'],
            'total_taxed_price' => $cart['total_taxed_price'],
            'address' => $customer->shipping_address->address,
            'province' => $customer->shipping_address->province,
            'city' => $customer->shipping_address->city,
            'zip_code' => $customer->shipping_address->zip_code,
            'country' => $customer->shipping_address->country,
            'notes' => $customer->shipping_address->notes,
            'status' => 'requested',
            'payment_method' => $request->payment_method,
            'payment_status' => 'unpaid',
        ]);
        FrontendDebugLog::carrelloPagamento('store_order:order_persisted', [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'items_lines' => count($cart['items'] ?? []),
            'total_taxed_price' => $order->total_taxed_price,
            'payment_status' => $order->payment_status,
        ]);
        foreach ($cart['items'] as $cart_item) {
            $order_item = $order->items()->create([
                'quantity' => $cart_item['quantity'],
                'price' => $cart_item['price'],
                'unit_price' => $cart_item['unit_price'],
                'product_id' => $cart_item['product']->id,
                'product_sku' => $cart_item['product']->sku,
                'product_name' => $cart_item['product']->name,
                'product_image_url' => $cart_item['product']->cover(true),
            ]);
            foreach ($cart_item['articles'] as $cart_item_article) {
                $order_item->articles()->create([
                    'article_id' => $cart_item_article['article']->id,
                    'article_sku' => $cart_item_article['article']->sku,
                    'article_size_label' => $cart_item_article['article']->size->label,
                    'article_color_label' => $cart_item_article['article']->color->label,
                    'article_image_url' => $cart_item_article['article']->cover(true),
                    'quantity' => $cart_item_article['quantity'],
                    'unit_price' => $cart_item_article['unit_price'],
                    'price' => $cart_item_article['quantity_price'],
                ]);
            }
            foreach ($cart_item['printings'] as $printing_color) {
                $order_item->printings()->create([
                    'printing_variant_color_id' => $printing_color->id,
                    'printing_label' => $printing_color->printing_label(),
                ]);
            }
        }

        if ($order->payment_method == 'bank_transfer') {
            FrontendDebugLog::carrelloPagamento('store_order:payment_branch', [
                'branch' => 'bank_transfer',
                'order_id' => $order->id,
            ]);
            $order->send_notification('stored', 'user');
            $order->send_notification('stored', 'admin');
            $request->session()->put('cart', []);
            FrontendDebugLog::carrelloPagamento('store_order:bank_transfer:completed', [
                'order_id' => $order->id,
                'cart_cleared' => true,
            ]);

            return view('frontend.pages.checkout.finalized', compact('order'));
        }

        $gateways = app(PaymentGateways::class);
        if ($gateways->has($order->payment_method)) {
            try {
                $order->send_notification('stored', 'user');
                $order->send_notification('stored', 'admin');

                // The gateway (config mercatura.payments.gateways) prepares the hosted payment.
                $redirectUrl = $gateways->for($order->payment_method)->start($order, $request->user());
            } catch (\Throwable $e) {
                CaughtExceptionLogger::error('FrontendCartController::store_order payment start failed', $e, [
                    'order_id' => $order->id,
                    'payment_method' => $order->payment_method,
                ]);
                FrontendDebugLog::carrelloPagamento('store_order:payment:error', [
                    'order_id' => $order->id,
                    'payment_method' => $order->payment_method,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            return redirect($redirectUrl);
        }

        FrontendDebugLog::carrelloPagamento('store_order:unsupported_payment_method', [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
        ]);

        return back()->withErrors([
            'checkout' => 'Impossibile processare la richiesta',
        ]);
    }

    private function session_data_to_cart($session_cart)
    {
        FrontendDebugLog::prezzoCarrello('******** CARICAMENTO CARRELLO');
        $cart = [
            'items' => [],
            'items_price' => 0,
            'delivery_cost' => 16,
            'total_price' => 0,
            'total_taxed_price' => 0,
            'total_additional_costs' => 0,
            'delivery_days' => 4,
            'delivery_date' => Carbon::now()->addDays(4),
        ];
        foreach ($session_cart as $session_cart_item_id => $sci) {
            FrontendDebugLog::prezzoCarrello('--- Processazione richiesta ---');
            $cart_item = [];
            $cart_item['id'] = $session_cart_item_id;
            $cart_item['price'] = 0;
            $cart_item['quantity'] = 0;
            $cart_item['additional_costs'] = 0;
            foreach ($sci['articles'] as $sci_article) {
                $cart_item['quantity'] += $sci_article[1];
            }
            FrontendDebugLog::prezzoCarrello('Quantità totale richiesta: '.$cart_item['quantity']);
            $cart_item['has_packaging'] = $sci['has_packaging'] ?? false;
            $minimum = 0;
            $print_processing_days = 0;
            $under_minimum = false;
            $cart_item_articles = [];
            FrontendDebugLog::prezzoCarrello('--- Calcolo prezzi articoli ---');
            foreach ($sci['articles'] as $sci_article) {
                $article = ProductVariant::find($sci_article[0]);
                FrontendDebugLog::prezzoCarrello("Articolo: $article->sku");
                $article_quantity = $sci_article[1];
                FrontendDebugLog::prezzoCarrello("Quantità articolo richiesta: $article_quantity");
                $article_original_price = $article->price_per_quantity($cart_item['quantity'], true);
                FrontendDebugLog::prezzoCarrello("Prezzo originario (determinato da quantità totale della richiesta): $article_original_price");
                $article_markup_percent = $article->get_markup_percent($cart_item['quantity'], $article_original_price);
                FrontendDebugLog::prezzoCarrello("Markup articolo (determinato da quantità totale della richiesta x prezzo originario su tabella markup): $article_markup_percent %");
                $article_markup = round($article_original_price * ($article_markup_percent / 100), 2);
                $article_unit_price = $article_original_price + $article_markup;
                $article_additional_costs = $article_quantity * $article->additional_unit_costs_per_quantity($article_quantity);
                $cart_item['additional_costs'] += $article_additional_costs;
                $article_quantity_price = $article_quantity * $article_unit_price;
                $cart_item['price'] += $article_quantity_price;
                $cart['items_price'] += $article_quantity_price;
                FrontendDebugLog::prezzoCarrello("Prezzo singolo (prezzo originario + markup): $article_unit_price | Quantità articolo richiesta: $article_quantity | Prezzo quantità (prezzo singolo x quantità articolo richiesta): $article_quantity_price | Costi aggiuntivi: $article_additional_costs");
                $cart_item_article = [
                    'article' => $article,
                    'quantity' => $article_quantity,
                    'unit_price' => $article_unit_price,
                    'quantity_price' => $article_quantity_price,
                    'additional_costs' => $article_additional_costs,
                ];
                if (! empty($sci['printings'])) {
                    FrontendDebugLog::prezzoCarrello('--- Calcolo personalizzazioni articolo ---');
                    foreach ($sci['printings'] as $sci_printing) {
                        $main_printing_color = VariantPrintingColor::find($sci_printing);
                        if (! PrintingPipeline::colorIsLive($main_printing_color)) {
                            continue;
                        }
                        FrontendDebugLog::prezzoCarrello("[Personalizzazione richiesta] VariantPrintingColor $main_printing_color->id");
                        // article printing color
                        $printing_color = $main_printing_color->sibling($article->id); // ?
                        $printing_price = $printing_color->calculate_print_price($cart_item['quantity'], $article_quantity, $cart_item['has_packaging'], false, $article_markup_percent);
                        $printing_label = $printing_color->printing_label();
                        FrontendDebugLog::prezzoCarrello("[Personalizzazione utilizzata] VariantPrintingColor $printing_color->id | $printing_label | Quantità totale: ".$cart_item['quantity']." | Quantità articolo: $article_quantity | Packaging: ".($cart_item['has_packaging'] ? 'Sì' : 'No')." | Markup (da markup articolo): $article_markup_percent %");
                        $cart_item['price'] += $printing_price['price'];
                        $cart['items_price'] += $printing_price['price'];
                        FrontendDebugLog::prezzoCarrello('[Personalizzazione con markup] '.$printing_price['quantity'].' x '.$printing_price['unit_price'].' = '.$printing_price['price']);
                        // packaging
                        if ($cart_item['has_packaging']) {
                            $cart_item['price'] += $printing_price['packaging_price'];
                            $cart['items_price'] += $printing_price['packaging_price'];
                            FrontendDebugLog::prezzoCarrello('[Personalizzazione packaging] '.$printing_price['packaging_quantity'].' x '.$printing_price['packaging_unit_price'].' = '.$printing_price['packaging_price']);
                        }
                    }
                }
                array_push($cart_item_articles, $cart_item_article);
            }
            if (! empty($sci['printings'])) {
                FrontendDebugLog::prezzoCarrello('--- Calcolo avviamento e impianto personalizzazioni ---');
                foreach ($sci['printings'] as $sci_printing) {
                    $printing_color = VariantPrintingColor::find($sci_printing);
                    if (! PrintingPipeline::colorIsLive($printing_color)) {
                        continue;
                    }
                    $printing_label = $printing_color->printing_label();
                    FrontendDebugLog::prezzoCarrello("Personalizzazione: $printing_label (ID VariantPrintingColor $printing_color->id)");
                    // start_cost
                    if ($printing_color->start_cost > 0) {
                        $start_cost = $printing_color->start_cost;
                        $cart_item['price'] += $start_cost;
                        $cart['items_price'] += $start_cost;
                        FrontendDebugLog::prezzoCarrello('[Avviamento] '.$printing_color->start_cost.' (originale: '.$printing_color->original_start_cost.')');
                    }
                    // setup
                    $setup_price = $printing_color->setup * $printing_color->setup_multiplier;
                    $cart_item['price'] += $setup_price;
                    $cart['items_price'] += $setup_price;
                    FrontendDebugLog::prezzoCarrello("[Impianto] Prezzo: $printing_color->setup (originale: $printing_color->original_setup) | Moltiplicatore: $printing_color->setup_multiplier | Prezzo finale impianto: $setup_price");
                    // get print technique
                    $printing = $printing_color->printing_size->printing;
                    // set minimum
                    $this_minimum = $printing->minimum_quantity;
                    if ($minimum == 0) {
                        $minimum = $this_minimum ?? 0;
                    } else {
                        if ($minimum < $this_minimum) {
                            $minimum = $this_minimum ?? 0;
                        }
                    }
                    // set processing_days
                    if ($printing->processing_days > $print_processing_days) {
                        $print_processing_days = $printing->processing_days;
                    }
                }
            }
            // check minimum
            if ($cart_item['quantity'] < $minimum) {
                $original_under_minimum = 35;
                $under_minimum = 40;
                $cart_item['price'] += $under_minimum;
                $cart['items_price'] += $under_minimum;
                FrontendDebugLog::prezzoCarrello("--- Quantità totale sotto soglia minima ($minimum pz), applicato sovrapprezzo fisso al totale: + $under_minimum");
            }
            // item product
            $cart_item['product'] = $cart_item_articles[0]['article']->product;
            // delivery days
            $delivery_days = $cart_item['product']->processing_days() + $print_processing_days;
            if ($cart['delivery_days'] < $delivery_days) {
                $cart['delivery_days'] = $delivery_days;
            }
            // item articles
            $cart_item['articles'] = $cart_item_articles;
            // item printings
            $cart_item_printings = [];
            foreach ($sci['printings'] as $sci_printing) {
                $printing_color = VariantPrintingColor::find($sci_printing);
                if (! PrintingPipeline::colorIsLive($printing_color)) {
                    continue;
                }
                array_push($cart_item_printings, $printing_color);
            }
            $cart_item['printings'] = $cart_item_printings;
            // item overall unit price
            $cart_item['unit_price'] = round($cart_item['price'] / $cart_item['quantity'], 2);
            array_push($cart['items'], $cart_item);
            $cart['total_additional_costs'] += $cart_item['additional_costs'];
            FrontendDebugLog::prezzoCarrello('Totali richiesta: Prezzo totale (senza IVA): '.$cart_item['price'].' | Quantità: '.$cart_item['quantity'].' | Prezzo unitario (senza IVA): '.$cart_item['unit_price'].' | Costi addizionali: '.$cart_item['additional_costs'].' ');
        }
        FrontendDebugLog::prezzoCarrello('--- Calcolo totali carrello ---');
        if ($cart['items_price'] > 500) {
            $cart['delivery_cost'] = 0;
        }
        $cart['delivery_date'] = Carbon::now()->addDays($cart['delivery_days']);
        $cart['total_price'] = round($cart['items_price'] + $cart['delivery_cost'], 2);
        $cart['tax'] = round($cart['total_price'] * 0.22, 2);
        $cart['total_taxed_price'] = $cart['total_price'] + $cart['tax'] + $cart['total_additional_costs'];
        FrontendDebugLog::prezzoCarrello('Totali carrello: Costo spedizione: '.$cart['delivery_cost'].' | Imponibile: '.$cart['total_price'].' | IVA: '.$cart['tax'].' | Costi addizionali: '.$cart['total_additional_costs'].' | Costo finale (IVA e costi addizionali inclusi): '.$cart['total_taxed_price'].' | Giorni di lavorazione: '.$cart['delivery_days'].' | Data di consegna prevista: '.$cart['delivery_date']);

        return $cart;
    }

    private function checkoutBlockCode(User $user): ?string
    {
        if ($user->hasRole('admin')) {
            return 'admin';
        }
        if (! $user->hasRole('customer')) {
            return 'role_not_customer';
        }
        if (! $user->customer) {
            return 'customer_missing';
        }

        return null;
    }

    private function checkoutBlockMessage(string $blockCode): string
    {
        if ($blockCode === 'customer_missing') {
            return 'Si è verificato un problema con il tuo account. Contatta l\'assistenza per completare l\'ordine.';
        }

        return 'Questo account non può completare ordini. Accedi con un\'utenza cliente.';
    }

    private function normalizeIntegerList($values): array
    {
        if (! is_array($values)) {
            return [];
        }
        $normalized = [];
        foreach ($values as $value) {
            $intValue = $this->normalizePositiveInt($value);
            if ($intValue !== null) {
                $normalized[] = $intValue;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeCartArticles($articles): array
    {
        if (! is_array($articles)) {
            return [];
        }
        $normalized = [];
        foreach ($articles as $article) {
            if (! is_array($article) || count($article) < 2) {
                continue;
            }
            $variantId = $this->normalizePositiveInt($article[0] ?? null);
            $quantity = $this->normalizePositiveInt($article[1] ?? null);
            if ($variantId === null || $quantity === null) {
                continue;
            }
            $normalized[] = [$variantId, $quantity];
        }

        return $normalized;
    }

    private function normalizePositiveInt($value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || ! preg_match('/^\d+$/', $trimmed)) {
                return null;
            }
            $normalized = (int) $trimmed;

            return $normalized > 0 ? $normalized : null;
        }
        if (is_float($value) && floor($value) == $value) {
            return $value > 0 ? (int) $value : null;
        }

        return null;
    }

    private function normalizeCheckboxLike($value): int
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return 1;
        }

        return 0;
    }
}
