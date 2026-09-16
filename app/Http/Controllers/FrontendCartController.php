<?php

namespace App\Http\Controllers;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Checkout\RegisterCustomer;
use App\Actions\Checkout\SaveCustomerProfile;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Rules\Captcha;
use App\Support\ArtworkFiles;
use App\Support\Checkout\CartData;
use App\Support\Checkout\CheckoutGuard;
use App\Support\CustomerFormRules;
use App\Support\FrontendDebugLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
        // A sample is one plain piece (docs/04 §4.6); artwork maps an option id to a token returned by uploadArtwork().
        $decodedCartItem['sample'] = config('mercatura.storefront.samples') ? $this->normalizeCheckboxLike($decodedCartItem['sample'] ?? 0) : 0;
        $decodedCartItem['artwork'] = $this->normalizeArtwork($decodedCartItem['artwork'] ?? [], $request);

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
        if (config('mercatura.storefront.checkout') === 'onepage') {
            FrontendDebugLog::carrelloPagamento('checkout:redirect_onepage');

            return redirect()->route('frontend.checkout.onepage');
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
        $user = app(RegisterCustomer::class)->handle($validated);

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

        $customer = Auth::user()?->customer;
        if ($customer === null) {
            return redirect()->route('frontend.cart.index')->withErrors(['checkout' => CheckoutGuard::blockMessage('customer_missing')]);
        }
        app(SaveCustomerProfile::class)->handle($customer, $validated);

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
        $placer = app(PlaceOrder::class);
        $order = $placer->handle($user, $cart, (string) $request->payment_method, CartData::artworkFiles($this->cartArtwork($cart), $request->session()));
        try {
            $redirectUrl = $placer->complete($order, $user, $request->session());
        } catch (\RuntimeException $e) {
            return back()->withErrors([
                'checkout' => 'Impossibile processare la richiesta',
            ]);
        }
        if ($redirectUrl !== null) {
            return redirect($redirectUrl);
        }

        return view('frontend.pages.checkout.finalized', compact('order'));
    }

    /** @param  array<string, mixed>  $cart  @return array<int, string> option id => token, all lines merged */
    private function cartArtwork(array $cart): array
    {
        $artwork = [];
        foreach ($cart['items'] as $item) {
            foreach ((array) $item['artwork'] as $optionId => $token) {
                $artwork[(int) $optionId] = $token;
            }
        }

        return $artwork;
    }

    private function session_data_to_cart($session_cart)
    {
        return CartData::build((array) $session_cart);
    }

    /** The onepage checkout (docs/04_STOREFRONT_FLOWS.md §4.7): one Livewire component, mounted here. */
    public function onepage(Request $request)
    {
        if (config('mercatura.storefront.checkout') !== 'onepage') {
            return redirect()->route('frontend.checkout.account');
        }
        if (empty(CartData::fromSession($request->session()))) {
            return redirect()->route('frontend.cart.index')->withErrors(['checkout' => 'Carrello vuoto']);
        }
        if (Auth::check() && ($blockCode = $this->checkoutBlockCode(Auth::user())) !== null) {
            return redirect()->route('frontend.cart.index')->withErrors(['checkout' => $this->checkoutBlockMessage($blockCode)]);
        }

        return view('frontend.pages.checkout.onepage');
    }

    /** Bank transfer outcome of the onepage checkout: the customer's latest order, with the bank details. */
    public function finalized(Request $request)
    {
        $order = Order::query()->where('user_id', Auth::id())->where('payment_method', 'bank_transfer')->latest('id')->first();
        if ($order === null) {
            return redirect()->route('frontend.home');
        }

        return view('frontend.pages.checkout.finalized', compact('order'));
    }

    public function clear_cart(Request $request)
    {
        $request->session()->put('cart', []);
        FrontendDebugLog::carrelloPagamento('clear_cart');

        return redirect()->route('frontend.cart.index')->with('success', 'Carrello aggiornato');
    }

    /**
     * Artwork uploaded from the configurator (docs/04 §4.2): kept per session until the order is stored.
     * Returns the token the cart line refers to.
     */
    public function uploadArtwork(Request $request)
    {
        if (! config('mercatura.storefront.artwork_in_configurator')) {
            abort(404);
        }
        $request->validate(['file' => ArtworkFiles::rules(), 'option_id' => ['required', 'integer']]);
        $path = ArtworkFiles::store($request->file('file'), 'local', self::artworkDirectory($request), 'artwork_'.$request->integer('option_id'));
        if ($path === null) {
            return response()->json(['message' => __('frontend.cart.artwork_invalid')], 422);
        }

        return response()->json(['token' => basename($path), 'name' => $request->file('file')->getClientOriginalName()]);
    }

    private static function artworkDirectory(Request $request): string
    {
        return CartData::artworkDirectory($request->session());
    }

    /** @return array<int, string> option id => token, only tokens that exist in this session's directory */
    private function normalizeArtwork($values, Request $request): array
    {
        if (! is_array($values) || ! config('mercatura.storefront.artwork_in_configurator')) {
            return [];
        }
        $normalized = [];
        foreach ($values as $optionId => $token) {
            $optionId = $this->normalizePositiveInt($optionId);
            $token = is_string($token) ? basename($token) : null;
            if ($optionId !== null && $token && Storage::disk('local')->exists(self::artworkDirectory($request).'/'.$token)) {
                $normalized[$optionId] = $token;
            }
        }

        return $normalized;
    }

    private function checkoutBlockCode(User $user): ?string
    {
        return CheckoutGuard::blockCode($user);
    }

    private function checkoutBlockMessage(string $blockCode): string
    {
        return CheckoutGuard::blockMessage($blockCode);
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
