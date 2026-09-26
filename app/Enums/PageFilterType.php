<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** pages_contents.filter_type: how a CMS page selects the products it lists. */
enum PageFilterType: string implements HasLabel
{
    case ProductId = 'product_id';
    case CategoryId = 'category_id';
    case AttributeValue = 'attribute_id_value';
    case CreatedAfter = 'created_after_value';
    case IsSale = 'is_sale';
    case IsBestseller = 'is_bestseller';
    case IsGreen = 'is_green';
    case IsPromo = 'is_promo';
    case IsNew = 'is_new';

    public function getLabel(): string
    {
        return match ($this) {
            self::ProductId => 'Prodotto',
            self::CategoryId => 'Categoria',
            self::AttributeValue => 'Attributo',
            self::CreatedAfter => 'Creati dopo',
            self::IsSale => 'In saldo',
            self::IsBestseller => 'Bestseller',
            self::IsGreen => 'Green',
            self::IsPromo => 'In promozione',
            self::IsNew => 'Novità',
        };
    }
}
