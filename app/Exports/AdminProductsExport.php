<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * XLSX export of selected products (legacy bulk "Export" dumped raw rows;
 * this one maps the columns an operator reads).
 */
class AdminProductsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @param  array<int, int|string>  $products  product ids
     */
    public function __construct(public array $products) {}

    public function collection(): Enumerable
    {
        return Product::query()->whereIn('id', $this->products)->with(['categories', 'main_variant_relationship'])->orderBy('id')->get();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['ID', 'SKU', 'Nome', 'Marchio', 'Attivo', 'Prezzo min', 'Prezzo max', 'Categorie', 'Fonte', 'Slug', 'Creato il'];
    }

    /**
     * @param  Product  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        /** @var Collection<int, \App\Models\Category> $categories */
        $categories = $row->categories;

        return [
            $row->id,
            $row->sku,
            $row->name,
            $row->brand,
            $row->active ? 1 : 0,
            $row->variants_min_price,
            $row->variants_max_price,
            $categories->pluck('name')->implode(', '),
            $row->source,
            $row->slug,
            optional($row->created_at)->format('Y-m-d H:i'),
        ];
    }
}
