<?php

namespace App\Http\Controllers;

use App\Contracts\NewsletterProvider;
use App\Contracts\TransactionalMailer;
use App\Models\Customer;
use App\Models\Message;
use App\Rules\Captcha;
use App\Support\CaughtExceptionLogger;
use App\Support\FrontendDebugLog;
use Illuminate\Http\Request;

class FrontendContactsController extends Controller
{
    public function index(Request $request)
    {
        $customer = new Customer;

        return view('frontend.pages.contact', compact('customer'));
    }

    public function send(Request $request)
    {
        FrontendDebugLog::contatto('contact_send:start');

        // Honeypot: se il campo nascosto è compilato è un bot -> risposta generica.
        if (filled($request->input('website'))) {
            FrontendDebugLog::contatto('contact_send:honeypot_triggered');

            return view('frontend.pages.sent');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50|regex:/^[+]?[0-9][0-9\s\-()]{6,22}$/',
            'customer_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Models\Customer::CUSTOMER_TYPES)],
            'company' => 'nullable|string|max:255',
            'activity' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'consent_gdpr' => ['accepted'],
            'subscribe_newsletter' => 'nullable|boolean',
            ...Captcha::rules('contact'),
        ]);
        FrontendDebugLog::contatto('contact_send:validated');
        FrontendDebugLog::contatto('contact_send:consent_flags', [
            'consent_gdpr' => (bool) $request->boolean('consent_gdpr'),
            'consent_terms' => $request->has('consent_terms') ? (bool) $request->boolean('consent_terms') : null,
            'subscribe_newsletter' => (bool) $request->boolean('subscribe_newsletter'),
        ]);

        $mailer = app(TransactionalMailer::class);

        // user notification
        $mailer->attribute('company', $request->company);
        $mailer->attribute('name', $request->name);
        $mailer->attribute('surname', $request->surname);
        $mailer->attribute('email', $request->email);
        $mailer->attribute('phone', $request->phone);
        $mailer->attribute('activity', $request->activity);
        $mailer->attribute('subject', $request->subject);
        $mailer->attribute('message', $request->message);
        $mailer->attribute('consent_gdpr', $request->consent_gdpr == 1 ? 'Sì' : 'No');
        $mailer->attribute('subscribe_newsletter', $request->subscribe_newsletter == 1 ? 'Sì' : 'No');
        // links
        $mailer->attribute('contacts_link', route('frontend.contacts.index'));
        $mailer->attribute('gdpr_link', url('/').'/contenuti/privacy-policy');
        //
        $mailer->to($request->email);
        // $mailer->send('contact_customer'); // template 14 unused
        $mailer->reset();
        // admin notification
        $mailer->attribute('company', $request->company);
        $mailer->attribute('name', $request->name);
        $mailer->attribute('surname', $request->surname);
        $mailer->attribute('email', $request->email);
        $mailer->attribute('phone', $request->phone);
        $mailer->attribute('activity', $request->activity);
        $mailer->attribute('subject', $request->subject);
        $mailer->attribute('message', $request->message);
        $mailer->attribute('consent_gdpr', $request->consent_gdpr == 1 ? 'Sì' : 'No');
        $mailer->attribute('subscribe_newsletter', $request->subscribe_newsletter == 1 ? 'Sì' : 'No');
        // links
        $mailer->attribute('contacts_link', route('frontend.contacts.index'));
        $mailer->attribute('gdpr_link', url('/').'/contenuti/privacy-policy');

        $mailer->to(config('emails.technical'));
        $mailer->to(config('emails.merchant'));
        $mailer->send('contact_admin');
        // The message is kept for the admin "Messaggi" list (read/unread) and the customer's own list.
        Message::create([
            'name' => $request->name,
            'surname' => $request->surname,
            'email' => $request->email,
            'phone' => $request->phone,
            'company' => $request->company,
            'activity' => $request->activity,
            'subject' => $request->subject,
            'message' => $request->message,
            'consent_gdpr' => $request->boolean('consent_gdpr'),
            'consent_terms' => $request->boolean('consent_terms'),
            'subscribe_newsletter' => $request->boolean('subscribe_newsletter'),
        ]);
        FrontendDebugLog::contatto('contact_send:completed');

        if ($request->subscribe_newsletter == 1) {
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
            } catch (\Exception $e) {
                CaughtExceptionLogger::error('FrontendContactsController::send newsletter subscribe failed', $e, [
                    'email' => $request->email,
                    'provider' => config('mercatura.providers.newsletter'),
                ]);
            }
        }

        return view('frontend.pages.sent');
    }

    public function list(Request $request)
    {
        return view('frontend.customer.contacts');
    }

    public function show(Request $request, $id)
    {
        $message = Message::find($id);

        return view('frontend.customer.message', compact('message'));
    }

    public function newsletter_subscribe_form()
    {
        return view('frontend.pages.newsletter');
    }

    public function newsletter_subscribe(Request $request)
    {
        FrontendDebugLog::newsletter('newsletter_subscribe:start', [
            'route' => 'frontend.newsletter.register',
            'consent_gdpr' => $request->has('consent_gdpr') ? $request->boolean('consent_gdpr') : null,
            'consent_terms' => $request->has('consent_terms') ? $request->boolean('consent_terms') : null,
        ]);

        if (filled($request->input('website'))) {
            FrontendDebugLog::newsletter('newsletter_subscribe:honeypot_triggered');

            return view('frontend.pages.newsletter_success');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50|regex:/^[+]?[0-9][0-9\s\-()]{6,22}$/',
            'customer_type' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Models\Customer::CUSTOMER_TYPES)],
            'company' => 'nullable|string|max:255',
            'activity' => 'nullable|string|max:255',
            'consent_gdpr' => ['accepted'],
            ...Captcha::rules('subscribe_nl'),
        ]);
        FrontendDebugLog::newsletter('newsletter_subscribe:validated', [
            'consent_gdpr' => (bool) $request->boolean('consent_gdpr'),
            'consent_terms' => $request->has('consent_terms') ? (bool) $request->boolean('consent_terms') : null,
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
            FrontendDebugLog::newsletter('newsletter_subscribe:brevo_ok', [
                'provider' => config('mercatura.providers.newsletter'),
            ]);
        } catch (\Exception $e) {
            CaughtExceptionLogger::error('FrontendContactsController::newsletter_subscribe newsletter subscribe failed', $e, [
                'email' => $request->email,
                'provider' => config('mercatura.providers.newsletter'),
            ]);
            FrontendDebugLog::newsletter('newsletter_subscribe:provider_error', [
                'error' => $e->getMessage(),
                'provider' => config('mercatura.providers.newsletter'),
            ]);

            return back()->withErrors([
                'Brevo' => 'Utente già iscritto alla newsletter',
            ])->withInput();
        }

        return view('frontend.pages.newsletter_success');
    }

    public function newsletter_subscribe_success()
    {
        return view('frontend.newsletter.registered');
    }
}
