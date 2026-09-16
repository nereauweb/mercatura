<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * The demo catalogue as plain data: neutral names, invented brands, no
 * supplier. Prices are unit costs; the seeder derives the selling tiers
 * with the markup bands of CoreSeeder, as the pricing helpers do.
 */
final class Catalog
{
    /** Colour label => hex ("a/b" = two-tone). */
    public const COLORS = [
        'Bianco' => 'FFFFFF', 'Nero' => '1F2937', 'Rosso' => 'DC2626', 'Blu navy' => '1E3A8A', 'Azzurro' => '60A5FA',
        'Verde' => '15803D', 'Giallo' => 'FACC15', 'Arancio' => 'F97316', 'Grigio' => '9CA3AF', 'Naturale' => 'E7DCC3',
        'Bordeaux' => '7F1D1D', 'Bianco/Nero' => 'FFFFFF/111827', 'Argento' => 'C0C4CC', 'Legno' => 'C08A4B',
    ];

    /** Root category => [icon colour, description, children]. */
    public const CATEGORIES = [
        'Abbigliamento' => ['1E3A8A', 'T-shirt, polo, felpe e cappellini da personalizzare con ricamo o stampa.', ['T-shirt', 'Polo', 'Felpe', 'Cappellini']],
        'Borse e zaini' => ['15803D', 'Shopper, zaini e sacche per fiere, eventi e promozioni.', ['Shopper', 'Zaini e sacche']],
        'Bere e tavola' => ['0891B2', 'Borracce, tazze e bicchieri con il tuo logo.', ['Borracce', 'Tazze e bicchieri']],
        'Scrittura e ufficio' => ['7F1D1D', 'Penne, matite e taccuini per la scrivania e la scuola.', ['Penne e matite', 'Taccuini']],
        'Tecnologia' => ['4B5563', 'Chiavette USB, powerbank e accessori per lo smart working.', ['Chiavette USB', 'Powerbank']],
        'Tempo libero' => ['F97316', 'Ombrelli e teli mare per l\'estate e gli eventi all\'aperto.', ['Ombrelli', 'Teli mare']],
    ];

    /** Printing position presets: label => [size label, width mm, height mm]. */
    public const POSITIONS = [
        'Fronte' => ['10x10 cm', 100, 100],
        'Retro' => ['20x25 cm', 200, 250],
        'Petto sinistro' => ['8x8 cm', 80, 80],
        'Fronte cappellino' => ['8x5 cm', 80, 50],
        'Lato' => ['6x3 cm', 60, 30],
        'Corpo' => ['12x6 cm', 120, 60],
        'Copertina' => ['10x6 cm', 100, 60],
        'Fusto' => ['4x1 cm', 40, 10],
        'Spicchio' => ['15x12 cm', 150, 120],
        'Angolo' => ['20x20 cm', 200, 200],
    ];

    /**
     * Technique => [colour options (label, number_of_colors, unit cost multiplier, setup cost), processing days].
     * Label "0" is full colour (CustomizationOption::label()).
     */
    /** Techniques whose family is beyond doubt in the demo (docs/03 decision 2: optional, untyped). */
    public const FAMILIES = ['Ricamo' => 'embroidery', 'Incisione laser' => 'engraving'];

    public const TECHNIQUES = [
        'Serigrafia tessile' => [[['1', 1, 1.0, 25.0], ['2', 2, 1.7, 45.0]], 5],
        'Serigrafia' => [[['1', 1, 1.0, 25.0], ['2', 2, 1.7, 45.0]], 4],
        'Transfer digitale' => [[['0', 0, 1.4, 30.0]], 4],
        'Ricamo' => [[['1', 1, 1.6, 40.0], ['2', 2, 2.2, 60.0]], 6],
        'Tampografia' => [[['1', 1, 0.8, 20.0], ['2', 2, 1.4, 35.0]], 3],
        'Incisione laser' => [[['1', 1, 1.1, 20.0]], 4],
        'Sublimazione' => [[['0', 0, 1.3, 25.0]], 4],
    ];

    /** Unit print cost tiers for a 1-colour reference technique: from quantity => cost. */
    public const PRINT_TIERS = [1 => 1.20, 50 => 0.85, 100 => 0.62, 250 => 0.48, 500 => 0.39, 1000 => 0.32];

    /** Quantity tiers of the product prices. */
    public const PRODUCT_TIERS = [1, 100, 250, 500];

