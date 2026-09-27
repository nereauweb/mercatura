{{-- @mercatura-view frontend.components.home.tiles @version 1 --}}
{{-- Home tiles (Contenuti → Tile home): photo blocks with a title and a link, independent of the categories.
     $tiles: ContentHomeTile rows. Photos: 16:9 box, WebP 420/840 conversions from App\Support\HomeTileImages. --}}
@props(['tiles' => [], 'title' => null])
@if(count($tiles))
<section {{ $attributes->merge(['class' => 'my-10 text-center']) }}>
    @if($title)<h2 class="text-2xl font-bold uppercase text-primary">{{ $title }}</h2>@endif
    <ul class="mt-6 grid gap-6 text-left sm:grid-cols-2 md:grid-cols-3">
        @foreach($tiles as $tile)
            @php $photo = \App\Support\HomeTileImages::banner($tile->image); @endphp
            <li>
                <a href="{{ $tile->link ?: '#' }}" class="group block h-full">
                    <div class="relative aspect-video overflow-hidden rounded-card border border-border-muted bg-surface-muted">
                        @if($photo['src'] !== '')
                            <img src="{{ $photo['src'] }}"@if($photo['srcset'] !== '') srcset="{{ $photo['srcset'] }}" sizes="(min-width: 768px) 33vw, (min-width: 640px) 50vw, 100vw"@endif alt="" width="420" height="235" loading="lazy" decoding="async" class="h-full w-full object-cover transition group-hover:scale-105">
                        @endif
                        <h3 class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 pb-4 pt-10 text-lg font-bold text-on-dark">{{ $tile->title }}</h3>
                    </div>
                    @if(filled($tile->text))<p class="mt-3 text-sm text-text-muted">{{ $tile->text }}</p>@endif
                </a>
            </li>
        @endforeach
    </ul>
</section>
@endif
