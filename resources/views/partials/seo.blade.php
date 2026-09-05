{{--
    Meta pencarian yang dirender server.

    Perayap AI umumnya tidak menjalankan JavaScript, jadi <Head> milik React
    tidak pernah mereka lihat. Blok ini satu-satunya yang terbaca oleh mereka —
    dan <link rel="canonical"> di dalamnya yang memberi tahu Google host mana
    yang sah ketika isi yang sama pernah tersaji di host lain.
--}}
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('seo.site_name') }}">
<meta property="og:locale" content="{{ config('seo.locale') }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">

@foreach ($seo['jsonLd'] as $skema)
    <script type="application/ld+json">{!! json_encode($skema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
