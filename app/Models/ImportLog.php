<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    protected $table = 'import_logs';

    protected $fillable = [
        'import_id',
        'type', // info,success,warn,error
        'context',
        'event',
        'message',
        'ref_type',
        'ref',
        'source_ref',
    ];

    public function reference()
    {
        switch ($this->ref_type) {
            case 'product':
                $product = Product::find($this->ref);
                if ($product) {
                    return ['ref_type' => $this->ref_type, 'entity' => 'object', 'content' => $product];
                }
            case 'variant':
                $variant = ProductVariant::find($this->ref);
                if ($variant) {
                    return ['ref_type' => $this->ref_type, 'entity' => 'object', 'content' => $variant];
                }
            case 'normalized_product':
                $product = \App\Models\ImportData\NormalizedProduct::find($this->ref);
                if ($product) {
                    return ['ref_type' => $this->ref_type, 'entity' => 'object', 'content' => $product];
                }
            case 'normalized_variant':
                $variant = \App\Models\ImportData\NormalizedProductVariant::find($this->ref);
                if ($variant) {
                    return ['ref_type' => $this->ref_type, 'entity' => 'object', 'content' => $variant];
                }
            case 'image_url':
                return ['ref_type' => $this->ref_type, 'entity' => 'url', 'content' => $this->ref];
        }

        return false;
    }
}
