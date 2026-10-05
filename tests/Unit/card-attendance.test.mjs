import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import React from 'react';
import ts from 'typescript';

const require = createRequire(import.meta.url);
function harness(file, props) {
    const states = [];
    const requests = [];
    let cursor;
    const { outputText } = ts.transpileModule(readFileSync(new URL(`../../resources/js/pages/${file}`, import.meta.url), 'utf8'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
    });
    const exports = {};
    new Function('require', 'exports', outputText)((name) => {
        if (name === 'react') return { ...React, useState: (initial) => {
            const key = cursor++;
            if (!(key in states)) states[key] = initial;
            return [states[key], (next) => { states[key] = next; }];
        } };
        if (name === '@inertiajs/react') return { Head: 'head', Link: 'a', usePoll() {}, usePage: () => ({ props: { app: { logo: '/logo.png' }, errors: {} } }), router: { get: (...args) => requests.push(args) } };
        if (name.startsWith('@/routes/')) return new Proxy({}, { get: (_, key) => ({ url: (args, options) => {
            if (key === 'scan') return `/scan/${args.token}/${args.mode}`;
            if (key === 'exportMethod') return `/export/${args}?${new URLSearchParams(options.query)}`;
            return `/${key}`;
        } }) });
        if (name.startsWith('@/')) return new Proxy({}, { get: (_, key) => key === 'default' ? 'layout' : key });
        return require(name);
    }, exports);
    return { requests, render() { cursor = 0; return exports.default(props); } };
}
function find(node, predicate) {
    if (!React.isValidElement(node)) return null;
    if (predicate(node)) return node;
    for (const child of React.Children.toArray(node.props.children)) {
        const match = find(child, predicate);
        if (match) return match;
    }
    return null;
}

test('participant scanner starts in masuk mode and explicitly switches the endpoint for pulang', () => {
    const page = harness('scan/card-participant.tsx', { layout: { name: 'Haji', token: 'private-scanner' } });
    let tree = page.render();
    let scanner = find(tree, (node) => node.type === 'PublicScanConsole');
    const initialKey = scanner.key;
    assert.equal(scanner.props.scanUrl, '/scan/private-scanner/masuk');
    assert.equal(scanner.props.subjectLabel, 'peserta');
    assert.equal(scanner.props.school.logo_url, '/logo.png');
    find(tree, (node) => node.type === 'Button' && node.props.children === 'Pulang').props.onClick();
    tree = page.render();
    scanner = find(tree, (node) => node.type === 'PublicScanConsole');
    assert.equal(scanner.props.scanUrl, '/scan/private-scanner/pulang');
    assert.notEqual(scanner.key, initialKey);
});

test('attendance page exposes the selected layout scan link and flags legacy QR layouts', () => {
    const page = harness('kartu-bebas/absensi/index.tsx', {
        layouts: [{ id: 'haji', name: 'Haji', is_active: true, qr_ready: false, scan_url: '/scan-peserta/private' }],
        filters: { layout: 'haji', date: '2026-09-29', q: '' },
        participants: { data: [], total: 0, prev_page_url: null, next_page_url: null },
    });
    const tree = page.render();
    assert.equal(find(tree, (node) => node.type === 'Input' && node.props.readOnly).props.value, '/scan-peserta/private');
    assert.ok(find(tree, (node) => node.type === 'a' && node.props.href === '/scan-peserta/private'));
    assert.ok(find(tree, (node) => node.type === 'a' && node.props.children === 'Atur QR di editor layout'));
});

test('report downloads use applied date layout and status filters', () => {
    const filters = { start_date: '2026-09-01', end_date: '2026-09-29', layout: 'haji', status: 'izin' };
    const page = harness('kartu-bebas/absensi/report.tsx', { filters, layouts: [], counts: {}, records: { data: [], total: 0 } });
    let tree = page.render();
    const excel = find(tree, (node) => node.type === 'a' && node.props.children === 'Unduh Excel');
    assert.ok(excel.props.href.includes('layout=haji'));
    assert.ok(excel.props.href.includes('status=izin'));
    find(tree, (node) => node.type === 'Input' && node.props.value === filters.start_date).props.onChange({ target: { value: '2026-09-10' } });
    tree = page.render();
    assert.ok(find(tree, (node) => node.type === 'a' && node.props.children === 'Unduh Excel').props.href.includes('start_date=2026-09-01'));
    find(tree, (node) => node.type === 'form').props.onSubmit({ preventDefault() {} });
    assert.equal(page.requests[0][1].start_date, '2026-09-10');
});

test('generate ulang semua kartu dikunci sampai layout memakai QR absensi', () => {
    const layout = { id: 'haji', name: 'Haji', is_active: true, qr_ready: false, scan_url: '/scan-peserta/private' };
    const props = { filters: { layout: 'haji', date: '2026-10-05', q: '' }, participants: { data: [], total: 0, prev_page_url: null, next_page_url: null } };
    const tombol = (tree) => find(tree, (node) => node.type === 'Button' && node.props.children === 'Generate ulang semua kartu');

    assert.equal(tombol(harness('kartu-bebas/absensi/index.tsx', { ...props, layouts: [layout] }).render()).props.disabled, true);
    assert.equal(tombol(harness('kartu-bebas/absensi/index.tsx', { ...props, layouts: [{ ...layout, qr_ready: true }] }).render()).props.disabled, false);
});

test('editor memperingatkan QR yang berisi data peserta', () => {
    const editor = readFileSync(new URL('../../resources/js/pages/kartu-bebas/layouts/editor.tsx', import.meta.url), 'utf8');
    assert.match(editor, /qrEntry\[1\]\.source !== '__attendance' && \(/);
    assert.match(editor, /tidak bisa dipakai absen di halaman scan/);
});
