import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const source = readFileSync(
    new URL('../../resources/js/hooks/use-current-url.ts', import.meta.url),
    'utf8',
);
const { outputText } = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
});
function currentUrl(url) {
    const exports = {};
    new Function('require', 'exports', outputText)((name) => {
        if (name === '@inertiajs/react') return { usePage: () => ({ url }) };
        if (name === '@/lib/utils')
            return {
                toUrl: (href) => (typeof href === 'string' ? href : href.url),
            };
        throw new Error(`Unexpected import: ${name}`);
    }, exports);
    return exports.useCurrentUrl();
}

for (const path of [
    '/orangtua',
    '/orangtua/absensi',
    '/orangtua/sholat',
    '/orangtua/laporan',
    '/orangtua/galeri',
]) {
    test(`active menu survives child and date filters: ${path}`, () => {
        const navigation = currentUrl(
            `${path}?anak=child-2&start_date=2026-09-01`,
        );
        assert.equal(navigation.isCurrentUrl(`${path}?anak=child-2`), true);
        assert.equal(
            navigation.isCurrentUrl(`${path}?anak=child-2#summary`),
            true,
        );
        assert.equal(
            navigation.isCurrentUrl({
                url: `${path}?anak=child-2`,
                method: 'get',
            }),
            true,
        );
        assert.equal(
            navigation.isCurrentUrl('/orangtua/other?anak=child-2'),
            false,
        );
        if (path !== '/orangtua')
            assert.equal(
                navigation.isCurrentUrl('/orangtua?anak=child-2'),
                false,
            );
    });
}

test('plain and absolute links still match', () => {
    const navigation = currentUrl('/admin/users?search=wali');
    assert.equal(navigation.isCurrentUrl('/admin/users'), true);
    assert.equal(
        navigation.isCurrentUrl('https://example.test/admin/users?page=2'),
        true,
    );
    assert.equal(navigation.isCurrentUrl('/admin/roles'), false);
    assert.equal(navigation.isCurrentOrParentUrl('/admin'), true);
});
