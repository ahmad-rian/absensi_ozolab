import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

const sumber = readFileSync(new URL('../../resources/js/components/scanner/public-scan-console.tsx', import.meta.url), 'utf8');

/*
 * html5-qrcode membaca `useBarCodeDetectorIfSupported` dan `formatsToSupport`
 * dari KONSTRUKTOR. Dioper ke `start()`, keduanya diabaikan tanpa galat dan
 * semua ponsel jatuh ke ZXing JavaScript yang lambat — sebab laporan "QR kurang
 * responsif dibanding QRIS".
 */
test('detektor bawaan dan format QR dioper ke konstruktor, bukan ke start()', () => {
    const konfigurasi = sumber.match(/const konfigurasi: Html5QrcodeFullConfig = \{([\s\S]*?)\};/);

    assert.ok(konfigurasi, 'objek konfigurasi konstruktor harus ada');
    assert.match(konfigurasi[1], /useBarCodeDetectorIfSupported: true/);
    assert.match(konfigurasi[1], /formatsToSupport: \[formatQr\]/);
    assert.match(sumber, /new Pustaka\(readerId, konfigurasi\)/);
    assert.doesNotMatch(sumber, /experimentalFeatures: \{ useBarCodeDetectorIfSupported/);
});

test('kamera meminta resolusi tinggi dan autofokus kontinu', () => {
    assert.match(sumber, /width: \{ ideal: 1280 \}/);
    assert.match(sumber, /await fokusKontinu\(scanner\)/);
});

test('QR terbaca langsung diberi bunyi sebelum server menjawab', () => {
    const kirim = sumber.indexOf('let res = await kirim(');
    const tik = sumber.indexOf('playReadTick();');

    assert.ok(tik > 0 && tik < kirim, 'playReadTick dipanggil sebelum request dikirim');
});
