<?php

namespace App\Models\ImportData;

use Illuminate\Database\Eloquent\Model;

class NormalizedRulesPriceTiers extends Model
{
    protected $table = 'normalized_rules_price_tiers';

    protected $fillable = [
        'from_price',
        'to_price',
        'from_quantity_1',
        'from_quantity_2',
        'from_quantity_3',
        'from_quantity_4',
    ];
}
