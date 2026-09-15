<?php

namespace App\Http\Controllers;

use App\Contracts\NewsletterProvider;
use App\Contracts\TransactionalMailer;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Rules\Captcha;
use App\Support\CaughtExceptionLogger;
use App\Support\CustomerFormRules;
use App\Support\FrontendDebugLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class FrontendLoginController extends Controller
{
    public function index()
    {
        if (Auth::check()) {
            return redirect()->route('frontend.auth.index');
        }

        return view('frontend.auth.login');
    }

    public function attempt_login(Request $request): RedirectResponse
    {
        FrontendDebugLog::autenticazione('login:start', [
            'route' => 'frontend.login.attempt',
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            ...Captcha::rules('login'),
        ]);
        unset($credentials[Captcha::field()]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $user = Auth::user();
            FrontendDebugLog::autenticazione('login:success', [
                'user_id' => $user->id,
                'is_admin' => $user->hasRole('admin'),
            ]);
            if ($user->hasRole('admin')) {
                return redirect()->route('filament.admin.pages.dashboard');
            }

            return redirect()->intended(route('frontend.auth.reserved_area'));
        }

        FrontendDebugLog::autenticazione('login:invalid_credentials');

        return back()->withErrors([
            'auth' => 'Login fallito: credenziali errate',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function request_reset_password(Request $request)
    {
        FrontendDebugLog::autenticazione('password_reset_request:start');
        $request->validate([
            'email' => ['required', 'email'],
            ...Captcha::rules('reset_password'),
        ]);
        FrontendDebugLog::autenticazione('password_reset_request:validated');
        $status = Password::sendResetLink(
            $request->only('email')
        );
        FrontendDebugLog::autenticazione('password_reset_request:send_link_result', [
            'sent' => $status === Password::RESET_LINK_SENT,
        ]);

        return $status === Password::RESET_LINK_SENT
                    ? back()->with(['status' => __($status)])
                    : back()->withErrors(['email' => __($status)]);
    }

    public function reset_password(Request $request)
    {
        FrontendDebugLog::autenticazione('password_reset_submit:start');
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
            ...Captcha::rules('reset_password'),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        FrontendDebugLog::autenticazione('password_reset_submit:result', [
            'reset_ok' => $status === Password::PASSWORD_RESET,
        ]);

        return $status === Password::PASSWORD_RESET
                    ? redirect()->route('frontend.auth.login')->with('status', __($status))
                    : back()->withErrors(['email' => [__($status)]]);
    }

    public function register_form()
    {
        $province_list = Customer::PROVINCES;

        return view('frontend.auth.register', compact('province_list'));
    }

    public function register(Request $request)
    {
        FrontendDebugLog::registrazioneCliente('register:start', [
            'route' => 'frontend.auth.register.submit',
        ]);

        $validated = $request->validate(array_merge(
            CustomerFormRules::registerAuthRules($request),
            Captcha::rules('register'),
        ));
        FrontendDebugLog::registrazioneCliente('register:consents_validated', [
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
            FrontendDebugLog::registrazioneCliente('register:user_create_failed');

            return back()->withErrors(['msg' => 'Errore nella creazione utente'])->withInput();
        }
        FrontendDebugLog::registrazioneCliente('register:user_created', [
            'user_id' => $user->id,
        ]);
        $user->assignRole('customer');
        $billing_address = CustomerAddress::create([
            'address' => $request->bill_address ?? null,
            'province' => $request->bill_province ?? null,
            'city' => $request->bill_city ?? null,
            'zip_code' => $request->bill_zip_code ?? null,
            'country' => $request->bill_country ?? null,
            'notes' => $request->bill_notes ?? null,
        ]);
        $shipping_address = CustomerAddress::create([
            'address' => $request->shipping_address ?? null,
            'province' => $request->shipping_province ?? null,
            'city' => $request->shipping_city ?? null,
            'zip_code' => $request->shipping_zip_code ?? null,
            'country' => $request->shipping_country ?? null,
            'notes' => $request->shipping_notes ?? null,
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
        FrontendDebugLog::registrazioneCliente('register:customer_created', [
            'user_id' => $user->id,
            'customer_id' => $customer?->id,
            'newsletter_requested' => (int) ($request->subscribe_newsletter == 1),
        ]);

        if ($request->subscribe_newsletter == 1) {
            FrontendDebugLog::newsletter('register:subscribe_newsletter:start', [
                'source' => 'frontend_register',
                'user_id' => $user->id,
            ]);
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
                FrontendDebugLog::newsletter('register:subscribe_newsletter:brevo_ok', [
                    'source' => 'frontend_register',
                    'user_id' => $user->id,
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
            } catch (\Exception $e) {
                CaughtExceptionLogger::error('FrontendLoginController::register newsletter subscribe failed', $e, [
                    'user_id' => $user->id,
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
                FrontendDebugLog::newsletter('register:subscribe_newsletter:provider_error', [
                    'source' => 'frontend_register',
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
            }
        }

        Auth::login($user);

        $mailer = app(TransactionalMailer::class);
        $mailer->attribute('name', $user->name);
        $mailer->to($user->email);
        $mailer->send('welcome');
        $mailer->reset();

        $mailer->attribute('name', $request->name);
        $mailer->attribute('surname', $request->surname);
        $mailer->attribute('company', $request->company);
        $mailer->attribute('email', $request->email);
        $mailer->attribute('phone', $request->phone);
        $mailer->attribute('activity', $request->activity);
        $mailer->attribute('customer_type', $request->customer_type);
        $mailer->attribute('gdpr', $request->consent_gdpr == 1 ? 'Sì' : 'No');
        $mailer->attribute('terms', $request->consent_terms == 1 ? 'Sì' : 'No');
        $mailer->attribute('newsletter', $request->subscribe_newsletter == 1 ? 'Sì' : 'No');
        $mailer->to(config('emails.merchant'));
        $mailer->send('register_admin');
        FrontendDebugLog::registrazioneCliente('register:completed', [
            'user_id' => $user->id,
        ]);

        return redirect()->route('frontend.auth.reserved_area')
            ->with('success', __('frontend.auth.register_success', ['brand' => config('brand.name')]));
    }
}
