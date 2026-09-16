<?php

declare(strict_types=1);

namespace App\Http\Livewire;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Checkout\RegisterCustomer;
use App\Actions\Checkout\SaveCustomerProfile;
use App\Models\Customer;
use App\Models\User;
use App\Rules\Captcha;
use App\Support\Checkout\CartData;
use App\Support\Checkout\CheckoutGuard;
use App\Support\CustomerFormRules;
use App\Support\FrontendDebugLog;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Onepage checkout (docs/04_STOREFRONT_FLOWS.md §4.7): six steps in one
 * component, one request per step, the same actions as the steps flow
 * (RegisterCustomer, SaveCustomerProfile, PlaceOrder). Forms post their
 * fields as an array (FormData from the view), so the customer-fields
 * component is reused unchanged and the captcha token travels with them.
 */
final class FrontendCheckoutOnepage extends Component
{
    public const STEPS = ['method', 'billing', 'shipping', 'shipping_method', 'payment', 'review'];

    public const SHIPPING_FIELDS = ['shipping_address', 'shipping_city', 'shipping_province', 'shipping_zip_code', 'shipping_country', 'shipping_notes'];

    public int $step = 1;

    /** Highest step the customer has reached; earlier ones can be reopened. */
    public int $reached = 1;

    public string $mode = 'login';

    /** @var array<string, mixed> */
    public array $billing = [];

    /** @var array<string, mixed> */
    public array $shipping = [];

    public bool $shipToBilling = true;

    public string $shippingMethod = 'courier';

    public string $paymentMethod = '';

    public function mount(): void
    {
        $user = Auth::user();
        if ($user instanceof User && CheckoutGuard::blockCode($user) === null) {
            $this->prefill($user->customer);
            $this->step = $this->reached = 2;
        }
    }

    /** @param  array<string, mixed>  $data */
    public function login(array $data): void
    {
        $credentials = Validator::make($data, [
            'email' => ['required', 'email'],
            'password' => ['required'],
            ...Captcha::rules('login'),
        ])->validate();
        unset($credentials[Captcha::field()]);
        if (! Auth::attempt($credentials)) {
            FrontendDebugLog::carrelloPagamento('onepage_login:invalid_credentials');
            throw ValidationException::withMessages(['auth' => 'Login fallito: credenziali errate']);
        }
        $this->afterAuthentication();
    }

    /** @param  array<string, mixed>  $data */
    public function register(array $data): void
    {
        $request = Request::create('/', 'POST', $data);
        $validated = Validator::make($data, array_merge(CustomerFormRules::registerAuthRules($request), Captcha::rules('register')))->validate();
        app(RegisterCustomer::class)->handle($validated);
        $this->afterAuthentication();
    }

