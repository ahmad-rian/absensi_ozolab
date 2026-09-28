import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const { outputText } = ts.transpileModule(readFileSync(new URL('../../resources/js/pages/kartu-bebas/layouts/editor.tsx', import.meta.url), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
});
const exports = {};
new Function('require', 'exports', outputText)((name) => name.startsWith('@/') || ['@inertiajs/react', 'lucide-react', 'react-rnd'].includes(name) ? {} : require(name), exports);
const { syncElements } = exports;
const field = { key: 'porsi', label: 'Nomor Porsi', type: 'text' };
const qr = { type: 'qr', source: 'porsi', x: 30, y: 4, w: 18, h: 18, enabled: true };
const photo = { type: 'photo', standalone: true, source: '__photo', x: 3, y: 5, w: 24, h: 32, enabled: true };

test('reopening a layout keeps QR settings and standalone photo placement', () => {
    const next = syncElements([field], { __qr: qr, __photo: photo });
    assert.deepEqual(next.__qr, qr);
    assert.deepEqual(next.__photo, photo);
    assert.equal(next.porsi.type, 'field');
});

test('switching datasets clears an unavailable QR source without dropping its position or photo', () => {
    const next = syncElements([{ key: 'nama', label: 'Nama', type: 'text' }], { __qr: qr, __photo: photo, old: { type: 'field', source: 'old' } });
    assert.deepEqual(next.__qr, { ...qr, source: '' });
    assert.deepEqual(next.__photo, photo);
    assert.equal(next.old, undefined);
});

test('a photo field cannot become the QR source', () => {
    assert.equal(syncElements([{ ...field, type: 'photo' }], { __qr: qr }).__qr.source, '');
});


test('changing orientation keeps photo and QR inside the card', () => {
    const next = exports.changeOrientation({ orientation: 'landscape', elements: {
        qr: { ...qr, x: 67, y: 3 }, photo: { ...photo, x: 60, y: 40 },
    } }, 'portrait');
    for (const element of Object.values(next.elements)) {
        assert.ok(element.x >= 0 && element.x + element.w <= 54);
        assert.ok(element.y >= 0 && element.y + element.h <= 85.6);
    }
    assert.equal(next.elements.qr.source, 'porsi');
});
