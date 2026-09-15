<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\BlogArticle;
use App\Models\BlogTag;
use App\Models\Category;
use App\Models\ContentHomeSlide;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** CMS pages (including the legal pages the checkout links to), home slides and two blog posts. */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPages();
        $this->seedSlides();
        $this->seedBlog();
    }

    private function seedPages(): void
    {
        $privacy = (string) config('mercatura.legal_pages.privacy', 'privacy-policy');
        $terms = (string) config('mercatura.legal_pages.terms', 'condizioni-di-vendita');
        $cover = 'demo-chi-siamo.jpg';
        Storage::disk('public')->put('pages/img/'.$cover, Images::banner(1200, 288, '1E3A8A', '60A5FA'));

        Page::query()->create([
            'title' => 'Chi siamo', 'slug' => 'chi-siamo', 'active' => 1, 'navbar' => 1, 'position' => 1, 'products' => 0, 'cover' => $cover,
            'text' => '<p>Questo negozio è l\'istanza dimostrativa del core Mercatura: catalogo, configuratore di stampa, preventivi e checkout funzionano su dati fittizi.</p><p>Ogni installazione reale sostituisce questi contenuti con i propri, senza toccare il core: identità in <code>config/brand.php</code>, copy nei file di lingua, viste sovrascritte dallo skin.</p>',
            'seo_title' => 'Chi siamo', 'seo_description' => 'L\'istanza dimostrativa del core Mercatura.',
        ]);
        Page::query()->create([
            'title' => 'Privacy policy', 'slug' => $privacy, 'active' => 1, 'navbar' => 0, 'position' => 10, 'products' => 0,
            'text' => '<p>Testo dimostrativo. L\'installazione pubblica qui la propria informativa ai sensi del Regolamento (UE) 2016/679: titolare del trattamento, finalità, base giuridica, conservazione, diritti dell\'interessato.</p>',
            'seo_title' => 'Privacy policy',
        ]);
        Page::query()->create([
            'title' => 'Condizioni di vendita', 'slug' => $terms, 'active' => 1, 'navbar' => 0, 'position' => 11, 'products' => 0,
            'text' => '<p>Testo dimostrativo. L\'installazione pubblica qui le proprie condizioni generali: ordini e preventivi, prezzi e IVA, bozze di stampa, tempi di produzione e consegna, pagamenti, resi e garanzie.</p>',
            'seo_title' => 'Condizioni di vendita',
        ]);

        $listing = [
            ['Novità', 'novita', 'created_after_value', null, '-1 month', 'Gli ultimi articoli entrati a catalogo.'],
            ['In promozione', 'promo', 'is_promo', null, '1', 'Prezzi speciali per quantità, finché durano le scorte.'],
            ['Gadget green', 'gadget-green', 'is_green', null, '1', 'Materiali riciclati, naturali o certificati: la selezione sostenibile.'],
            ['Saldi', 'saldi', 'is_sale', null, '1', 'Articoli in fine serie a prezzo ridotto.'],
        ];
        $position = 2;
        foreach ($listing as [$title, $slug, $type, $target, $value, $intro]) {
            $page = Page::query()->create([
                'title' => $title, 'slug' => $slug, 'active' => 1, 'navbar' => $slug === 'novita' || $slug === 'gadget-green' ? 1 : 0,
                'position' => $position++, 'products' => 1, 'text' => '<p>'.$intro.'</p>', 'seo_title' => $title, 'seo_description' => $intro,
            ]);
            $page->contents()->create(['filter_type' => $type, 'filter_target' => $target, 'filter_value' => $value]);
        }

        $shopper = Category::query()->where('name', 'Shopper')->first();
        if ($shopper) {
            $page = Page::query()->create([
                'title' => 'Shopper personalizzate', 'slug' => 'shopper-personalizzate', 'active' => 1, 'navbar' => 0, 'position' => $position,
                'products' => 1, 'text' => '<p>Borse in cotone e juta con il tuo logo, da 50 pezzi.</p>', 'seo_title' => 'Shopper personalizzate',
            ]);
            $page->contents()->create(['filter_type' => 'category_id', 'filter_target' => (string) $shopper->id, 'filter_value' => null]);
        }
    }

    private function seedSlides(): void
    {
        $slides = [
            ['demo-slide-1.jpg', '1E3A8A', '60A5FA', 'Gadget personalizzati per la tua azienda', 'Catalogo demo', 'Scegli l\'articolo, configura la stampa, ricevi il preventivo.', 'Scopri il catalogo', '/prodotti'],
            ['demo-slide-2.jpg', '14532D', '86EFAC', 'La linea green', 'Sostenibilità', 'Cotone organico, plastica riciclata, bambù e legno certificato.', 'Vedi la selezione', '/contenuti/gadget-green'],
            ['demo-slide-3.jpg', '7C2D12', 'FDBA74', 'Preventivo in 24 ore', 'Servizio', 'Raccontaci l\'evento: ti proponiamo il gadget giusto e la bozza di stampa.', 'Contattaci', '/contattaci'],
        ];
        $position = 1;
        foreach ($slides as [$file, $from, $to, $title, $subtitle, $text, $cta, $link]) {
            Storage::disk('public')->put('home_slides/'.$file, Images::banner(1200, 420, $from, $to));
            ContentHomeSlide::query()->create([
                'position' => $position++, 'background_color' => '#'.$from, 'background_image' => $file,
                'title_color' => '#ffffff', 'title_text' => $title, 'subtitle_color' => '#ffffff', 'subtitle_text' => $subtitle,
                'text_color' => '#ffffff', 'text' => $text, 'cta_text' => $cta, 'cta_link' => $link,
            ]);
        }
    }

    private function seedBlog(): void
    {
        $tags = [];
        foreach (['Stampa', 'Sostenibilità'] as $position => $name) {
            $tags[$name] = BlogTag::query()->create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'position' => $position]);
        }
        $articles = [
            ['Come scegliere la tecnica di stampa', 'come-scegliere-la-tecnica-di-stampa', 'Serigrafia, tampografia, transfer, ricamo o laser: quale conviene per il tuo gadget.', '<p>La tecnica dipende dal materiale, dal numero di colori del logo e dalla quantità. La serigrafia è economica sui grandi numeri a uno o due colori; il transfer digitale riproduce fotografie e sfumature; il ricamo dà valore ai capi; l\'incisione laser è permanente su metallo e legno.</p><p>Nel configuratore di ogni prodotto trovi le tecniche disponibili per posizione, con i prezzi per quantità e i costi di impianto.</p>', ['Stampa'], '1E3A8A', '60A5FA'],
            ['Gadget sostenibili: cosa guardare', 'gadget-sostenibili-cosa-guardare', 'Materiali, certificazioni e durata: i criteri per un regalo aziendale davvero green.', '<p>Un gadget è sostenibile se dura e se il suo materiale ha un ciclo di vita corto o riciclato: cotone organico, plastica rigenerata, bambù, carta riciclata. Diffida delle etichette generiche e chiedi le certificazioni.</p><p>La nostra selezione green raccoglie gli articoli che rispettano questi criteri.</p>', ['Sostenibilità'], '14532D', '86EFAC'],
        ];
        foreach ($articles as $position => [$title, $slug, $excerpt, $text, $tagNames, $from, $to]) {
            $cover = 'demo-'.$slug.'.jpg';
            Storage::disk('public')->put('blog/img/'.$cover, Images::banner(1200, 600, $from, $to));
            $article = BlogArticle::query()->create([
                'title' => $title, 'slug' => $slug, 'excerpt' => $excerpt, 'text' => $text, 'cover' => $cover,
                'active' => 1, 'navbar' => 0, 'position' => $position, 'seo_title' => $title, 'seo_description' => $excerpt,
            ]);
            $article->tags()->attach(array_map(fn ($name) => $tags[$name]->id, $tagNames));
        }
    }
}
