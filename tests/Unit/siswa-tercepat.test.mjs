import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const { outputText } = ts.transpileModule(
    readFileSync(new URL('../../resources/js/components/dashboard/fastest-students-card.tsx', import.meta.url), 'utf8'),
    { compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX } },
);
const exports = {};
const lewat = ({ children }) => React.createElement('div', null, children);
new Function('require', 'exports', outputText)((name) => {
    if (name === 'lucide-react') return { Timer: () => null };
    if (name === '@/components/ui/tabs') {
        return {
            Tabs: lewat,
            TabsList: lewat,
            TabsTrigger: ({ children }) => React.createElement('button', null, children),
            TabsContent: ({ value, children }) => (value === 'pagi' ? React.createElement('div', null, children) : null),
        };
    }
    if (name.startsWith('@/components/ui/')) {
        return new Proxy({}, { get: (_, key) => (key === '__esModule' ? false : lewat) });
    }
    return require(name);
}, exports);
const { FastestStudentsCard } = exports;
const tampil = (data) => renderToStaticMarkup(React.createElement(FastestStudentsCard, { data }));

test('menampilkan peringkat absen pagi periode 1 bulan secara bawaan', () => {
    const html = tampil([
        {
            kunci: 'pagi',
            label: 'Absen pagi',
            periode: {
                1: [{ peringkat: 1, id: 'a', nama: 'ANI', kelas: 'VIII A', rataRata: '06.15', hari: 18, inisial: 'A' }],
                3: [{ peringkat: 1, id: 'b', nama: 'BUDI', kelas: null, rataRata: '06.05', hari: 50, inisial: 'B' }],
            },
        },
        { kunci: 'dhuha', label: 'Sholat Dhuha', periode: { 1: [] } },
    ]);

    assert.match(html, /ANI/);
    assert.match(html, /06\.15/);
    assert.match(html, /18 hari hadir/);
    assert.doesNotMatch(html, /BUDI/);
    assert.match(html, /Sholat Dhuha/);
});

test('periode tanpa cukup data memberi penjelasan, bukan daftar kosong', () => {
    assert.match(tampil([{ kunci: 'pagi', label: 'Absen pagi', periode: { 1: [] } }]), /minimal separuh hari aktif/);
});
