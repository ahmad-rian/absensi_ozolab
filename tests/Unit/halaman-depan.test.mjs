import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import { test } from 'node:test';

const baca = (jalur) => readFileSync(new URL(`../../${jalur}`, import.meta.url), 'utf8');

test('halaman depan tidak memakai smooth-scroll Lenis maupun GSAP', () => {
    const paket = JSON.parse(baca('package.json'));
    const semua = { ...paket.dependencies, ...paket.devDependencies };

    assert.equal(semua.lenis, undefined);
    assert.equal(semua.gsap, undefined);
    assert.doesNotMatch(baca('resources/js/pages/welcome.tsx'), /lenis|gsap/i);
});

test('pemutar Lottie memakai build ringan dan dimuat saat terlihat', () => {
    const komponen = baca('resources/js/components/depan/animasi-lottie.tsx');

    assert.match(komponen, /import\('lottie-web\/build\/player\/lottie_light'\)/);
    assert.match(komponen, /IntersectionObserver/);
    assert.match(komponen, /prefers-reduced-motion/);
    assert.doesNotMatch(baca('resources/js/pages/welcome.tsx'), /lottie-web/);
});

for (const nama of ['scan', 'pesan', 'laporan']) {
    test(`animasi ${nama} adalah Lottie yang valid dan kecil`, () => {
        const jalur = `public/lottie/${nama}.json`;
        const data = JSON.parse(baca(jalur));

        assert.ok(statSync(new URL(`../../${jalur}`, import.meta.url)).size < 10_000);
        assert.ok(data.v && data.fr > 0 && data.op > data.ip);
        assert.ok(data.w > 0 && data.h > 0);
        assert.ok(Array.isArray(data.layers) && data.layers.length > 0);
        assert.ok(data.layers.every((l) => l.ty === 4 && Array.isArray(l.shapes)));
    });
}
