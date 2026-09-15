<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;

class NormalizedRulesPricingProducts extends Model
{
    protected $table = 'normalized_rules_pricing_products';

    protected $fillable = [
        'condition_type',
        'condition_1',
        'condition_2',
        'condition_3',
        'delta_type',
        'value',
    ];
}
