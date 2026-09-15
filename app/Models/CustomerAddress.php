<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $table = 'customer_addresses';

    protected $fillable = [
        'address',
        'province',
        'city',
        'zip_code',
        'country',
        'notes',
    ];

    public static $view_fields = [
        'Indirizzo' => 'address',
        'Provincia' => 'province',
        'Città' => 'city',
        'CAP' => 'zip_code',
        'Paese' => 'country',
        'Note' => 'notes',
    ];
}
