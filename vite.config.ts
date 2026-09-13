import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    /*
     * Target diturunkan dari bawaan Vite, dan ini bukan pilihan gaya.
     *
     * Bawaan `baseline-widely-available` berarti chrome111 / safari16.4 /
     * ios16.4. Peramban yang lebih tua tidak "berjalan lebih lambat" — ia
     * GAGAL MEM-PARSE berkasnya dan menampilkan halaman putih tanpa satu pun
     * pesan, karena galat sintaks terjadi sebelum ada kode yang sempat jalan.
     *
     * Yang membuat ini penting: halaman gerbang absensi dibuka di ponsel
     * apa pun yang ada di sekolah, termasuk iPhone lama dan WebView Android
     * yang tidak pernah diperbarui. Operator melaporkannya sebagai "tidak
     * bisa dibuka", dan dari sisi server tidak ada jejak apa-apa.
     *
     * chrome87 (2020) dan safari14 (iOS 14) menutup hampir semua perangkat
     * yang masih dipakai. Biayanya beberapa kilobita.
     *
     * Ini hanya mengatur SINTAKS, bukan API. Yang dipakai halaman scan —
     * fetch, AudioContext, speechSynthesis, mediaDevices — semuanya sudah ada
     * jauh sebelum itu.
     */
    build: {
        target: ['chrome87', 'edge88', 'firefox78', 'safari14'],
    },
    // react-rnd (and some CJS deps) reference process.env at runtime; shim it for the browser.
    define: {
        'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV ?? 'production'),
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
