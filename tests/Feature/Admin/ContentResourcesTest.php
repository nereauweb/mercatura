<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\Reports;
use App\Filament\Pages\Sitemap;
use App\Filament\Resources\Content\BlogArticles\Pages\CreateBlogArticle;
use App\Filament\Resources\Content\HomeSlides\Pages\ManageHomeSlides;
use App\Filament\Resources\Content\LegacyRedirects\LegacyRedirectResource;
use App\Filament\Resources\Content\LegacyRedirects\Pages\ManageLegacyRedirects;
use App\Filament\Resources\Content\Pages\Pages\CreatePage;
use App\Filament\Resources\Content\Pages\Pages\EditPage;
use App\Filament\Resources\System\ImportLogs\Pages\ListImportLogs;
use App\Models\BlogArticle;
use App\Models\BlogTag;
use App\Models\Category;
use App\Models\ContentHomeSlide;
use App\Models\LegacyRedirect;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** v2b.4 stop criterion: pages, slides, blog, redirects, sitemap, reports and import logs from Filament. */
class ContentResourcesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CoreSeeder::class);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'content-admin@example.com', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_pages_render(): void
    {
        foreach ([ManageHomeSlides::class, ManageLegacyRedirects::class, ListImportLogs::class, Sitemap::class, Reports::class] as $page) {
            Livewire::test($page)->assertOk();
        }
    }

    public function test_cms_page_with_product_block_writes_its_filters_and_is_browsable(): void
    {
        $category = Category::query()->create(['name' => 'Cat test', 'slug' => 'cat-test', 'active' => 1]);

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Novità test', 'slug' => 'novita-test', 'active' => true, 'navbar' => true, 'text' => '<p>Testo</p>',
                'products' => true, 'filter_categories' => [$category->id], 'filter_green' => true,
                'filter_attributes' => [['attribute_id' => 17, 'value' => 'penna']], 'filter_created_after' => '2026-01-01 00:00',
            ])
            ->call('create')->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'novita-test')->firstOrFail();
        $types = $page->contents()->pluck('filter_type')->sort()->values()->all();
        $this->assertSame(['attribute_id_value', 'category_id', 'created_after_value', 'is_green'], $types);
        $this->get('/contenuti/novita-test')->assertOk()->assertSee('Novità test');

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->assertSchemaStateSet(['filter_categories' => [$category->id], 'filter_green' => true])
            ->fillForm(['products' => false])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(0, $page->contents()->count());
    }

    public function test_blog_article_with_tags_and_redirect_import(): void
    {
        $tag = BlogTag::query()->create(['name' => 'Stampa test', 'slug' => 'stampa-test', 'position' => 0]);

        Livewire::test(CreateBlogArticle::class)
            ->fillForm(['title' => 'Articolo test', 'slug' => 'articolo-test', 'active' => true, 'excerpt' => 'x', 'text' => '<p>y</p>', 'tags' => [$tag->id]])
            ->call('create')->assertHasNoFormErrors();
        $article = BlogArticle::query()->where('slug', 'articolo-test')->firstOrFail();
        $this->assertSame([$tag->id], $article->tags()->pluck('blog_tags.id')->all());
        $this->get('/blog/articolo-test')->assertOk();

        $csv = tempnam(sys_get_temp_dir(), 'redirects');
        file_put_contents($csv, "from,to,code\n/vecchio.html,/contenuti/chi-siamo,301\n/sparito.pdf,,410\n");
        $this->assertSame(2, LegacyRedirectResource::importCsv((string) $csv));
        $this->assertSame(410, LegacyRedirect::for('/sparito.pdf')?->status_code);
        $this->get('/vecchio.html')->assertRedirect('/contenuti/chi-siamo');
    }

    public function test_home_slides_are_managed_with_a_switch_and_a_phone_image(): void
    {
        ContentHomeSlide::query()->delete();
        Livewire::test(ManageHomeSlides::class)->callAction(TestAction::make('create')->table(), data: [
            'title_text' => '', 'background_image' => UploadedFile::fake()->image('wide.jpg', 1920, 420), 'mobile_image' => UploadedFile::fake()->image('phone.jpg', 768, 420),
            'cta_link' => '/prodotti', 'position' => 1, 'active' => false,
        ])->assertHasNoFormErrors();
        $slide = ContentHomeSlide::query()->latest('id')->firstOrFail();
        $this->assertFalse($slide->active);
        $this->assertNotNull($slide->mobile_image);
        $this->assertStringNotContainsString('/', (string) $slide->background_image, 'bare file name, as the storefront expects');
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertStringNotContainsString('home_slides/'.$slide->background_image, $this->get('/')->assertOk()->getContent(), 'an inactive slide is not shown');

        $slide->update(['active' => true]);
        \Illuminate\Support\Facades\Cache::flush();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('home_slides/'.$slide->background_image, $html);
        $this->assertStringContainsString('<source media="(max-width: 639px)" srcset="/storage/home_slides/'.$slide->mobile_image.'"', $html, 'the phone image is offered under 640 px');
    }
}
