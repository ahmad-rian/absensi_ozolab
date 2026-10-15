import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import ts from 'typescript';

const require = createRequire(import.meta.url);

function loadSidebar() {
    const source = readFileSync(new URL('../../resources/js/components/app-sidebar.tsx', import.meta.url), 'utf8');
    const { outputText } = ts.transpileModule(source, {
        compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
    });
    const exports = {};
    new Function('require', 'exports', outputText)((name) => {
        if (name === '@/routes') return { dashboard: () => '/dashboard' };
        if (name.startsWith('@/') || name === 'lucide-react' || name === '@inertiajs/react') {
            return new Proxy({}, { get: (_, key) => (key === 'default' ? 'stub' : key) });
        }
        return require(name);
    }, exports);

    return exports;
}

const { sections } = loadSidebar();
const kartuSiswa = sections.find((section) => section.label === 'Kartu Siswa');
const item = kartuSiswa?.items.find((entry) => entry.title === 'Siswa Belum Ada Foto');

test('menu Siswa Belum Ada Foto ada di grup Kartu Siswa', () => {
    assert.ok(item);
    assert.equal(item.href, '/admin/siswa-tanpa-foto');
});

test('menu memakai hak akses halaman siswa dan tidak tersembunyi oleh saklar generate massal', () => {
    assert.equal(item.permission, 'siswa.access');
    assert.equal(item.feature, 'master_siswa');
    assert.notEqual(item.superAdmin, true);
    assert.notEqual(item.massal, true);
});
