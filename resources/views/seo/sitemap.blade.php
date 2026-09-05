{{-- Deklarasi <?xml ...?> sengaja TIDAK di sini: Blade menganggapnya tag
     pembuka PHP dan berhenti mengompilasi sisa barisnya. SeoController yang
     menempelkannya di depan. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($halaman as $url)
    <url>
        <loc>{{ $url }}</loc>
        <changefreq>weekly</changefreq>
    </url>
@endforeach
</urlset>
