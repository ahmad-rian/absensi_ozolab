import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import ts from 'typescript';

const { outputText } = ts.transpileModule(readFileSync(new URL('../../resources/js/lib/favicon.ts', import.meta.url), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS },
});
const exports = {};
new Function('exports', outputText)(exports);

function headDocument() {
    const links = [];
    return {
        links,
        head: {
            querySelectorAll: () => links.filter((link) => ['icon', 'apple-touch-icon'].includes(link.rel)),
            appendChild: (link) => links.push(link),
        },
        createElement: () => {
            const link = { rel: '', href: '', remove: () => links.splice(links.indexOf(link), 1) };
            return link;
        },
    };
}

test('navigation replaces the old favicon and touch icon after a branding upload', () => {
    const document = headDocument();
    exports.syncFavicon(document, null);
    exports.syncFavicon(document, '/storage/old.webp');
    exports.syncFavicon(document, '/storage/new.webp');
    assert.deepEqual(document.links.map(({ rel, href }) => ({ rel, href })), [
        { rel: 'icon', href: '/storage/new.webp' },
        { rel: 'apple-touch-icon', href: '/storage/new.webp' },
    ]);
});

test('leaving branded pages restores fallback icons without retaining the old upload', () => {
    const document = headDocument();
    exports.syncFavicon(document, '/storage/brand.webp');
    exports.syncFavicon(document, null);
    assert.deepEqual(document.links.map((link) => link.href), ['/favicon.ico', '/favicon.svg', '/apple-touch-icon.png']);
});
