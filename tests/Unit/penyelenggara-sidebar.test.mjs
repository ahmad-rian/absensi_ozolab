import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import ts from 'typescript';

const require = createRequire(import.meta.url);

function loadSidebar() {
    const source = readFileSync(new URL('../../resources/js/components/penyelenggara-sidebar.tsx', import.meta.url), 'utf8');
    const { outputText } = ts.transpileModule(source, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
    });
    const exports = {};
    new Function('require', 'exports', outputText)((name) => {
        if (name.startsWith('@/routes/')) return new Proxy({}, { get: (_, key) => ({ url: () => `/${String(key)}` }) });
        if (name.startsWith('@/') || name === 'lucide-react' || name === '@inertiajs/react') {
            return new Proxy({}, { get: (_, key) => (key === 'default' ? 'stub' : key) });
        }
        return require(name);
    }, exports);

    return exports;
}

const { penyelenggaraItems, visiblePenyelenggaraItems } = loadSidebar();

test('pendamping hanya melihat menu yang izinnya dimiliki', () => {
    const items = visiblePenyelenggaraItems(penyelenggaraItems, ['PENDAMPING'], ['penyelenggara-beranda.access']);

    assert.deepEqual(items.map((i) => i.title), ['Beranda']);
});

test('admin penyelenggara dengan izin tim melihat menu Tim', () => {
    const items = visiblePenyelenggaraItems(penyelenggaraItems, ['ADMIN_PENYELENGGARA'], ['penyelenggara-beranda.access', 'penyelenggara-tim.access']);

    assert.deepEqual(items.map((i) => i.title), ['Beranda', 'Tim']);
});

test('admin dengan izin kategori dan program melihat menunya', () => {
    const items = visiblePenyelenggaraItems(penyelenggaraItems, ['ADMIN_PENYELENGGARA'], ['penyelenggara-beranda.access', 'penyelenggara-kategori.access', 'penyelenggara-program.access']);

    assert.deepEqual(items.map((i) => i.title), ['Beranda', 'Kategori', 'Program']);
});

test('super admin melewati izin', () => {
    const items = visiblePenyelenggaraItems(penyelenggaraItems, ['SUPER_ADMIN'], []);

    assert.equal(items.length, penyelenggaraItems.length);
});