    /** @param  array<string, mixed>  $data */
    public function saveBilling(array $data): void
    {
        $customer = $this->customer();
        $data = array_intersect_key($data, array_flip(array_merge(array_keys(CustomerFormRules::fiscalAndBillingRules(Request::create('/'))), ['email', 'ship_to_billing'])));
        $this->shipToBilling = filter_var($data['ship_to_billing'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['ship_to_billing']);
        $data = array_diff_key($data, array_flip(self::SHIPPING_FIELDS));
        $request = Request::create('/', 'POST', $data);
        $validated = Validator::make($data, array_merge(
            array_diff_key(CustomerFormRules::fiscalAndBillingRules($request), array_flip(self::SHIPPING_FIELDS)),
            CustomerFormRules::profileEmailRules((int) $customer->user_id),
        ))->validate();
        $this->billing = $validated;
        if ($this->shipToBilling) {
            $validated += [
                'shipping_address' => $validated['bill_address'],
                'shipping_city' => $validated['bill_city'],
                'shipping_province' => $validated['bill_province'],
                'shipping_zip_code' => $validated['bill_zip_code'],
                'shipping_country' => $validated['bill_country'],
                'shipping_notes' => $validated['bill_notes'] ?? null,
            ];
            $this->shipping = array_intersect_key($validated, array_flip(self::SHIPPING_FIELDS));
        }
        app(SaveCustomerProfile::class)->handle($customer, $validated);
        $this->advance($this->shipToBilling ? 4 : 3);
    }

    /** @param  array<string, mixed>  $data */
    public function saveShipping(array $data): void
    {
        $customer = $this->customer();
        $data = array_intersect_key($data, array_flip(self::SHIPPING_FIELDS));
        $validated = Validator::make($data, [
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:100'],
            'shipping_province' => ['required', 'string', Rule::in(Customer::PROVINCES)],
            'shipping_zip_code' => ['required', 'string', 'max:20'],
            'shipping_country' => ['required', 'string', 'max:100'],
            'shipping_notes' => ['nullable', 'string', 'max:500'],
        ])->validate();
        $this->shipping = $validated;
        app(SaveCustomerProfile::class)->handle($customer, $validated);
        $this->advance(4);
    }

    public function saveShippingMethod(): void
    {
        $this->shippingMethod = 'courier';
        $this->advance(5);
    }

    public function savePayment(): void
    {
        $this->validate(['paymentMethod' => ['required', Rule::in(config('mercatura.checkout.payment_methods', []))]]);
        $this->advance(6);
    }

    /** @param  array<string, mixed>  $data */
    public function placeOrder(array $data): mixed
    {
        $user = Auth::user();
        if (! $user instanceof User || ($blockCode = CheckoutGuard::blockCode($user)) !== null) {
            throw ValidationException::withMessages(['checkout' => CheckoutGuard::blockMessage($blockCode ?? 'role_not_customer')]);
        }
        Validator::make($data, [
            'consent_gdpr' => ['accepted'],
            'consent_terms' => ['accepted'],
        ])->validate();
        $this->validate(['paymentMethod' => ['required', Rule::in(config('mercatura.checkout.payment_methods', []))]]);
        $sessionCart = CartData::fromSession($this->session());
        if ($sessionCart === []) {
            return $this->redirectRoute('frontend.cart.index');
        }
        $cart = CartData::build($sessionCart);
        $artwork = [];
        foreach ($cart['items'] as $item) {
            foreach ((array) $item['artwork'] as $optionId => $token) {
                $artwork[(int) $optionId] = $token;
            }
        }
        $placer = app(PlaceOrder::class);
        $order = $placer->handle($user, $cart, $this->paymentMethod, CartData::artworkFiles($artwork, $this->session()));
        try {
            $url = $placer->complete($order, $user, $this->session());
        } catch (\RuntimeException) {
            throw ValidationException::withMessages(['checkout' => 'Impossibile processare la richiesta']);
        }

        return $url !== null ? $this->redirect($url) : $this->redirectRoute('frontend.checkout.finalized_page');
    }

    public function goTo(int $step): void
    {
        if ($step >= 1 && $step <= $this->reached && ($step !== 1 || ! Auth::check())) {
            $this->step = $step;
        }
    }

    public function render(): View
    {
        $sessionCart = CartData::fromSession($this->session());

        return view('livewire.frontend-checkout-onepage', [
            'cart' => $sessionCart === [] ? null : CartData::build($sessionCart),
            'customer' => Auth::check() ? Auth::user()?->customer : null,
            'provinces' => Customer::PROVINCES,
            'paymentMethods' => config('mercatura.checkout.payment_methods', []),
        ]);
    }

    private function afterAuthentication(): void
    {
        $user = Auth::user();
        if (! $user instanceof User || ($blockCode = CheckoutGuard::blockCode($user)) !== null) {
            Auth::logout();
            throw ValidationException::withMessages(['auth' => CheckoutGuard::blockMessage($blockCode ?? 'role_not_customer')]);
        }
        $this->prefill($user->customer);
        $this->advance(2);
    }

    private function prefill(?Customer $customer): void
    {
        if ($customer === null) {
            return;
        }
        $this->billing = array_merge(
            $customer->only(['name', 'surname', 'email', 'phone', 'customer_type', 'activity', 'company', 'tax_code', 'vat_code', 'pec', 'sdi_code', 'ipa_code', 'cig_code']),
            ['bill_'.'address' => $customer->billing_address?->address, 'bill_city' => $customer->billing_address?->city, 'bill_province' => $customer->billing_address?->province, 'bill_zip_code' => $customer->billing_address?->zip_code, 'bill_country' => $customer->billing_address?->country, 'bill_notes' => $customer->billing_address?->notes],
        );
        $this->shipping = ['shipping_address' => $customer->shipping_address?->address, 'shipping_city' => $customer->shipping_address?->city, 'shipping_province' => $customer->shipping_address?->province, 'shipping_zip_code' => $customer->shipping_address?->zip_code, 'shipping_country' => $customer->shipping_address?->country, 'shipping_notes' => $customer->shipping_address?->notes];
    }

    private function customer(): Customer
    {
        $user = Auth::user();
        if (! $user instanceof User || ($blockCode = CheckoutGuard::blockCode($user)) !== null || $user->customer === null) {
            throw ValidationException::withMessages(['checkout' => CheckoutGuard::blockMessage($blockCode ?? 'customer_missing')]);
        }

        return $user->customer;
    }

    /** The session store, also outside an HTTP request (component tests). */
    private function session(): Session
    {
        return app(Session::class);
    }

    private function advance(int $step): void
    {
        $this->step = $step;
        $this->reached = max($this->reached, $step);
    }
}
