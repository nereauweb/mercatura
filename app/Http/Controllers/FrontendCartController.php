<?php

namespace App\Http\Controllers;

use App\Actions\Orders\StoreOrderItemCustomizations;
use App\Contracts\NewsletterProvider;
use App\Contracts\TransactionalMailer;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\User;
use App\Rules\Captcha;
use App\Support\CaughtExceptionLogger;
use App\Support\CustomerFormRules;
use App\Support\Customizations\LinePricer;
use App\Support\Customizations\Pricing;
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
        // The configurator posts `printings`; the session keeps `customizations` (docs/03 decision 7).
        $decodedCartItem['customizations'] = $this->normalizeIntegerList($decodedCartItem['customizations'] ?? $decodedCartItem['printings'] ?? []);
        unset($decodedCartItem['printings']);
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
            app(StoreOrderItemCustomizations::class)->handle($order_item, $cart_item['line']);
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
            'delivery_cost' => Pricing::deliveryCost(0.0),
            'total_price' => 0,
            'total_taxed_price' => 0,
            'total_additional_costs' => 0,
            'delivery_days' => 4,
            'delivery_date' => Carbon::now()->addDays(4),
        ];
        $pricer = app(LinePricer::class);
        foreach ($session_cart as $session_cart_item_id => $sci) {
            $line = $pricer->price(
                array_map(fn ($article): array => [intval($article[0]), intval($article[1])], (array) ($sci['articles'] ?? [])),
                array_map('intval', (array) ($sci['customizations'] ?? $sci['printings'] ?? [])),
                (bool) ($sci['has_packaging'] ?? false),
            );
            $cart_item = [
                'id' => $session_cart_item_id,
                'price' => $line->price,
                'quantity' => $line->quantity,
                'additional_costs' => $line->additionalCosts,
                'has_packaging' => $sci['has_packaging'] ?? false,
                'product' => $line->product,
                'articles' => array_map(fn ($article): array => [
                    'article' => $article->variant,
                    'quantity' => $article->quantity,
                    'unit_price' => $article->unitPrice,
                    'quantity_price' => $article->price,
                    'additional_costs' => $article->additionalCosts,
                ], $line->articles),
                'printings' => array_map(fn ($customization) => $customization->option, $line->customizations),
                'unit_price' => $line->unitPrice(),
                'line' => $line,
            ];
            $delivery_days = $line->product->processing_days() + $line->processingDays;
            if ($cart['delivery_days'] < $delivery_days) {
                $cart['delivery_days'] = $delivery_days;
            }
            $cart['items'][] = $cart_item;
            $cart['items_price'] += $line->price;
            $cart['total_additional_costs'] += $line->additionalCosts;
            FrontendDebugLog::prezzoCarrello('Riga '.$session_cart_item_id.': prezzo '.$line->price.' | quantità '.$line->quantity.' | costi addizionali '.$line->additionalCosts);
        }
        $cart['delivery_cost'] = Pricing::deliveryCost((float) $cart['items_price']);
        $cart['delivery_date'] = Carbon::now()->addDays($cart['delivery_days']);
        $cart['total_price'] = round($cart['items_price'] + $cart['delivery_cost'], 2);
        $cart['tax'] = Pricing::vat((float) $cart['total_price']);
        $cart['total_taxed_price'] = $cart['total_price'] + $cart['tax'] + $cart['total_additional_costs'];
        FrontendDebugLog::prezzoCarrello('Totali carrello: spedizione '.$cart['delivery_cost'].' | imponibile '.$cart['total_price'].' | IVA '.$cart['tax'].' | costi addizionali '.$cart['total_additional_costs'].' | totale '.$cart['total_taxed_price'].' | giorni '.$cart['delivery_days']);

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
