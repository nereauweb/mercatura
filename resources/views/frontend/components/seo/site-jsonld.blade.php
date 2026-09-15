{{-- @mercatura-view frontend.components.seo.site-jsonld @version 1 --}}
{{-- Organization and WebSite structured data from config/brand.php, on every page. --}}
@php
    $brand = config('brand');
    $base = rtrim(url('/'), '/');
    $organization = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => $brand['legal_name'] ?: $brand['name'],
        'url' => $base.'/',
        'logo' => $brand['logo'] ? $base.$brand['logo'] : null,
        'email' => $brand['contact']['email'] ?: null,
        'telephone' => $brand['contact']['phone'] ?: null,
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $brand['contact']['address']['street'] ?: null,
            'postalCode' => $brand['contact']['address']['zip'] ?: null,
            'addressLocality' => $brand['contact']['address']['city'] ?: null,
            'addressRegion' => $brand['contact']['address']['province'] ?: null,
            'addressCountry' => $brand['contact']['address']['country'] ?: null,
        ]),
        'sameAs' => array_values(array_filter($brand['social'] ?? [])) ?: null,
    ], fn ($v) => $v !== null && $v !== [] && $v !== ['@type' => 'PostalAddress']);
    $website = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $brand['name'],
        'url' => $base.'/',
    ];
@endphp
<script type="application/ld+json">{!! json_encode($organization, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode($website, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
