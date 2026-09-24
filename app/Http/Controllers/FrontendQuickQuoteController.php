<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Quotations\SendQuotation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Rules\Captcha;
use App\Support\CustomerFormRules;
use App\Support\FrontendDebugLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * JSON endpoints of the quick-quote modal (docs/04_STOREFRONT_FLOWS.md §4.4).
 * They read and write the same session store as the quotation page
 * (`quotation.products`, `quotation.customer`), so the two flows share the
 * request and the header counter; `send` produces the same rows and mails
 * as the page through SendQuotation.
 */
final class FrontendQuickQuoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'article_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:0'],
        ]);
        $article = ProductVariant::query()->with(['product', 'color', 'size'])->find((int) $data['article_id']);
        if ($article === null || $article->product === null) {
            abort(404);
        }
        $products = $this->products($request);
        // The same article opened twice (CTA, configurator) is one row; a quantity given now replaces the old one.
        foreach ($products as $id => $row) {
            if (($row['sku'] ?? null) === $article->product->sku && ($row['color'] ?? null) === ($article->color->label ?? '') && ($row['size'] ?? null) === ($article->size->label ?? __('frontend.cart.one_size'))) {
                if ((int) ($data['quantity'] ?? 0) > 0) {
                    $products[$id]['quantity'] = (int) $data['quantity'];
                    $request->session()->put('quotation.products', $products);
                }

                return response()->json($this->payload($request));
            }
        }
        $id = Str::random(9);
        $products[$id] = [
            'id' => $id,
            'product_id' => $article->product->id,
            'name' => $article->product->name,
            'sku' => $article->product->sku,
            'image' => $article->cover(true) ?: '',
            'color' => $article->color->label ?? '',
            'size' => $article->size->label ?? __('frontend.cart.one_size'),
            'quantity' => (int) ($data['quantity'] ?? 0),
            'printing' => __('frontend.quotation.no'),
            'notes' => '',
        ];
        $request->session()->put('quotation.products', $products);
        FrontendDebugLog::preventivo('quick:add', ['article_id' => $article->id, 'items_count' => count($products)]);

        return response()->json($this->payload($request));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $products = $this->products($request);
        if (! isset($products[$id])) {
            abort(404);
        }
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'printing' => ['nullable', 'string', Rule::in([__('frontend.quotation.yes'), __('frontend.quotation.no')])],
        ]);
        $row = $products[$id];
        foreach (['quantity', 'notes', 'color', 'size', 'printing'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $row[$field] = $field === 'quantity' ? (int) $data[$field] : (string) $data[$field];
            }
        }
        if (isset($data['color']) && $data['color'] !== ($products[$id]['color'] ?? null)) {
            $product = $this->product($row);
            $variant = $product?->color_variants->first(fn (ProductVariant $v): bool => $v->color?->label === $data['color']);
            if ($variant !== null) {
                $row['image'] = $variant->cover(true) ?: $row['image'];
            }
        }
        $products[$id] = $row;
        $request->session()->put('quotation.products', $products);

        return response()->json($this->payload($request));
    }

    public function remove(Request $request, string $id): JsonResponse
    {
        $request->session()->forget('quotation.products.'.$id);

        return response()->json($this->payload($request));
    }

    public function send(Request $request): JsonResponse
    {
        $request->validate(array_merge(
            CustomerFormRules::quotationCustomerRules($request),
            ['consent_gdpr' => ['required', 'accepted'], ...Captcha::rules('request_quotation')],
        ));
        $products = $this->products($request);
        if ($products === []) {
            return response()->json(['message' => __('frontend.quick_quote.no_products'), 'errors' => ['products' => [__('frontend.quick_quote.no_products')]]], 422);
        }
        foreach ($products as $row) {
            if ((int) ($row['quantity'] ?? 0) < 1) {
                return response()->json(['message' => __('frontend.quotation.quantity_error'), 'errors' => ['products' => [__('frontend.quotation.quantity_error')]]], 422);
            }
        }
        $customer = (array) $request->input('customer', []);
        $request->session()->put('quotation.customer', $customer);
        $request->session()->put('quotation.consent_gdpr', '1');
        $request->session()->put('quotation.subscribe_newsletter', $request->boolean('subscribe_newsletter') ? '1' : '0');

        $quotation = app(SendQuotation::class)->handle($customer, array_values($products), true, $request->boolean('subscribe_newsletter'));
        $request->session()->forget('quotation.products');
        FrontendDebugLog::preventivo('quick:completed', ['quotation_id' => $quotation->id, 'items_count' => count($products)]);

        return response()->json(['sent' => true, 'quotation_id' => $quotation->id] + $this->payload($request));
    }

    /** @return array<string, array<string, mixed>> */
    private function products(Request $request): array
    {
        $products = $request->session()->get('quotation.products', []);

        return is_array($products) ? $products : [];
    }

    /** @param  array<string, mixed>  $row */
    private function product(array $row): ?Product
    {
        if (isset($row['product_id'])) {
            return Product::query()->find((int) $row['product_id']);
        }

        return Product::query()->where('sku', (string) ($row['sku'] ?? ''))->first();
    }

    /** @return array{customer: array<string, mixed>, products: list<array<string, mixed>>, count: int} */
    private function payload(Request $request): array
    {
        $rows = [];
        foreach ($this->products($request) as $id => $row) {
            $product = $this->product($row);
            $colors = [];
            $sizes = [];
            if ($product !== null) {
                foreach ($product->color_variants as $variant) {
                    if ($variant->color !== null) {
                        $colors[] = ['label' => $variant->color->label, 'code' => $variant->color->code ?? null];
                    }
                }
                foreach ($product->size_variants as $variant) {
                    $sizes[] = $variant->size->label ?? __('frontend.cart.one_size');
                }
            }
            $rows[] = [
                'id' => (string) $id,
                'name' => (string) ($row['name'] ?? ''),
                'sku' => (string) ($row['sku'] ?? ''),
                'image' => (string) ($row['image'] ?? ''),
                'color' => (string) ($row['color'] ?? ''),
                'size' => (string) ($row['size'] ?? ''),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'printing' => (string) ($row['printing'] ?? __('frontend.quotation.no')),
                'notes' => (string) ($row['notes'] ?? ''),
                'colors' => $colors,
                'sizes' => $sizes,
            ];
        }
        $customer = $request->session()->get('quotation.customer', []);

        return ['customer' => is_array($customer) ? $customer : [], 'products' => $rows, 'count' => count($rows)];
    }
}
