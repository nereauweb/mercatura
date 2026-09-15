<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Rules\Captcha;
use App\Support\CustomerFormRules;
use App\Support\FrontendDebugLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FrontendAuthController extends Controller
{
    public function index()
    {
        return view('frontend.customer.index');
    }

    public function profile()
    {
        if (Auth::user()->hasRole('admin')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }
        $user = Auth::user();
        if (! $user->customer) {
            $user->customer()->create([]);
        }
        $customer = $user->customer;
        if (! $user->customer->billing_address) {
            $billing_address = CustomerAddress::create([]);
            $customer->billing_address_id = $billing_address->id;
        }
        if (! $user->customer->shipping_address) {
            $shipping_address = CustomerAddress::create([]);
            $customer->shipping_address_id = $shipping_address->id;
        }
        $customer->save();
        $province_list = Customer::PROVINCES;

        return view('frontend.customer.profile', compact('province_list'));
    }

    public function user()
    {
        if (Auth::user()->hasRole('admin')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        return back();
    }

    public function update_user(Request $request)
    {
        if (Auth::user()->hasRole('admin')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }
        $user = Auth::user();
        FrontendDebugLog::profiloCliente('update_user:start', [
            'user_id' => $user->id,
            'consent_gdpr' => $request->has('consent_gdpr') ? $request->boolean('consent_gdpr') : null,
            'consent_terms' => $request->has('consent_terms') ? $request->boolean('consent_terms') : null,
        ]);
        try {
            $validated = $request->validate([
                'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['nullable', 'string', 'min:8', 'confirmed'],
                ...Captcha::rules('user_autoupdate'),
            ]);
        } catch (ValidationException $e) {
            FrontendDebugLog::profiloCliente('update_user:validation_failed', [
                'user_id' => $user->id,
                'error_fields' => array_keys($e->errors()),
            ]);
            throw $e;
        }
        FrontendDebugLog::profiloCliente('update_user:recaptcha_ok', [
            'user_id' => $user->id,
        ]);
        $user->name = $request->name;
        $user->email = $request->email;
        if (filled($request->password)) {
            $user->password = Hash::make($request->password);
        }
        $user->save();
        FrontendDebugLog::profiloCliente('update_user:completed', [
            'user_id' => $user->id,
        ]);

        return back()->with('success', 'Profilo utente aggiornato.');
    }

    public function update_customer(Request $request)
    {
        if (Auth::user()->hasRole('admin')) {
            return redirect()->route('filament.admin.pages.dashboard');
        }
        $customer = Auth::user()->customer;
        FrontendDebugLog::profiloCliente('update_customer:start', [
            'user_id' => Auth::id(),
            'customer_id' => $customer?->id,
            'consent_gdpr' => $request->has('consent_gdpr') ? $request->boolean('consent_gdpr') : null,
            'consent_terms' => $request->has('consent_terms') ? $request->boolean('consent_terms') : null,
        ]);
        try {
            $validated = $request->validate(array_merge(
                [
                    ...Captcha::rules('customer_autoupdate'),
                ],
                CustomerFormRules::fiscalAndBillingRules($request),
                CustomerFormRules::profileEmailRules(Auth::id()),
            ));
        } catch (ValidationException $e) {
            FrontendDebugLog::profiloCliente('update_customer:validation_failed', [
                'user_id' => Auth::id(),
                'customer_id' => $customer?->id,
                'error_fields' => array_keys($e->errors()),
            ]);
            throw $e;
        }
        FrontendDebugLog::profiloCliente('update_customer:recaptcha_ok', [
            'user_id' => Auth::id(),
            'customer_id' => $customer?->id,
        ]);
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
        FrontendDebugLog::profiloCliente('update_customer:completed', [
            'user_id' => Auth::id(),
            'customer_id' => $customer?->id,
        ]);

        return back()->with('success', 'Profilo cliente aggiornato');
    }
}
