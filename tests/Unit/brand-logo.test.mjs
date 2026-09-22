import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';

const require = createRequire(import.meta.url);
function renderLogo(logo) {
    const source = readFileSync(
        new URL(
            '../../resources/js/components/brand-logo.tsx',
            import.meta.url,
        ),
        'utf8',
    );
    const { outputText } = ts.transpileModule(source, {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            jsx: ts.JsxEmit.ReactJSX,
        },
    });
    const exports = {};
    new Function('require', 'exports', outputText)((name) => {
        if (name === '@inertiajs/react')
            return {
                usePage: () => ({
                    props: { name: 'Sekolah Test', app: { logo } },
                }),
            };
        if (name === '@/components/app-logo-icon')
            return {
                default: () =>
                    React.createElement('svg', { 'data-fallback': true }),
            };
        if (name === '@/lib/utils')
            return { cn: (...values) => values.filter(Boolean).join(' ') };
        return require(name);
    }, exports);
    return renderToStaticMarkup(
        React.createElement(exports.default, { className: 'size-14' }),
    );
}

test('uploaded branding renders as an image with application name', () => {
    const html = renderLogo('/storage/images/logo.webp');
    assert.ok(html.includes('src="/storage/images/logo.webp"'));
    assert.ok(html.includes('alt="Sekolah Test"'));
    assert.ok(!html.includes('data-fallback'));
});

test('missing upload retains the default icon', () => {
    assert.ok(renderLogo(null).includes('data-fallback'));
});
