<?php

namespace App\Models;

use App\Mail\NotifyQuotationRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

class Message extends Model
{
    protected $table = 'messages';

    protected $fillable = [
        'email',
        'phone',
        'name',
        'surname',
        'company',
        'activity',
        'subject',
        'message',
        'consent_gdpr',
        'subscribe_newsletter',
        'consent_terms',
        'read_at',
    ];

    protected $casts = ['read_at' => 'datetime', 'consent_gdpr' => 'boolean', 'consent_terms' => 'boolean', 'subscribe_newsletter' => 'boolean'];

    public function notify_admin()
    {
        Mail::to(config('emails.merchant'))
            ->cc(config('emails.technical'))
            ->send(new NotifyQuotationRequest($this));
    }
}
