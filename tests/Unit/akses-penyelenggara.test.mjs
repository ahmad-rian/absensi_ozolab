import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

function loadAkses() {
    const source = readFileSync(new URL('../../resources/js/hooks/use-akses-penyelenggara.ts', import.meta.url), 'utf8');
    const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS } });
    const exports = {};
    new Function('require', 'exports', outputText)(() => ({ usePage: () => ({ props: {} }) }), exports);

    return exports;
}

const { aksesDari } = loadAkses();

test('pendamping tidak melihat tombol kelola peserta maupun program', () => {
    const akses = aksesDari(['PENDAMPING'], ['penyelenggara-beranda.access', 'penyelenggara-peserta.access']);

    assert.deepEqual(akses, { program: false, kelolaPeserta: false, kartu: false });
});

test('admin penyelenggara melihat tombol sesuai izinnya', () => {
    const akses = aksesDari(['ADMIN_PENYELENGGARA'], ['penyelenggara-program.access', 'penyelenggara-peserta-kelola.access']);

    assert.equal(akses.program, true);
    assert.equal(akses.kelolaPeserta, true);
    assert.equal(akses.kartu, false);
});

test('super admin melewati cek izin', () => {
    assert.deepEqual(aksesDari(['SUPER_ADMIN'], []), { program: true, kelolaPeserta: true, kartu: true });
});
