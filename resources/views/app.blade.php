<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $seo = App\Support\SeoMeta::forComponent($page['component'] ?? '', $page['props'] ?? []);
        @endphp
        @include('partials.seo', ['seo' => $seo])

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @php
            $faviconPath = App\Models\Setting::getValue('app_favicon');
            $faviconUrl = $faviconPath
                ? Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath)
                : null;
        @endphp
        @if($faviconUrl)
            <link rel="icon" href="{{ $faviconUrl }}" type="image/webp">
            <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        {{-- Judul cadangan untuk perayap yang tidak menjalankan JS. React
             menggantinya lewat <Head> begitu bundelnya jalan. --}}
        <x-inertia::head>
            <title>{{ $seo['title'] }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        {{--
            Penjaga halaman putih.

            Kalau bundelnya gagal — sintaks yang tidak dikenal peramban lama,
            unduhan yang putus, JavaScript dimatikan — React tidak pernah
            memasang apa pun dan yang dilihat orang layar putih polos. Tidak ada
            pesan, tidak ada jejak di server, dan yang melaporkannya cuma bisa
            bilang "tidak bisa dibuka". Itu sudah terjadi di halaman gerbang
            absensi, yang dibuka di ponsel apa pun yang ada di sekolah.

            Ditulis ES5 MURNI dengan sengaja: var, function, tanpa arrow, tanpa
            template literal. Penjaga yang ikut gagal di-parse tidak menolong
            siapa pun.

            Delapan detik, bukan dua: jaringan sekolah lambat, dan menuduh
            peramban terlalu tua padahal berkasnya masih mengunduh justru
            mengirim orang memperbaiki hal yang salah.
        --}}
        <script nomodule>
            document.documentElement.setAttribute('data-tanpa-modul', '1');
        </script>
        <script>
            (function () {
                /*
                    Titik pasang React, BUKAN `[data-page]`.

                    Inertia v3 memasang dua elemen: satu pembawa data dengan
                    atribut `data-page` yang memang selalu kosong, dan satu
                    `#app` tempat React benar-benar merender. Versi pertama
                    penjaga ini memeriksa yang pertama, jadi ia menyala di
                    halaman yang baik-baik saja — lalu React menimpanya lagi
                    pada render berikutnya dan yang terlihat cuma kedipan
                    peringatan palsu. Harness CDP yang menangkapnya.
                */
                function titikPasang() {
                    return document.getElementById('app') || document.querySelector('[data-page]');
                }

                function tampilkan(sebab) {
                    var akar = titikPasang();
                    if (!akar || akar.getAttribute('data-sudah-diperingatkan')) { return; }
                    akar.setAttribute('data-sudah-diperingatkan', '1');

                    akar.innerHTML =
                        '<div style="font-family:system-ui,-apple-system,Segoe UI,sans-serif;' +
                        'max-width:30rem;margin:15vh auto;padding:0 1.5rem;text-align:center;color:#1e293b">' +
                        '<div style="font-size:2.5rem;line-height:1">&#9888;</div>' +
                        '<h1 style="font-size:1.25rem;font-weight:700;margin:.75rem 0 .25rem">Halaman tidak bisa dimuat</h1>' +
                        '<p style="color:#64748b;font-size:.9rem;margin:0 0 1rem">' + sebab + '</p>' +
                        '<p style="color:#94a3b8;font-size:.8rem;margin:0">' +
                        'Buka lewat Google Chrome atau Safari versi terbaru. ' +
                        'Kalau tautan ini dibuka dari dalam WhatsApp, pilih ' +
                        '&quot;Buka di peramban&quot; lebih dulu.</p></div>';
                }

                if (document.documentElement.getAttribute('data-tanpa-modul')) {
                    tampilkan('Peramban ini terlalu lama untuk menjalankan aplikasinya.');
                    return;
                }

                setTimeout(function () {
                    var akar = titikPasang();
                    if (!akar) { return; }

                    // Dua syarat, bukan satu. Anak nol saja pernah salah
                    // menuduh; teks halaman yang juga kosong memastikan yang
                    // dilihat orang memang benar-benar layar putih.
                    var teks = (document.body.innerText || '').replace(/\s/g, '');

                    if (akar.children.length === 0 && teks.length < 20) {
                        tampilkan('Aplikasinya gagal dijalankan di peramban ini.');
                    }
                }, 8000);
            })();
        </script>
    </body>
</html>
