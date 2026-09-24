<?php

declare(strict_types=1);

namespace App\Http\Livewire;

use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogFilterOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Catalogue listing: filters, sorting, page size, paginated grid.
 *
 * The URL is the API: brand, color, print, purchase, min, max, new, sale,
 * green, promo, sort, limit and page keep the names they had, so shared
 * links and skins' custom JavaScript keep working. The filter panel and the
 * grid live in one component; filters apply immediately, as before.
 */
final class FrontendProductList extends Component
{
    use WithPagination;

    public const DEFAULT_LIMIT = 16;

    public const PRICE_MAX = 10000000;

    protected $queryString = [
        'urlBrand' => ['as' => 'brand', 'except' => '', 'history' => false],
        'urlColor' => ['as' => 'color', 'except' => '', 'history' => false],
        'urlPrint' => ['as' => 'print', 'except' => '', 'history' => false],
        'urlPurchase' => ['as' => 'purchase', 'except' => '', 'history' => false],
        'min_price' => ['as' => 'min', 'except' => 0, 'history' => false],
        'max_price' => ['as' => 'max', 'except' => self::PRICE_MAX, 'history' => false],
        'is_new' => ['as' => 'new', 'except' => false, 'history' => false],
        'is_sale' => ['as' => 'sale', 'except' => false, 'history' => false],
        'is_green' => ['as' => 'green', 'except' => false, 'history' => false],
        'is_promo' => ['as' => 'promo', 'except' => false, 'history' => false],
        'urlSort' => ['as' => 'sort', 'except' => 'price', 'history' => false],
        'limit' => ['except' => self::DEFAULT_LIMIT, 'history' => false],
    ];

    /** Context, set once at mount. */
    public ?int $categoryId = null;

    public string|false $brand = false;

    /** Explicit product set (CMS pages); empty means "by category/brand/all". */
    /** @var list<int> */
    public array $products_ids = [];

    /** First grid card is the page LCP when the listing has no bestseller slider above it. */
    public bool $prioritizeFirstCard = true;

    /** Query-string state. */
    public string $urlBrand = '';

    public string $urlColor = '';

    public string $urlPrint = '';

    public string $urlPurchase = '';

    /** Source filter options offered by the enabled connectors: key => label. @return array<string, string> */
    #[\Livewire\Attributes\Computed]
    public function purchaseOptions(): array
    {
        $options = [];
        foreach (app(\App\Support\ImportConnectors::class)->enabled() as $connector) {
            if ($connector->catalogFilterLabel() !== null) {
                $options[$connector->key()] = (string) $connector->catalogFilterLabel();
            }
        }

        return $options;
    }

    public string $urlSort = 'price';

    public int $limit = self::DEFAULT_LIMIT;

    public float|int|string $min_price = 0;

    public float|int|string $max_price = self::PRICE_MAX;

    public bool $is_new = false;

    public bool $is_sale = false;

    public bool $is_green = false;

    public bool $is_promo = false;

    /** Long option lists show a first slice until expanded (keeps the HTML small). */
    public const OPTIONS_PREVIEW = 12;

    /** @var array{colors: bool, brands: bool, prints: bool} */
    public array $expanded = ['colors' => false, 'brands' => false, 'prints' => false];

    /** Price inputs: empty means "no bound"; mirrored into min_price / max_price. */
    public string $priceMinInput = '';

    public string $priceMaxInput = '';

    /** Checkbox bindings, mirrored into the csv query-string properties. */
    /** @var list<string> */
    public array $colors = [];

    /** @var list<string> */
    public array $brands = [];

    /** @var list<string> */
    public array $print_techniques = [];

    /** @param  list<int>  $products_ids */
    public function mount(int|string|false $category = 0, string|false $brand = false, array $products_ids = [], bool $prioritizeFirstCard = true): void
    {
        $this->categoryId = $category ? (int) $category : null;
        $this->brand = $brand;
        $this->products_ids = array_map('intval', $products_ids);
        $this->prioritizeFirstCard = $prioritizeFirstCard;
        $this->syncArraysFromUrl();
        $this->priceMinInput = (float) $this->min_price > 0 ? (string) $this->min_price : '';
        $this->priceMaxInput = (float) $this->max_price < self::PRICE_MAX ? (string) $this->max_price : '';
    }

    /**
     * Filter options for the current context; cached, never part of the snapshot.
     *
     * @return array{colors: list<array{id: int, label: string, code: string}>, brands: list<array{name: string, count: int}>, prints: list<array{label: string, count: int}>, price: array{0: float, 1: float}}
     */
    #[Computed]
    public function options(): array
    {
        return CatalogFilterOptions::forContext($this->categoryId, $this->brand ?: null);
    }

    /** @return list<array{id: int, name: string, slug: string, count: int}> */
    #[Computed]
    public function navigation(): array
    {
        return CatalogFilterOptions::navigationFor($this->categoryId);
    }

    #[Computed]
    public function category(): ?Category
    {
        return $this->categoryId ? Category::find($this->categoryId) : null;
    }

