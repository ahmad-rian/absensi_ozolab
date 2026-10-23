import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const { outputText } = ts.transpileModule(readFileSync(new URL('../../resources/js/components/scanner/pilih-kamera.ts', import.meta.url), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
});
const modul = {};
new Function('exports', outputText)(modul);
const { pilihKamera } = modul;

const kamera = (...label) => label.map((l, i) => ({ id: `k${i}`, label: l }));

test('iPhone Pro: memilih kamera virtual triple yang bisa makro, bukan lensa utama', () => {
    const daftar = kamera('Front Camera', 'Back Camera', 'Back Telephoto Camera', 'Back Ultra Wide Camera', 'Back Dual Wide Camera', 'Back Triple Camera');
    assert.equal(pilihKamera(daftar), 'k5');
});

test('iPhone non-Pro: dual wide didahulukan, ultra wide dan telefoto tidak pernah dipilih', () => {
    assert.equal(pilihKamera(kamera('Front Camera', 'Back Ultra Wide Camera', 'Back Camera', 'Back Dual Wide Camera')), 'k3');
    assert.equal(pilihKamera(kamera('Back Ultra Wide Camera', 'Back Telephoto Camera')), null);
});

test('Android dan PC gerbang tetap seperti sebelumnya', () => {
    assert.equal(pilihKamera(kamera('camera2 1, facing front', 'camera2 0, facing back')), 'k1');
    assert.equal(pilihKamera(kamera('Integrated Camera', 'USB2.0 HD UVC WebCam')), 'k1');
    assert.equal(pilihKamera(kamera('Kamera 1a2b3c')), null);
});
