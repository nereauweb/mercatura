<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductColor extends Model
{
    use SoftDeletes;

    protected $table = 'product_colors';

    protected $fillable = [
        'label',
        'code',
    ];

    /**
     * The families the colour belongs to: one for a plain colour, several for a
     * composite one ("blu/bianco").
     *
     * @return BelongsToMany<ProductColorFamily, $this>
     */
    public function families(): BelongsToMany
    {
        return $this->belongsToMany(ProductColorFamily::class, 'product_colors_families_colors', 'color_id', 'family_id')->withTimestamps();
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ProductColorAlias::class, 'color_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'color_id');
    }

    public function render_code()
    {
        if ($this->label == 'Multicolore') {
            return 'background: linear-gradient(43deg, rgba(59,180,58,1) 0%, rgba(253,248,29,1) 50%, rgba(252,69,69,1) 100%)';
        }

        return $this->build_color_background($this->code);
    }

    private function build_color_background($hex_code)
    {
        if ($hex_code == '') {
            return 'background-color:#FFFFFF;';
        }
        $colors = explode('/', $hex_code);
        if ($colors[0] == 0) {
        }
        if (count($colors) == 1) {
            return 'background-color:#'.$colors[0].';';
        }
        if (count($colors) == 2) {
            return 'background:linear-gradient(90deg, #'.$colors[0].' 50%, #'.$colors[1].' 50%);';
        }
        if (count($colors) == 3) {
            return 'background:linear-gradient(90deg, #'.$colors[0].' 33%, #'.$colors[1].' 33%, #'.$colors[1].' 67%, #'.$colors[2].' 67%);';
        }
    }

    private function invert_color($color)
    {
        if (strpos($color, '/')) {
            $colors = explode('/', $color);
            $color = $colors[0];
        }
        $color = str_replace('#', '', $color);
        if (strlen($color) != 6) {
            return '#000000';
        }
        $rgb = '';
        for ($x = 0; $x < 3; $x++) {
            $c = 255 - hexdec(substr($color, (2 * $x), 2));
            $c = ($c < 0) ? 0 : dechex($c);
            $rgb .= (strlen($c) < 2) ? '0'.$c : $c;
        }

        return '#'.$rgb;
    }
}