    /**
     * Products. Keys: name, category (leaf), shape, brand, material, dimensions,
     * colors, sizes (bool), cost, min (first tier), positions (position => techniques),
     * flags (bestseller/promo/green/sale), age (months), pack (pieces, l, w, h, kg), description.
     *
     * @return list<array<string, mixed>>
     */
    public static function products(): array
    {
        return [
            ['name' => 'T-shirt classica in cotone', 'category' => 'T-shirt', 'shape' => 'shirt', 'brand' => 'Basic Line', 'material' => '100% cotone, 150 g/m²', 'dimensions' => 'XS-XXL', 'colors' => ['Bianco', 'Nero', 'Blu navy', 'Rosso'], 'sizes' => true, 'cost' => 2.10, 'min' => 1, 'positions' => ['Fronte' => ['Serigrafia tessile', 'Transfer digitale'], 'Retro' => ['Serigrafia tessile']], 'flags' => ['bestseller'], 'age' => 6, 'pack' => [100, 60, 40, 30, 16.0], 'description' => 'La t-shirt girocollo per eventi, staff e promozioni: tessuto in cotone pettinato, taglio classico, ampia scelta di colori. Personalizzabile fronte e retro.'],
            ['name' => 'T-shirt in cotone organico', 'category' => 'T-shirt', 'shape' => 'shirt', 'brand' => 'Verde Lab', 'material' => '100% cotone organico, 160 g/m²', 'dimensions' => 'XS-XXL', 'colors' => ['Naturale', 'Verde', 'Grigio'], 'sizes' => true, 'cost' => 3.40, 'min' => 1, 'positions' => ['Fronte' => ['Transfer digitale', 'Serigrafia tessile']], 'flags' => ['green'], 'age' => 0, 'pack' => [50, 50, 40, 30, 8.5], 'description' => 'Cotone organico certificato e colori naturali per una comunicazione sostenibile. Vestibilità regolare, etichetta rimovibile.'],
            ['name' => 'Polo piqué', 'category' => 'Polo', 'shape' => 'shirt', 'brand' => 'Basic Line', 'material' => '100% cotone piqué, 200 g/m²', 'dimensions' => 'S-XXL', 'colors' => ['Bianco', 'Blu navy', 'Nero'], 'sizes' => true, 'cost' => 6.90, 'min' => 1, 'positions' => ['Petto sinistro' => ['Ricamo', 'Serigrafia tessile']], 'flags' => [], 'age' => 4, 'pack' => [50, 60, 40, 30, 11.0], 'description' => 'La polo da lavoro e da rappresentanza: colletto a costine, tre bottoni in tinta, spacchetti laterali. Il ricamo sul petto la rende un capo di divisa.'],
            ['name' => 'Felpa con cappuccio', 'category' => 'Felpe', 'shape' => 'shirt', 'brand' => 'Basic Line', 'material' => '80% cotone, 20% poliestere, 280 g/m²', 'dimensions' => 'S-XXL', 'colors' => ['Grigio', 'Nero', 'Bordeaux'], 'sizes' => true, 'cost' => 12.50, 'min' => 1, 'positions' => ['Fronte' => ['Serigrafia tessile', 'Ricamo'], 'Retro' => ['Serigrafia tessile']], 'flags' => ['bestseller'], 'age' => 8, 'pack' => [20, 60, 40, 40, 9.0], 'description' => 'Felpa con cappuccio foderato, tasca a marsupio e polsini elastici. Interno felpato, ideale per team, scuole e associazioni.'],
            ['name' => 'Cappellino 5 pannelli', 'category' => 'Cappellini', 'shape' => 'cap', 'brand' => 'Basic Line', 'material' => '100% cotone twill', 'dimensions' => 'Taglia unica, chiusura regolabile', 'colors' => ['Nero', 'Blu navy', 'Rosso', 'Bianco'], 'sizes' => false, 'cost' => 1.95, 'min' => 25, 'positions' => ['Fronte cappellino' => ['Ricamo', 'Transfer digitale']], 'flags' => ['promo'], 'age' => 12, 'pack' => [200, 60, 40, 40, 14.0], 'description' => 'Cappellino con visiera precurvata e chiusura in velcro. Il ricamo frontale è la personalizzazione più richiesta per eventi sportivi e squadre.'],
            ['name' => 'Shopper in cotone', 'category' => 'Shopper', 'shape' => 'bag', 'brand' => 'Verde Lab', 'material' => '100% cotone, 140 g/m²', 'dimensions' => '38x42 cm, manici 70 cm', 'colors' => ['Naturale', 'Nero', 'Verde'], 'sizes' => false, 'cost' => 0.95, 'min' => 50, 'positions' => ['Fronte' => ['Serigrafia', 'Transfer digitale']], 'flags' => ['green', 'bestseller'], 'age' => 10, 'pack' => [250, 50, 40, 30, 12.0], 'description' => 'La shopper più venduta: manici lunghi da spalla, cotone naturale non sbiancato, ampia area di stampa. Riutilizzabile e lavabile.'],
            ['name' => 'Shopper in juta', 'category' => 'Shopper', 'shape' => 'bag', 'brand' => 'Verde Lab', 'material' => 'Juta laminata, manici in cotone', 'dimensions' => '40x30x15 cm', 'colors' => ['Naturale'], 'sizes' => false, 'cost' => 2.30, 'min' => 50, 'positions' => ['Fronte' => ['Serigrafia']], 'flags' => ['green'], 'age' => 2, 'pack' => [100, 60, 40, 40, 20.0], 'description' => 'Borsa in fibra naturale con fondo rigido e interno laminato: robusta, capiente, perfetta per la spesa e le fiere.'],
            ['name' => 'Zaino urbano', 'category' => 'Zaini e sacche', 'shape' => 'bag', 'brand' => 'Nordic Line', 'material' => 'Poliestere 600D riciclato', 'dimensions' => '30x45x14 cm, 18 litri', 'colors' => ['Nero', 'Grigio', 'Blu navy'], 'sizes' => false, 'cost' => 9.80, 'min' => 10, 'positions' => ['Fronte' => ['Ricamo', 'Transfer digitale']], 'flags' => ['bestseller'], 'age' => 5, 'pack' => [20, 60, 50, 40, 13.0], 'description' => 'Zaino con scomparto imbottito per laptop da 15", tasca frontale con zip e spallacci ergonomici. Tessuto riciclato da bottiglie PET.'],
            ['name' => 'Sacca con coulisse', 'category' => 'Zaini e sacche', 'shape' => 'bag', 'brand' => 'Basic Line', 'material' => 'Poliestere 210T', 'dimensions' => '34x44 cm', 'colors' => ['Bianco', 'Nero', 'Rosso', 'Giallo'], 'sizes' => false, 'cost' => 0.75, 'min' => 50, 'positions' => ['Fronte' => ['Serigrafia', 'Transfer digitale']], 'flags' => ['promo'], 'age' => 14, 'pack' => [500, 50, 40, 30, 15.0], 'description' => 'La sacca da palestra economica per eventi e scuole: cordini in tinta, angoli rinforzati, stampa a colori vivaci.'],
            ['name' => 'Borraccia termica 500 ml', 'category' => 'Borracce', 'shape' => 'bottle', 'brand' => 'Nordic Line', 'material' => 'Acciaio inox 18/8 a doppia parete', 'dimensions' => 'Ø 7 x 26 cm', 'colors' => ['Argento', 'Nero', 'Azzurro', 'Verde'], 'sizes' => false, 'cost' => 6.20, 'min' => 10, 'positions' => ['Corpo' => ['Incisione laser', 'Serigrafia']], 'flags' => ['bestseller'], 'age' => 7, 'pack' => [24, 40, 30, 30, 9.5], 'description' => 'Mantiene le bevande fredde per 24 ore e calde per 12. L\'incisione laser sul corpo resiste per sempre al lavaggio.'],
            ['name' => 'Borraccia in tritan 750 ml', 'category' => 'Borracce', 'shape' => 'bottle', 'brand' => 'Verde Lab', 'material' => 'Tritan senza BPA', 'dimensions' => 'Ø 7,5 x 25 cm', 'colors' => ['Azzurro', 'Verde', 'Arancio'], 'sizes' => false, 'cost' => 2.80, 'min' => 25, 'positions' => ['Corpo' => ['Tampografia', 'Serigrafia']], 'flags' => ['green'], 'age' => 1, 'pack' => [48, 60, 40, 30, 8.0], 'description' => 'Leggera, trasparente e infrangibile, con tappo a vite e moschettone. Adatta a sport, ufficio e scuola.'],
            ['name' => 'Tazza in ceramica', 'category' => 'Tazze e bicchieri', 'shape' => 'mug', 'brand' => 'Basic Line', 'material' => 'Ceramica smaltata', 'dimensions' => '320 ml', 'colors' => ['Bianco', 'Nero', 'Rosso'], 'sizes' => false, 'cost' => 1.60, 'min' => 36, 'positions' => ['Corpo' => ['Sublimazione', 'Tampografia']], 'flags' => [], 'age' => 9, 'pack' => [36, 40, 30, 30, 14.0], 'description' => 'La classica mug da ufficio, lavabile in lavastoviglie. Con la sublimazione la stampa avvolge tutto il corpo, a colori.'],
            ['name' => 'Bicchiere da viaggio in bambù', 'category' => 'Tazze e bicchieri', 'shape' => 'mug', 'brand' => 'Verde Lab', 'material' => 'Fibra di bambù e PP, coperchio in silicone', 'dimensions' => '400 ml', 'colors' => ['Legno'], 'sizes' => false, 'cost' => 3.90, 'min' => 25, 'positions' => ['Corpo' => ['Incisione laser']], 'flags' => ['green'], 'age' => 3, 'pack' => [50, 50, 40, 30, 10.0], 'description' => 'Bicchiere riutilizzabile con fascia antiscottatura e coperchio a tenuta. Un regalo sostenibile per clienti e dipendenti.'],
            ['name' => 'Penna a sfera in plastica riciclata', 'category' => 'Penne e matite', 'shape' => 'pen', 'brand' => 'Verde Lab', 'material' => 'ABS riciclato', 'dimensions' => '14 cm', 'colors' => ['Bianco', 'Blu navy', 'Verde', 'Nero'], 'sizes' => false, 'cost' => 0.28, 'min' => 100, 'positions' => ['Fusto' => ['Tampografia']], 'flags' => ['green', 'bestseller'], 'age' => 11, 'pack' => [1000, 40, 30, 20, 10.0], 'description' => 'Penna a scatto con refill blu, fusto in plastica riciclata al 100%. Il gadget più diffuso per fiere e congressi.'],
            ['name' => 'Penna in metallo', 'category' => 'Penne e matite', 'shape' => 'pen', 'brand' => 'Nordic Line', 'material' => 'Alluminio anodizzato', 'dimensions' => '14 cm', 'colors' => ['Argento', 'Nero'], 'sizes' => false, 'cost' => 1.10, 'min' => 50, 'positions' => ['Fusto' => ['Incisione laser', 'Tampografia']], 'flags' => ['promo'], 'age' => 13, 'pack' => [500, 40, 30, 20, 12.0], 'description' => 'Penna a torsione in alluminio con finitura satinata. L\'incisione laser rivela il metallo lucido sotto il colore.'],
            ['name' => 'Matita in legno', 'category' => 'Penne e matite', 'shape' => 'pen', 'brand' => 'Verde Lab', 'material' => 'Legno certificato, mina HB', 'dimensions' => '19 cm', 'colors' => ['Legno'], 'sizes' => false, 'cost' => 0.15, 'min' => 250, 'positions' => ['Fusto' => ['Tampografia']], 'flags' => ['green'], 'age' => 0, 'pack' => [2000, 40, 30, 20, 9.0], 'description' => 'Matita in legno naturale con gommino. Economica e sostenibile, perfetta per scuole e uffici.'],
            ['name' => 'Taccuino A5 con copertina rigida', 'category' => 'Taccuini', 'shape' => 'book', 'brand' => 'Nordic Line', 'material' => 'Copertina in PU, 96 fogli a righe', 'dimensions' => '14,5x21 cm', 'colors' => ['Nero', 'Blu navy', 'Rosso'], 'sizes' => false, 'cost' => 2.90, 'min' => 25, 'positions' => ['Copertina' => ['Serigrafia', 'Incisione laser']], 'flags' => ['bestseller'], 'age' => 6, 'pack' => [50, 40, 30, 30, 15.0], 'description' => 'Taccuino con elastico, segnalibro in tessuto e tasca interna. La copertina morbida al tatto accoglie stampa o incisione.'],
            ['name' => 'Blocco note in carta riciclata', 'category' => 'Taccuini', 'shape' => 'book', 'brand' => 'Verde Lab', 'material' => 'Carta riciclata, copertina in cartone', 'dimensions' => 'A6, 70 fogli', 'colors' => ['Naturale'], 'sizes' => false, 'cost' => 1.20, 'min' => 50, 'positions' => ['Copertina' => ['Tampografia']], 'flags' => ['green'], 'age' => 2, 'pack' => [100, 40, 30, 30, 12.0], 'description' => 'Blocco tascabile con spirale e penna in cartone inclusa. Materiali riciclati e riciclabili.'],
            ['name' => 'Chiavetta USB 16 GB', 'category' => 'Chiavette USB', 'shape' => 'device', 'brand' => 'Nordic Line', 'material' => 'Alluminio', 'dimensions' => '5,5x1,8x0,8 cm', 'colors' => ['Argento', 'Nero'], 'sizes' => false, 'cost' => 3.30, 'min' => 25, 'positions' => ['Lato' => ['Incisione laser', 'Tampografia']], 'flags' => [], 'age' => 5, 'pack' => [100, 30, 20, 20, 3.0], 'description' => 'Chiavetta girevole in metallo, USB 3.0, con possibilità di precaricare i tuoi file. Confezione singola inclusa.'],
            ['name' => 'Powerbank 5000 mAh', 'category' => 'Powerbank', 'shape' => 'device', 'brand' => 'Nordic Line', 'material' => 'ABS e alluminio', 'dimensions' => '9x6x1,2 cm', 'colors' => ['Nero', 'Bianco'], 'sizes' => false, 'cost' => 7.40, 'min' => 10, 'positions' => ['Fronte' => ['Tampografia', 'Incisione laser']], 'flags' => ['sale'], 'age' => 15, 'pack' => [50, 40, 30, 30, 8.0], 'description' => 'Batteria tascabile con uscita USB-C e USB-A, indicatore di carica a LED. Un regalo utile che resta sulla scrivania.'],
            ['name' => 'Ombrello automatico', 'category' => 'Ombrelli', 'shape' => 'umbrella', 'brand' => 'Nordic Line', 'material' => 'Poliestere 190T, stecche in fibra di vetro', 'dimensions' => 'Ø 105 cm', 'colors' => ['Nero', 'Blu navy', 'Rosso'], 'sizes' => false, 'cost' => 5.60, 'min' => 12, 'positions' => ['Spicchio' => ['Serigrafia']], 'flags' => [], 'age' => 4, 'pack' => [24, 90, 20, 20, 9.0], 'description' => 'Ombrello da passeggio con apertura automatica e manico in legno. Antivento, con logo stampato su uno o più spicchi.'],
            ['name' => 'Ombrello pieghevole', 'category' => 'Ombrelli', 'shape' => 'umbrella', 'brand' => 'Basic Line', 'material' => 'Poliestere 190T', 'dimensions' => 'Ø 95 cm, chiuso 24 cm', 'colors' => ['Nero', 'Grigio'], 'sizes' => false, 'cost' => 4.10, 'min' => 12, 'positions' => ['Spicchio' => ['Serigrafia']], 'flags' => ['sale'], 'age' => 16, 'pack' => [48, 50, 30, 30, 12.0], 'description' => 'Mini ombrello a tre sezioni con custodia. Entra in borsa e nel cassetto dell\'auto.'],
            ['name' => 'Telo mare in microfibra', 'category' => 'Teli mare', 'shape' => 'shirt', 'brand' => 'Basic Line', 'material' => 'Microfibra 250 g/m²', 'dimensions' => '90x160 cm', 'colors' => ['Azzurro', 'Arancio', 'Verde'], 'sizes' => false, 'cost' => 4.80, 'min' => 25, 'positions' => ['Angolo' => ['Sublimazione']], 'flags' => ['promo'], 'age' => 3, 'pack' => [50, 60, 40, 40, 13.0], 'description' => 'Asciuga in fretta e occupa poco spazio. Con la sublimazione il telo diventa tutto un\'immagine.'],
            ['name' => 'Telo mare in cotone', 'category' => 'Teli mare', 'shape' => 'shirt', 'brand' => 'Nordic Line', 'material' => '100% cotone, 400 g/m²', 'dimensions' => '100x180 cm', 'colors' => ['Bianco/Nero', 'Azzurro'], 'sizes' => false, 'cost' => 7.30, 'min' => 10, 'positions' => ['Angolo' => ['Ricamo']], 'flags' => [], 'age' => 7, 'pack' => [20, 60, 40, 40, 15.0], 'description' => 'Spugna di cotone pesante con bordo a righe. Il ricamo sull\'angolo lo rende un regalo elegante per resort e club.'],
        ];
    }
}
