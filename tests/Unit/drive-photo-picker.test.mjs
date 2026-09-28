import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import React from 'react';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const { outputText } = ts.transpileModule(readFileSync(new URL('../../resources/js/components/shared/drive-photo-picker.tsx', import.meta.url), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
});

function picker(props) {
    const selected = { id: 'photo-123', name: 'Peserta.jpg', thumb: true, size: 1000 };
    const initial = [true, { tersedia: true, gambar: [selected] }, false, '', selected, false];
    const updates = [];
    const posts = [];
    let index = 0;
    const exports = {};
    new Function('require', 'exports', outputText)((name) => {
        if (name === 'react') return { ...React, useCallback: (fn) => fn, useState: () => {
            const key = index++;
            return [initial[key], (value) => updates.push([key, value])];
        } };
        if (name === '@inertiajs/react') return { router: { post: (...args) => posts.push(args) } };
        if (name === 'lucide-react') return new Proxy({}, { get: () => 'svg' });
        if (name.startsWith('@/')) return new Proxy({}, { get: (_, key) => key === 'Button' ? 'button' : 'div' });
        return require(name);
    }, exports);
    const tree = exports.DrivePhotoPicker(props);
    function find(node, predicate) {
        if (!React.isValidElement(node)) return null;
        if (predicate(node)) return node;
        for (const child of React.Children.toArray(node.props.children)) {
            const found = find(child, predicate);
            if (found) return found;
        }
        return null;
    }
    return {
        updates, posts, selected,
        image: find(tree, (node) => node.type === 'img'),
        confirm: find(tree, (node) => node.type === 'button' && React.Children.toArray(node.props.children).some((child) => child === 'Pakai Peserta.jpg')),
    };
}

test('card mode selects a Drive photo through the callback without updating a student', async () => {
    let chosen;
    const view = picker({ browseUrl: '/kartu-bebas/drive/jelajah', thumbnailUrl: (id) => `/kartu-bebas/drive/thumb/${id}`, onSelect: async (image) => { chosen = image; } });
    assert.equal(view.image.props.src, '/kartu-bebas/drive/thumb/photo-123');
    await view.confirm.props.onClick();
    assert.deepEqual(chosen, view.selected);
    assert.deepEqual(view.posts, []);
    assert.ok(view.updates.some(([key, value]) => key === 0 && value === false));
});

test('failed card download keeps the picker open and displays the error', async () => {
    const view = picker({ browseUrl: '/browse', thumbnailUrl: () => '/thumb', onSelect: async () => { throw new Error('Foto tidak tersedia'); } });
    await view.confirm.props.onClick();
    assert.ok(view.updates.some(([key, value]) => key === 3 && value === 'Foto tidak tersedia'));
    assert.ok(!view.updates.some(([key, value]) => key === 0 && value === false));
});

test('student mode retains the original photo installation endpoint', async () => {
    const view = picker({ studentId: 'student-1' });
    await view.confirm.props.onClick();
    assert.equal(view.posts[0][0], '/admin/siswa/student-1/foto/drive');
    assert.deepEqual(view.posts[0][1], { file_id: 'photo-123' });
});
