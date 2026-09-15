<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    protected $casts = ['read_at' => 'datetime', 'deleted_at' => 'datetime'];

    protected $table = 'quotations';

    protected $fillable = [
        'customer_email',
        'customer_type',
        'customer_company',
        'customer_activity',
        'customer_name',
        'customer_surname',
        'customer_phone',
        'consent_gdpr',
        'subscribe_newsletter',
        'read_at',
    ];

    public function items(): HasMany
    {
        return $this->hasMany('App\Models\QuotationItem', 'quotation_id');
    }
}
