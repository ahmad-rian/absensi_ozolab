import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';

const require = createRequire(import.meta.url);

test('desktop and open mobile navbar render shared uploaded branding', () => {
    const source = readFileSync(
        new URL(
            '../../resources/js/components/welcome/navbar.tsx',
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
        if (name === 'react')
            return { ...React, useState: () => [true, () => {}] };
        if (name === '@inertiajs/react')
            return {
                usePage: () => ({
                    props: { auth: { user: null }, name: 'Sekolah Test' },
                }),
                Link: ({ children }) =>
                    React.createElement('a', null, children),
            };
        if (name === 'lucide-react')
            return new Proxy(
                {},
                { get: () => () => React.createElement('svg') },
            );
        if (name === '@/components/brand-logo')
            return {
                default: () =>
                    React.createElement('img', {
                        src: '/storage/logo.webp',
                        alt: 'Sekolah Test',
                    }),
            };
        if (name === '@/components/ui/button')
            return {
                Button: ({ children }) =>
                    React.createElement('button', null, children),
            };
        if (name === '@/hooks/use-appearance')
            return {
                useAppearance: () => ({
                    resolvedAppearance: 'light',
                    updateAppearance: () => {},
                }),
            };
        if (name === '@/routes') return { dashboard: () => '/dashboard' };
        return require(name);
    }, exports);
    const html = renderToStaticMarkup(React.createElement(exports.Navbar));
    assert.equal((html.match(/<img/g) ?? []).length, 2);
    assert.ok(html.includes('Tutup menu'));
});