    public function render(): View
    {
        $this->syncArraysFromUrl();

        $products = $this->applyCatalogFilters($this->baseQuery());

        return view('livewire.frontend-product-list', [
            'products' => $products,
            'count' => $products->total(),
            'currentSort' => $this->sortKey(),
            'activeFilters' => $this->activeFilterCount(),
        ]);
    }

    /* Checkbox and select bindings ------------------------------------ */

    public function updatedColors(): void
    {
        $this->urlColor = $this->arrayToCsv($this->colors);
        $this->resetPage();
    }

    public function updatedBrands(): void
    {
        $this->urlBrand = $this->arrayToCsv($this->brands);
        $this->resetPage();
    }

    public function updatedPrintTechniques(): void
    {
        $this->urlPrint = $this->arrayToCsv($this->print_techniques);
        $this->resetPage();
    }

    public function updatedPriceMinInput(): void
    {
        $this->min_price = is_numeric($this->priceMinInput) ? (float) $this->priceMinInput : 0;
        $this->resetPage();
    }

    public function updatedPriceMaxInput(): void
    {
        $this->max_price = is_numeric($this->priceMaxInput) && (float) $this->priceMaxInput > 0 ? (float) $this->priceMaxInput : self::PRICE_MAX;
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['urlPurchase', 'urlSort', 'limit', 'is_new', 'is_sale', 'is_green', 'is_promo'], true)) {
            $this->normaliseScalars();
            $this->resetPage();
        }
    }

    public function expand(string $group): void
    {
        if (array_key_exists($group, $this->expanded)) {
            $this->expanded[$group] = true;
        }
    }

    /**
     * Options to render for a group: all when expanded, otherwise the first
     * slice plus every selected value so the state is always visible.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $selected
     * @return array{items: list<array<string, mixed>>, hidden: int}
     */
    public function visibleOptions(string $group, array $items, array $selected, string $key): array
    {
        if ($this->expanded[$group] ?? false) {
            return ['items' => $items, 'hidden' => 0];
        }

        $visible = array_slice($items, 0, self::OPTIONS_PREVIEW);
        foreach ($items as $item) {
            if (in_array((string) $item[$key], $selected, true) && ! in_array($item, $visible, true)) {
                $visible[] = $item;
            }
        }

        return ['items' => $visible, 'hidden' => max(0, count($items) - count($visible))];
    }

    public function resetFilters(): void
    {
        $this->reset('urlBrand', 'urlColor', 'urlPrint', 'urlPurchase', 'min_price', 'max_price', 'priceMinInput', 'priceMaxInput', 'is_new', 'is_sale', 'is_green', 'is_promo', 'colors', 'brands', 'print_techniques');
        $this->resetPage();
    }

    /* Event API kept for custom scripts ------------------------------- */

    #[On('filter_brands')]
    public function filter_brands(string $jsonBrandsArray): void
    {
        $this->brands = $this->decodeFilterArray($jsonBrandsArray);
        $this->updatedBrands();
    }

    #[On('filter_colors')]
    public function filter_colors(string $jsonColorsArray): void
    {
        $this->colors = $this->decodeFilterArray($jsonColorsArray);
        $this->updatedColors();
    }

    #[On('filter_prints')]
    public function filter_prints(string $jsonPrintsArray): void
    {
        $this->print_techniques = $this->decodeFilterArray($jsonPrintsArray);
        $this->updatedPrintTechniques();
    }

    #[On('filter_prices')]
    public function filter_prices(string $jsonRequestedPricesArray): void
    {
        $requested = json_decode($jsonRequestedPricesArray, true);
        $this->min_price = isset($requested[0]) && is_numeric($requested[0]) ? (float) $requested[0] : 0;
        $this->max_price = isset($requested[1]) && is_numeric($requested[1]) ? (float) $requested[1] : self::PRICE_MAX;
        $this->resetPage();
    }

    #[On('filter_purchase')]
    public function filter_purchase(string $jsonPurchaseTypeArray): void
    {
        $values = $this->decodeFilterArray($jsonPurchaseTypeArray);
        $this->urlPurchase = (string) ($values[0] ?? '');
        $this->resetPage();
    }

    #[On('filter_is_new')]
    public function filter_is_new(mixed $value): void
    {
        $this->is_new = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->resetPage();
    }

    #[On('filter_is_sale')]
    public function filter_is_sale(mixed $value): void
    {
        $this->is_sale = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->resetPage();
    }

    #[On('filter_is_green')]
    public function filter_is_green(mixed $value): void
    {
        $this->is_green = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->resetPage();
    }

    #[On('filter_is_promo')]
    public function filter_is_promo(mixed $value): void
    {
        $this->is_promo = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->resetPage();
    }

    #[On('select_pagination')]
    public function select_pagination(mixed $requestedPagination): void
    {
        $this->limit = (int) $requestedPagination ?: self::DEFAULT_LIMIT;
        $this->resetPage();
    }

    #[On('select_orderby')]
    public function select_orderby(mixed $requestedOrderby): void
    {
        $this->urlSort = in_array($requestedOrderby, ['name', 'price', 'position'], true) ? $requestedOrderby : 'price';
        $this->resetPage();
    }

    #[On('refreshComponent')]
    public function refreshComponent(): void
    {
        // $refresh
    }

    /* Query ------------------------------------------------------------ */

    /** @return Builder<Product>|\Illuminate\Database\Eloquent\Relations\BelongsToMany<Product, Category, \App\Models\ProductCategory> */
    private function baseQuery(): Builder|\Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        if ($category = $this->category()) {
            return $category->products();
        }

        return Product::where('active', 1)
            ->when($this->products_ids !== [], fn ($q) => $q->whereIn('id', $this->products_ids))
            ->when($this->brand !== false && $this->brand !== '', fn ($q) => $q->where('brand', 'LIKE', '%'.$this->brand.'%'));
    }

    /** @return \Illuminate\Contracts\Pagination\LengthAwarePaginator */
    private function applyCatalogFilters(Builder|\Illuminate\Database\Eloquent\Relations\BelongsToMany $query)
    {
        $this->normaliseScalars();

        return $query
            ->with(['color_variants.color', 'main_variant_relationship'])
            ->when($this->brands !== [], fn ($q) => $q->whereIn('brand', $this->brands))
            ->when($this->colors !== [], fn ($q) => $q->whereHas('variants', fn ($sq) => $sq->whereIn('color_id', $this->colors)))
            ->when($this->print_techniques !== [], function ($q) {
                return $q->whereHas('customizations', function ($sq) {
                    $sq->where(function ($inner) {
                        foreach ($this->print_techniques as $index => $technique) {
                            $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                            $inner->{$method}('FIND_IN_SET(?, technique_label)', [$technique]);
                        }
                    });
                });
            })
            ->when($this->urlPurchase !== '', fn ($q) => $q->whereIn('source', app(\App\Support\ImportConnectors::class)->find($this->urlPurchase)?->sourceValues() ?? [$this->urlPurchase]))
            ->when($this->is_new, fn ($q) => $q->where('products.created_at', '>', now()->subMonth()))
            ->when($this->is_sale, fn ($q) => $q->whereHas('variants', fn ($sq) => $sq->whereIn('isSale', [1, 2])))
            ->when($this->is_green, fn ($q) => $q->where('isGreen', true))
            ->when($this->is_promo, fn ($q) => $q->where('isPromo', true))
            ->when($this->hasPriceFilter(), fn ($q) => $q->where('variants_min_price', '>', (float) $this->min_price)->where('variants_max_price', '<', (float) $this->max_price))
            ->orderBy($this->orderByColumn())
            ->paginate($this->limit ?: self::DEFAULT_LIMIT);
    }

    private function syncArraysFromUrl(): void
    {
        $this->brands = $this->csvToArray($this->urlBrand);
        $this->colors = $this->csvToArray($this->urlColor);
        $this->print_techniques = $this->csvToArray($this->urlPrint);
        $this->normaliseScalars();
    }

    private function normaliseScalars(): void
    {
        $this->urlSort = in_array($this->urlSort, ['name', 'price', 'position'], true) ? $this->urlSort : 'price';
        $this->limit = in_array((int) $this->limit, [16, 24, 36], true) ? (int) $this->limit : self::DEFAULT_LIMIT;
        $this->min_price = is_numeric($this->min_price) ? (float) $this->min_price : 0;
        $this->max_price = is_numeric($this->max_price) && (float) $this->max_price > 0 ? (float) $this->max_price : self::PRICE_MAX;
        if ($this->min_price == 0) {
            $this->min_price = 0;
        }
        if ($this->max_price == self::PRICE_MAX) {
            $this->max_price = self::PRICE_MAX;
        }
    }

    private function hasPriceFilter(): bool
    {
        return (float) $this->min_price != 0 || (float) $this->max_price != self::PRICE_MAX;
    }

    private function orderByColumn(): string
    {
        return match ($this->urlSort) {
            'name' => 'name',
            'position' => $this->categoryId ? 'category_product.position' : 'variants_min_price',
            default => 'variants_min_price',
        };
    }

    private function sortKey(): string
    {
        return $this->urlSort;
    }

    private function activeFilterCount(): int
    {
        return count($this->brands) + count($this->colors) + count($this->print_techniques)
            + ($this->urlPurchase !== '' ? 1 : 0) + (int) $this->is_new + (int) $this->is_sale + (int) $this->is_green + (int) $this->is_promo
            + ($this->hasPriceFilter() ? 1 : 0);
    }

    /** @return list<string> */
    private function csvToArray(?string $csv): array
    {
        if ($csv === null || $csv === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), fn ($value) => $value !== ''));
    }

    /** @param  list<mixed>  $values */
    private function arrayToCsv(array $values): string
    {
        return implode(',', array_values(array_filter(array_map('strval', $values), fn ($value) => $value !== '')));
    }

    /** @return list<string> */
    private function decodeFilterArray(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded), fn ($value) => $value !== '')) : [];
    }
}
