import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

// Laporan dari lapangan: di HP, daftar Kelas di /daftar "susah muncul".
// Radix Select membuka daftarnya di bawah kotak dengan tinggi sisa layar, dan
// kotak Kelas ada di dasar kartu — daftarnya jadi garis tipis. Formulir publik
// ini memakai <select> asli supaya HP membuka pemilih layar penuhnya sendiri.
const sumber = readFileSync(
    new URL('../../resources/js/pages/student-register.tsx', import.meta.url),
    'utf8',
);

test('formulir /daftar tidak memakai Radix Select', () => {
    assert.doesNotMatch(sumber, /@\/components\/ui\/select/);
    assert.doesNotMatch(sumber, /<SelectTrigger/);
});

test('keempat pilihan memakai select asli', () => {
    for (const id of [
        'school_id',
        'religion',
        'classroom_id',
        'parent_relation',
    ]) {
        assert.match(sumber, new RegExp(`<PilihanAsli\\s+id="${id}"`));
    }
    assert.match(sumber, /<select\b/);
});
