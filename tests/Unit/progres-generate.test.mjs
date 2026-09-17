import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';

const source = readFileSync(
    new URL(
        '../../resources/js/components/shared/progres-generate.tsx',
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
const componentModule = { exports: {} };
const require = createRequire(import.meta.url);
const requireWithIconStubs = (name) =>
    name === 'lucide-react'
        ? {
              AlertTriangle: () => null,
              CheckCircle2: () => null,
              Loader2: () => null,
          }
        : require(name);
new Function('require', 'exports', outputText)(
    requireWithIconStubs,
    componentModule.exports,
);
const { ProgresGenerate } = componentModule.exports;

for (const unit of ['kartu', 'berkas']) {
    for (const status of ['completed', 'failed']) {
        test(`progres ${status} menggunakan satuan ${unit}`, () => {
            const selesai = status === 'failed' ? 2 : 3;
            const markup = renderToStaticMarkup(
                React.createElement(ProgresGenerate, {
                    progres: {
                        total: 3,
                        selesai,
                        gagal: status === 'failed' ? 1 : 0,
                        persen: 100,
                        status,
                    },
                    ...(unit === 'berkas'
                        ? { unit, label: 'Membuat pas foto 4R dan kartu OSIS' }
                        : {}),
                }),
            );
            assert.ok(markup.includes(`${selesai} ${unit} selesai`));
            if (status === 'failed') {
                assert.ok(markup.includes('1 gagal'));
            }
            assert.ok(
                markup.includes(
                    unit === 'berkas'
                        ? 'Membuat pas foto 4R dan kartu OSIS'
                        : 'Membuat kartu',
                ),
            );
        });
    }
}
