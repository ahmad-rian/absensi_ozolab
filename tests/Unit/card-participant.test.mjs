import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import React from 'react';
import ts from 'typescript';

const require = createRequire(import.meta.url);
function compile(file, overrides) {
    const { outputText } = ts.transpileModule(readFileSync(new URL(`../../resources/js/pages/kartu-bebas/peserta/${file}`, import.meta.url), 'utf8'), {
        compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
    });
    const exports = {};
    new Function('require', 'exports', outputText)((name) => overrides(name) ?? require(name), exports);
    return exports;
}
const types = compile('types.ts', () => undefined);
const fields = [{ key: 'nama', label: 'Nama', type: 'text', required: true }, { key: 'foto', label: 'Foto', type: 'photo', required: true }];
const participant = { id: 'participant', layout_id: 'haji', layout_name: 'Haji', fields, data: { nama: 'Ahmad' }, status: 'draft', photo_url: '/crop.png', original_photo_url: '/original.jpg', preview_url: '/preview', download_url: '/download', updated_at: '2026-09-29T00:00:00Z', error: null };
function find(node, predicate) {
    if (!React.isValidElement(node)) return null;
    if (predicate(node)) return node;
    for (const child of React.Children.toArray(node.props.children)) {
        const match = find(child, predicate);
        if (match) return match;
    }
    return null;
}
function harness(file, props) {
    let cursor = 0;
    const states = [];
    const calls = [];
    let form;
    const component = compile(file, (name) => {
        if (name === 'react') return { ...React, useEffect: () => {}, useState: (initial) => {
            const key = cursor++;
            if (!(key in states)) states[key] = initial;
            return [states[key], (value) => { states[key] = typeof value === 'function' ? value(states[key]) : value; }];
        } };
        if (name === './types') return types;
        if (name === '@inertiajs/react') return { Head: 'head', Link: 'a', router: { post: (...args) => calls.push(args), delete: (...args) => calls.push(args) }, usePoll: () => ({ start() {}, stop() {} }), useForm: (data) => {
            form ??= { data, errors: {}, processing: false, clearErrors() {}, post: (...args) => calls.push(args), setData: (key, value) => {
                if (typeof key === 'function') form.data = key(form.data);
                else if (typeof key === 'object') form.data = key;
                else form.data = { ...form.data, [key]: value };
            } };
            return form;
        } };
        if (name.startsWith('@/routes/')) return new Proxy({}, { get: (_, key) => ({ url: (id = '') => `/${key}/${id}` }) });
        if (name.startsWith('@/')) return new Proxy({}, { get: (_, key) => key === 'default' ? 'layout' : key });
        return undefined;
    }).default;
    return { layout: component.layout, render() { cursor = 0; return component(props); }, get form() { return form; }, calls };
}

test('editing text keeps the stored photo and does not submit a crop until requested', () => {
    const view = harness('form.tsx', { participant, layouts: [{ id: 'haji', name: 'Haji', fields }] });
    const tree = view.render();
    assert.equal(find(tree, (node) => node.type === 'CardCropReposition'), null);
    assert.equal(view.form.data.manual_crop, null);
    assert.equal(view.form.data.data.foto, undefined);
    find(tree, (node) => node.type === 'Input' && node.props.id === 'participant-nama').props.onChange({ target: { value: 'Nama Baru' } });
    find(tree, (node) => node.type === 'form').props.onSubmit({ preventDefault() {} });
    assert.equal(view.form.data.data.nama, 'Nama Baru');
    assert.equal(view.form.data._method, 'put');
    assert.equal(view.calls[0][0], '/update/participant');
    assert.equal(view.calls[0][1].forceFormData, true);
});

test('recrop uses original photo and cancellation leaves the current crop untouched', () => {
    const view = harness('form.tsx', { participant, layouts: [{ id: 'haji', name: 'Haji', fields }] });
    let tree = view.render();
    find(tree, (node) => node.type === 'Button' && node.props.children === 'Atur ulang crop').props.onClick();
    tree = view.render();
    const crop = find(tree, (node) => node.type === 'CardCropReposition');
    assert.equal(crop.props.imageUrl, '/original.jpg');
    crop.props.onChange({ sx: 0.1, sy: 0, sw: 0.7, sh: 1 });
    assert.equal(view.form.data.manual_crop.sx, 0.1);
    find(tree, (node) => node.type === 'Button' && node.props.children === 'Batalkan crop ulang').props.onClick();
    assert.equal(view.form.data.manual_crop, null);
});

test('computer photo selection resets crop and submits a file in the layout photo field', () => {
    const view = harness('form.tsx', { participant: null, layouts: [{ id: 'haji', name: 'Haji', fields }] });
    const tree = view.render();
    view.form.data.manual_crop = { sx: 0, sy: 0, sw: 0.5, sh: 1 };
    const photo = new File(['photo'], 'photo.jpg', { type: 'image/jpeg' });
    find(tree, (node) => node.type === 'Input' && node.props.type === 'file').props.onChange({ target: { files: [photo] } });
    assert.equal(view.form.data.data.foto, photo);
    assert.equal(view.form.data.manual_crop, null);
    const crop = find(view.render(), (node) => node.type === 'CardCropReposition');
    assert.ok(crop.props.imageUrl.startsWith('blob:'));
    URL.revokeObjectURL(crop.props.imageUrl);
});

test('Drive photo selection enters the same editable crop flow as a computer upload', async () => {
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => ({ ok: true, blob: async () => new Blob(['photo'], { type: 'image/jpeg' }) });
    try {
        const view = harness('form.tsx', { participant, layouts: [{ id: 'haji', name: 'Haji', fields }] });
        const picker = find(view.render(), (node) => node.type === 'DrivePhotoPicker');
        await picker.props.onSelect({ id: 'drive-photo', name: 'Ahmad.jpg' });
        assert.ok(view.form.data.data.foto instanceof File);
        assert.equal(view.form.data.data.foto.name, 'Ahmad.jpg');
        const crop = find(view.render(), (node) => node.type === 'CardCropReposition');
        assert.ok(crop.props.imageUrl.startsWith('blob:'));
        URL.revokeObjectURL(crop.props.imageUrl);
    } finally { globalThis.fetch = previousFetch; }
});

test('processing participant keeps the previous result visible and disables mutation controls', () => {
    const view = harness('show.tsx', { participant: { ...participant, status: 'processing' } });
    const tree = view.render();
    assert.ok(find(tree, (node) => node.type === 'img' && node.props.alt === 'Hasil kartu peserta'));
    assert.ok(find(tree, (node) => node.type === 'a' && node.props.href === '/download'));
    assert.equal(find(tree, (node) => node.type === 'a' && node.props.href === '/edit/participant'), null);
    assert.equal(find(tree, (node) => node.type === 'Button' && node.props.children === 'Sedang diproses…').props.disabled, true);
    assert.equal(find(tree, (node) => node.type === 'Button' && node.props.children === 'Hapus Peserta').props.disabled, true);
});

test('participant pages provide their own workspace layout instead of nesting the default admin sidebar', () => {
    for (const [file, props] of [
        ['form.tsx', { participant, layouts: [] }],
        ['show.tsx', { participant }],
        ['index.tsx', {}],
    ]) {
        const view = harness(file, props);
        assert.equal(typeof view.layout, 'function');
        const page = React.createElement('main');
        assert.equal(view.layout(page).props.children, page);
    }
});
