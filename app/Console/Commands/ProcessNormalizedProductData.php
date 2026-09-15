<?php

namespace App\Console\Commands;

use App\Models\CategoryImportAlias;
use App\Models\ImportData\NormalizedProduct;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductColor;
use App\Models\ProductSize;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Support\CaughtExceptionLogger;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;

class ProcessNormalizedProductData extends ImportCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:ProcessNormalizedProductData {import_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process normalized product data';

    protected $context = 'import';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // ===== INIZIALIZZAZIONE IMPORT =====
        $this->import_id = $this->argument('import_id') ? $this->argument('import_id') : time();
        $start = microtime(true);

        $this->db_log('Inizio import', 'Import iniziato alle '.date('H:i').' del '.date('d/m/Y'), 'warn');

        // Caricamento dati di riferimento
        $meta_colors = DB::table('meta_colors')
            ->select(DB::raw('*'))
            ->get();

        $attributes = ProductAttribute::all();

        // Recupero prodotti normalizzati attivi negli ultimi 3 giorni
        $normalized_products = NormalizedProduct::with('variants')
            ->where('normalized_products.last_seen_active', '>', Carbon::now()->subDays(3)->toDateString())
            ->get();

        $total_products = $normalized_products->count();
        $processed_products = 0;

        // ===== CICLO PRINCIPALE SUI PRODOTTI =====
        foreach ($normalized_products as $normalized_product) {
            $processed_products++;
            $this->line("Elaborazione prodotto $processed_products / $total_products: ".$normalized_product->source_id.' ('.$normalized_product->source.' '.$normalized_product->subsource.')');

            // ===== SEZIONE 1: CREAZIONE/RECUPERO PRODOTTO =====
            $product_created = false;

            // Cerca se il prodotto esiste già nel database
            $product = Product::with('variants')
                ->where('source', $normalized_product->source)
                ->where('source_sku', $normalized_product->source_id)
                ->first();

            if (! $product) {
                // CASO 1: Prodotto non esiste - Creazione nuovo prodotto
                $this->line('aggiunta prodotto');
                $product = Product::create([
                    'source' => $normalized_product->source,
                    'subsource' => $normalized_product->subsource,
                    'source_sku' => $normalized_product->source_id,
                    'sku' => $normalized_product->source_id,
                    'active' => true,  // Nuovo prodotto inizia come attivo
                    'name' => $normalized_product->source_id,
                    'slug' => $normalized_product->source_id,
                    'full_update' => true,  // Flag per aggiornamento completo
                    'isGreen' => $normalized_product->isGreen,  // Copy isGreen from normalized product
                    'isPromo' => $normalized_product->isPromo,  // Copy isPromo from normalized product
                ]);
                $product_created = true;
                $log = 'Nuovo prodotto '.$normalized_product->source.' '.$normalized_product->source_id.' aggiunto';
            } elseif ($product->full_update) {
                // CASO 2: Prodotto esiste ed è marcato per aggiornamento completo
                $log = 'Prodotto '.$normalized_product->source.' '.$normalized_product->source_id.' in aggiornamento';
            } else {
                // CASO 3: Prodotto esiste - Aggiornamento normale
                $log = 'Prodotto '.$normalized_product->source.' '.$normalized_product->source_id.' in elaborazione';
            }

            $this->line('Prodotto '.($product_created ? 'creato' : 'aggiornato'));

            // ===== SEZIONE 2: GESTIONE CATEGORIE =====
            if ($this->update_categories) {
                $product->categories()->detach();
            }
            // Solo se il prodotto non ha ancora categorie assegnate
            if ($product->categories()->count() == 0) {
                if (! $normalized_product->category || ! $normalized_product->parent_category) {
                    // Errore: dati categoria mancanti nel prodotto normalizzato
                    $this->db_log('Categoria prodotto', "Dati categoria mancanti all'origine", 'error', 'product', $product->id, $product->source_sku);
                } else {
                    // Cerca alias categoria per mappare categoria origine -> categoria destinazione
                    $category_alias = CategoryImportAlias::where('source', $normalized_product->source)
                        ->where('parent_category_ref', $normalized_product->parent_category)
                        ->where('category_ref', $normalized_product->category)
                        ->first();

                    if (! $category_alias) {
                        // Alias non trovato - Crea nuovo alias non mappato per futura mappatura
                        $this->db_log('Categoria prodotto', 'Alias categoria ['.$normalized_product->source.'] '.$normalized_product->parent_category.'>'.$normalized_product->category.' non trovato per il prodotto '.$normalized_product->source_id.' (alias non mappato aggiunto)', 'error', 'product', $product->id, $product->source_sku);
                        CategoryImportAlias::create([
                            'source' => $normalized_product->source,
                            'parent_category_ref' => $normalized_product->parent_category,
                            'category_ref' => $normalized_product->category,
                        ]);
                    } else {
                        if ($category_alias->category) {
                            // Alias trovato e mappato - Assegna categoria e categoria padre
                            $product->categories()->sync([
                                $category_alias->category_id,
                                $category_alias->category->parent_id,
                            ]);
                        } else {
                            // Alias trovato ma non mappato a categoria destinazione
                            $this->db_log('Categoria prodotto', 'Alias categoria ['.$normalized_product->source.'] '.$normalized_product->parent_category.'>'.$normalized_product->category.' non mappata per il prodotto '.$normalized_product->source_id, 'warn', 'product', $product->id, $product->source_sku);
                        }
                    }
                }
            }

            // ===== SEZIONE 3: DETERMINAZIONE TIPO DI AGGIORNAMENTO =====
            // IMPORTANTE: $product_active inizializzato a FALSE - il prodotto sarà attivo solo se ha varianti attive
            $product_active = false;
            $partial_update = false;

            // Verifica se fare aggiornamento parziale (solo prezzi/stock) o completo
            if (! $product->full_update && ! $this->full_products_update) {
                // Confronta varianti esistenti attive con varianti normalizzate attive
                $existing_variants = $product->active_variants()->pluck('source_sku')->toArray();
                $normalized_variants_skus = [];
                foreach ($normalized_product->active_variants as $normalized_variant) {
                    array_push($normalized_variants_skus, $normalized_variant->source_id);
                }

                // Ordina per confronto accurato
                sort($existing_variants);
                sort($normalized_variants_skus);

                // Se le varianti sono identiche, aggiornamento parziale
                if ($existing_variants === $normalized_variants_skus) {
                    $partial_update = true;
                    // IMPORTANTE: Se ci sono varianti attive esistenti, il prodotto è attivo
                    if (count($existing_variants) > 0) {
                        $product_active = true;
                    }
                }
            }

            // ===== SEZIONE 4: INIZIALIZZAZIONE VARIABILI PER ELABORAZIONE VARIANTI =====
            $is_first_variant = true;          // Flag per identificare prima variante
            $variants_colors = [];             // Array colori delle varianti
            $brand = false;                    // Brand del prodotto
            $brand_image = false;              // Immagine brand
            $main_variant_id = $product->main_variant_id;  // Variante principale esistente

            // ===== SEZIONE 5: CICLO ELABORAZIONE VARIANTI =====
            foreach ($normalized_product->variants as $normalized_variant) {
                // FILTRO: Elabora solo varianti viste attive negli ultimi 3 giorni
                if ((int) Carbon::now()->diffInDays($normalized_variant->last_seen_active, true) <= 3) {
                    $variant_modified = false;  // Flag per tracciare modifiche alla variante

                    // Messaggio diverso per aggiornamento parziale vs completo
                    if ($partial_update) {
                        $this->line('Aggiornamento parziale variante: '.$normalized_variant->source_id);
                    } else {
                        $this->line('Aggiunta o aggiornamento variante: '.$normalized_variant->source_id);
                    }

                    // ===== SEZIONE 5.1: RECUPERO/CREAZIONE VARIANTE =====
                    if ($partial_update) {
                        // In aggiornamento parziale, recupera variante esistente
                        $variant = ProductVariant::where('source', $normalized_product->source)
                            ->where('source_sku', $normalized_variant->source_id)
                            ->first();

                        $update_data = [
                            'stock' => $normalized_variant->stock,
                            'next_stock_date' => $normalized_variant->next_stock_date,
                            'next_stock_quantity' => $normalized_variant->next_stock_quantity,
                        ];
                        // Aggiorna isSale solo se non è forzato (valore 2)
                        if ($variant->isSale != 2) {
                            $update_data['isSale'] = $normalized_variant->sale ? 1 : 0;  // Update sale status from normalized variant
                        }
                        $variant->update($update_data);
                        $variants_colors[$variant->id] = $variant->color_id;

                    } else {
                        // In aggiornamento completo, processa completamente la variante

                        // Solo per la prima variante, aggiorna nome e descrizione prodotto
                        if ($is_first_variant) {
                            if ($product->wasChanged()) {
                                $this->line('Nome prodotto: '.$normalized_variant->name);
                            }
                            if ($product->full_update || $this->full_products_update) {
                                $product->name = $normalized_variant->name;
                                $product->description = $normalized_variant->full_description;
                                $product->save();
                                $product->slug(true);
                            }
                            $is_first_variant = false;
                        }

                        // ===== SEZIONE 5.2: GESTIONE COLORE VARIANTE =====
                        $color_label = '';
                        $color_code = '';

                        // Caso 1: Variante con colori multipli
                        if ($normalized_variant->colors()->count() > 0) {
                            foreach ($normalized_variant->colors as $normalized_variant_color) {
                                $color_label .= $normalized_variant_color->label.'/';
                                $color_code .= $normalized_variant_color->hex_code.'/';
                            }
                            $color_label = rtrim($color_label, '/');
                            $color_code = rtrim($color_code, '/');
                        }
                        // Caso 2: Variante con colore singolo
                        elseif ($normalized_variant->color) {
                            $color_label = $normalized_variant->color->label;
                            $color_code = $normalized_variant->color->hex_code;
                        }

                        // Trova o crea colore destinazione
                        $destination_color = ProductColor::where('label', $color_label)->first();
                        if (! $destination_color) {
                            $destination_color = ProductColor::create([
                                'label' => $color_label,
                                'code' => $color_code,
                            ]);
                        }

                        // ===== SEZIONE 5.3: GESTIONE TAGLIA VARIANTE =====
                        // Se taglia mancante, prova a dedurla dal codice variante
                        if (! $normalized_variant->size || $normalized_variant->size == '') {
                            if (str_contains($normalized_variant->source_id, 'S/M')) {
                                $normalized_variant->size = 'S/M';
                            } elseif (str_contains($normalized_variant->source_id, 'M/L')) {
                                $normalized_variant->size = 'M/L';
                            } elseif (str_contains($normalized_variant->source_id, 'L/XL')) {
                                $normalized_variant->size = 'L/XL';
                            } elseif (str_contains($normalized_variant->source_id, 'XL/XXL')) {
                                $normalized_variant->size = 'XL/XXL';
                            } elseif ($normalized_product->variants()->count() == $normalized_product->variants()->whereNull('size')->count() || $normalized_product->variants()->count() == $normalized_product->variants()->where('size', '')->count()) {
                                // Se nessuna variante ha taglia, usa N/A
                                $normalized_variant->size = 'N/A';
                            } else {
                                // Default a M
                                $normalized_variant->size = 'M';
                            }
                            $this->db_log('product size', 'Dimensione non trovata nel prodotto normalizzato, assegnata dimensione "'.$normalized_variant->size.'"', 'warn', 'normalized_variant', $normalized_product->id, $normalized_variant->id);
                        }

                        // Crea taglia se specificata
                        if ($normalized_variant->size) {
                            $destination_size = ProductSize::firstOrCreate(['label' => $normalized_variant->size]);
                        }

                        // ===== SEZIONE 5.4: CREAZIONE/AGGIORNAMENTO VARIANTE =====
                        $variant = $normalized_variant->app_variant;

                        if (! $variant) {
                            // CREA nuova variante - Inizia SEMPRE come attiva
                            $variant = ProductVariant::create([
                                'product_id' => $product->id,
                                'source' => $normalized_product->source,
                                'source_sku' => $normalized_variant->source_id,
                                'sku' => $normalized_variant->source_id,
                                'stock' => $normalized_variant->stock,
                                'isSale' => $normalized_variant->sale ? 1 : 0,  // Copy sale status from normalized variant
                                'next_stock_date' => $normalized_variant->next_stock_date,
                                'next_stock_quantity' => $normalized_variant->next_stock_quantity,
                                'active' => true,  // IMPORTANTE: Nuova variante sempre attiva inizialmente
                                'color_id' => $destination_color->id,
                                'size_id' => $destination_size->id ?? 0,
                                'themes' => $normalized_variant->theme,
                            ]);
                            $this->db_log('variante aggiunta', 'Nuova variante '.$normalized_product->source.' '.$normalized_variant->source_id.' aggiunta: '.$normalized_variant->name, 'success', 'variant', $variant->id, $variant->source_sku);
                        } else {
                            // AGGIORNA variante esistente - Imposta come attiva
                            $update_data = [
                                'stock' => $normalized_variant->stock,
                                'next_stock_date' => $normalized_variant->next_stock_date,
                                'next_stock_quantity' => $normalized_variant->next_stock_quantity,
                                'active' => true,  // IMPORTANTE: Variante aggiornata impostata come attiva
                                'color_id' => $destination_color->id,
                                'size_id' => $destination_size->id ?? 0,
                                'themes' => $normalized_variant->theme,
                            ];

                            // Aggiorna isSale solo se non è forzato (valore 2)
                            if ($variant->isSale != 2) {
                                $update_data['isSale'] = $normalized_variant->sale ? 1 : 0;  // Update sale status from normalized variant
                            }

                            $variant->update($update_data);
                            $this->line('Variante '.$normalized_product->source.' '.$normalized_variant->source_id.' aggiornata: '.$normalized_variant->name);
                        }

                        // Memorizza colore variante
                        $variants_colors[$variant->id] = $destination_color->id;

                        // ===== SEZIONE 5.5: GESTIONE ATTRIBUTI VARIANTE =====
                        $sync_attributes = [];
                        foreach ($attributes as $attribute) {
                            $attribute_alias = $attribute->alias;
                            if ($normalized_variant->$attribute_alias) {
                                $sync_attributes[$attribute->id] = [
                                    'product_id' => $product->id,
                                    'value' => $normalized_variant->$attribute_alias,
                                ];
                            }
                        }
                        $variant->attributes()->sync($sync_attributes);

                        // Estrai brand dall'attributo 3 (se presente)
                        if (! $brand_image && isset($sync_attributes[3])) {
                            $brand = $sync_attributes[3]['value'];
                            $brand_image = preg_replace('/[^A-Za-z0-9]/', '', strtolower(str_replace(' ', '', $brand))).'.png';
                        }

                    } // Fine elaborazione completa variante (non partial_update)

                    // ===== SEZIONE 5.6: GESTIONE IMMAGINI VARIANTE =====
                    // Se aggiornamento completo prodotto, pulisci immagini esistenti
                    if ($product->full_update) {
                        $variant->clearMediaCollection('image');
                    }

                    // Verifica se variante ha già immagini
                    $media = $variant->getFirstMedia('image');
                    $has_media = $media ? true : false;

                    if (! $has_media) {
                        // Variante senza immagini - Prova a caricarle
                        $this->db_log('Aggiunta immagini variante', 'Variante '.$normalized_product->source.' '.$normalized_variant->source_id.' senza immagini, aggiunta immagini in corso', 'info', 'variant', $variant->id, $variant->source_sku);

                        // Recupera URL immagini dal database normalizzato
                        $images = DB::table('normalized_products_variants_images')
                            ->select(DB::raw('url'))
                            ->where('variant_id', $normalized_variant->id)
                            ->groupBy('url')
                            ->orderBy('main', 'desc')  // Prima immagine principale
                            ->orderBy('id', 'asc')
                            ->get();

                        if (! isset($images) || empty($images)) {
                            $this->db_log('errore aggiunta immagini variante', 'Nessuna immagine associata alla variante normalizzata', 'error', 'variant', $variant->id, $variant->source_sku);
                        }

                        // Tenta caricamento di ogni immagine
                        foreach ($images as $image) {
                            $this->line('Processazione immagine: '.$image->url);
                            try {
                                // Scarica l'immagine
                                $response = Http::timeout(30)->get($image->url);
                                if (! $response->successful()) {
                                    $this->db_log('errore aggiunta immagini variante', "Errore nel download dell'immagine: ".$image->url, 'error', 'variant', $variant->id, $variant->source_sku);

                                    continue;
                                }
                                $contentType = strtolower((string) $response->header('Content-Type', ''));
                                if (! str_starts_with($contentType, 'image/')) {
                                    $this->db_log(
                                        'errore aggiunta immagini variante',
                                        'Content-Type non valido per immagine: '.$image->url." (content-type: {$contentType})",
                                        'error',
                                        'variant',
                                        $variant->id,
                                        $variant->source_sku
                                    );

                                    continue;
                                }
                                // Crea file temporaneo
                                $tempPath = tempnam(sys_get_temp_dir(), 'variant_image_');
                                file_put_contents($tempPath, $response->body());
                                // Verifica che sia un'immagine valida
                                try {
                                    $img = Image::make($tempPath);
                                    // Ridimensiona se necessario
                                    if ($img->height() > 1600) {
                                        $img->heighten(1600);
                                        $img->save($tempPath);
                                    }

                                    // Estrae il filename originale dall'URL
                                    $originalFilename = basename(parse_url($image->url, PHP_URL_PATH));

                                    // Se il filename non ha estensione, aggiungila dal mime type
                                    if (! pathinfo($originalFilename, PATHINFO_EXTENSION)) {
                                        $mimeType = $img->mime();
                                        $extension = match ($mimeType) {
                                            'image/jpeg' => 'jpg',
                                            'image/png' => 'png',
                                            'image/gif' => 'gif',
                                            'image/webp' => 'webp',
                                            default => 'jpg',
                                        };
                                        $originalFilename .= '.'.$extension;
                                    }

                                    // Salva in Media Library usando il filename originale
                                    $variant->addMedia($tempPath)
                                        ->usingFileName($originalFilename)
                                        ->toMediaCollection('image');
                                } catch (\Exception $e) {
                                    CaughtExceptionLogger::error('ProcessNormalizedProductData: variant image conversion failed', $e, [
                                        'variant_id' => $variant->id,
                                        'source_sku' => $variant->source_sku,
                                        'image_url' => $image->url ?? null,
                                    ]);
                                    @unlink($tempPath);
                                    $this->db_log('errore aggiunta immagini variante', "Errore nel processare l'immagine (conversione): ".$image->url.' '.$e->getMessage(), 'error', 'variant', $variant->id, $variant->source_sku);
                                }
                                // Cleanup
                                @unlink($tempPath);
                            } catch (\Exception $e) {
                                CaughtExceptionLogger::error('ProcessNormalizedProductData: variant image download/process failed', $e, [
                                    'variant_id' => $variant->id,
                                    'source_sku' => $variant->source_sku,
                                    'image_url' => $image->url ?? null,
                                ]);
                                $this->db_log('errore aggiunta immagini variante', "Errore nel processare l'immagine: ".$image->url.' '.$e->getMessage(), 'error', 'variant', $variant->id, $variant->source_sku);
                            }
                        }

                        $variant->refresh();
                        $media = $variant->getFirstMedia('image');
                        $has_media = $media ? true : false;
                        // CONDIZIONE DISATTIVAZIONE 1: Se nessuna immagine caricata con successo
                        if (! $has_media) {
                            $variant->active = false;  // DISATTIVA VARIANTE
                            $variant_modified = true;  // Flag per salvare alla fine
                            $this->db_log('errore aggiunta immagini variante', 'Nessuna immagine per la variabile processata, variabile disattivata', 'error', 'variant', $variant->id, $variant->source_sku);
                        }
                    }

                    // ===== SEZIONE 5.7: GESTIONE PREZZI VARIANTE =====
                    if (! $normalized_variant->prices) {
                        // Nessun prezzo normalizzato disponibile
                        if (! $variant->prices) {
                            // CONDIZIONE DISATTIVAZIONE 2: Nessun prezzo né nuovo né esistente
                            $this->db_log('prezzo variante', 'Prezzo normalizzato non trovato e nessun prezzo precedente salvato, variabile disabilitata', 'error', 'variant', $variant->id, $variant->source_sku);
                            $variant->active = false;  // DISATTIVA VARIANTE
                            $variant_modified = true;  // Flag per salvare alla fine
                        } else {
                            // Ha prezzi precedenti - Mantieni variante attiva ma logga warning
                            $this->db_log('prezzo variante', 'Prezzo normalizzato non trovato, il prezzo non è stato aggiornato', 'warn', 'variant', $variant->id, $variant->source_sku);
                        }
                    } else {
                        // Prezzi disponibili - Aggiorna
                        $variant->prices()->delete();  // Rimuovi prezzi esistenti

                        foreach ($normalized_variant->prices as $normalized_price) {
                            // Crea nuovo prezzo
                            ProductVariantPrice::create(
                                [
                                    'variant_id' => $variant->id,
                                    'from_quantity' => $normalized_price->from_quantity,
                                    'original_price' => $normalized_price->original_price,
                                    'price' => $normalized_price->price,
                                    'included_additional_costs' => $normalized_price->included_additional_costs,
                                ]
                            );
                        }
                    }

                    // ===== SEZIONE 5.8: disattivazione decisa dal connettore (fine serie, occasioni…) =====
                    if (app(\App\Support\ImportConnectors::class)->forSource($product->source)?->shouldDeactivateVariant($variant) ?? false) {
                        $variant->active = false;  // DISATTIVA VARIANTE
                        $variant_modified = true;  // Flag per salvare alla fine
                    }

                    // ===== SEZIONE 5.9: AGGIORNAMENTO STATUS PRODOTTO =====
                    // Se almeno una variante è attiva, il prodotto è attivo
                    if ($variant->active) {
                        $product_active = true;  // ATTIVA PRODOTTO

                        // Se non c'è variante principale, usa la prima attiva
                        if (! $main_variant_id) {
                            $main_variant_id = $variant->id;
                        }
                    }

                    // Salva variante solo se è stata modificata
                    if ($variant_modified) {
                        $variant->save();
                    }

                } // Fine IF controllo data last_seen_active
            } // Fine FOREACH varianti

            // ===== SEZIONE 6: AGGIORNAMENTO FINALE PRODOTTO =====
            // Solo se NON è aggiornamento parziale
            if (! $partial_update) {
                $product->variants_colors = json_encode($variants_colors);
                $product->brand = $brand;
                $product->brand_image = $brand_image;
                $product->default_customization_technique = $normalized_product->default_customization_technique;
                $product->default_customization_position = $normalized_product->default_customization_position;

                // CONDIZIONE DISATTIVAZIONE PRODOTTO: Nessuna variante principale
                if (! $main_variant_id) {
                    $product_active = false;  // DISATTIVA PRODOTTO
                    $this->db_log('variabile principale mancante', 'Variabile principale non impostata, prodotto disattivato', 'error', 'product', $product->id, $product->source_sku);
                }
            }

            // ===== SEZIONE 7: SALVATAGGIO FINALE PRODOTTO =====
            $product->full_update = $main_variant_id ? false : true;  // Reset flag aggiornamento completo se main_variant_id è impostato

            // Handle forced_status logic
            if ($product->forced_status == 'none' || ! $product->forced_status) {
                $product->active = $product_active ? 1 : 0;  // Converte boolean in 0/1
            } elseif ($product->forced_status == 'disabled') {
                $product->active = 0;  // Forza disabilitato
                // When product is forced disabled, disable all its variants too
                $product->variants()->update(['active' => 0]);
                $this->line('Prodotto forzato disabilitato - tutte le varianti sono state disabilitate');
            } elseif ($product->forced_status == 'active') {
                $product->active = 1;  // Forza attivo
            }

            $product->subsource = $normalized_product->subsource;
            $product->supplier_info = $normalized_product->supplier_info;

            // Aggiorna isGreen solo se non è forzato (valore 2)
            if ($product->isGreen != 2 && $product->isGreen != -1) {
                $product->isGreen = $normalized_product->isGreen;  // Update isGreen from normalized product
            }

            if ($product->isPromo != 2 && $product->isPromo != -1) {
                $product->isPromo = $normalized_product->isPromo;  // Update isPromo from normalized product
            }

            // Set main variant and calculate prices from main variant
            // If main_variant_id is valid, use it; otherwise choose randomly from valid variants
            if ($main_variant_id) {
                $product->set_main_variant($main_variant_id, true);
            } else {
                $product->set_main_variant(false, true); // Choose randomly from valid variants
            }

            // Save all changes
            $product->save();

            // Log finale con stato prodotto
            $this->info('Prodotto '.$product->source.' '.$product->source_sku.' '.$product->name.' processato, sku: '.$product->sku.', stato: '.($product->active == 1 ? 'Attivo' : 'Disabilitato'));

        } // Fine FOREACH prodotti

        // ===== SEZIONE 9: CONCLUSIONE IMPORT =====
        $time_elapsed_secs = microtime(true) - $start;
        $this->db_log('end', 'Import concluso alle '.date('H:i').' del '.date('d/m/Y').', durata totale: '.round($time_elapsed_secs / 60, 2).' minuti', 'success');

    }
}

/*
 * RIEPILOGO LOGICA DI ATTIVAZIONE/DISATTIVAZIONE:
 *
 * VARIANTI:
 * - Iniziano sempre come active = true (create o update)
 * - Vengono disattivate se:
 *   1. Non hanno immagini caricate con successo
 *   2. Non hanno prezzi (né normalizzati né esistenti)
 *
 * PRODOTTI:
 * - $product_active inizia come false
 * - Diventa true se:
 *   1. In aggiornamento parziale E ci sono varianti attive esistenti
 *   2. Almeno una variante elaborata è attiva
 * - Ritorna false se:
 *   1. Non ha main_variant_id (solo in aggiornamento completo)
 *   2. Nessuna variante è attiva
 */
