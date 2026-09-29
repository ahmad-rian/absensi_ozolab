import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const { outputText } = ts.transpileModule(readFileSync(new URL('../../resources/js/pages/kartu-bebas/layouts/editor.tsx', import.meta.url), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX },
});
const exports = {};
new Function('require', 'exports', outputText)((name) => name === '@/lib/utils' ? { cn: (...values) => values.filter(Boolean).join(' ') } : name.startsWith('@/') || ['@inertiajs/react', 'lucide-react', 'react-rnd'].includes(name) ? {} : require(name), exports);
const { syncElements } = exports;
const field = { key: 'porsi', label: 'Nomor Porsi', type: 'text' };
const qr = { type: 'qr', source: 'porsi', x: 30, y: 4, w: 18, h: 18, enabled: true };
const photo = { type: 'photo', standalone: true, source: '__photo', x: 3, y: 5, w: 24, h: 32, enabled: true };

test('reopening a layout keeps QR settings and standalone photo placement', () => {
    const next = syncElements([field], { __qr: qr, __photo: photo });
    assert.deepEqual(next.__qr, qr);
    assert.deepEqual(next.__photo, photo);
    assert.equal(next.porsi.type, 'field');
});

test('switching datasets clears an unavailable QR source without dropping its position or photo', () => {
    const next = syncElements([{ key: 'nama', label: 'Nama', type: 'text' }], { __qr: qr, __photo: photo, old: { type: 'field', source: 'old' } });
    assert.deepEqual(next.__qr, { ...qr, source: '' });
    assert.deepEqual(next.__photo, photo);
    assert.equal(next.old, undefined);
});

test('a photo field cannot become the QR source', () => {
    assert.equal(syncElements([{ ...field, type: 'photo' }], { __qr: qr }).__qr.source, '');
});


test('changing orientation keeps photo and QR inside the card', () => {
    const next = exports.changeOrientation({ orientation: 'landscape', elements: {
        qr: { ...qr, x: 67, y: 3 }, photo: { ...photo, x: 60, y: 40 },
    } }, 'portrait');
    for (const element of Object.values(next.elements)) {
        assert.ok(element.x >= 0 && element.x + element.w <= 54);
        assert.ok(element.y >= 0 && element.y + element.h <= 85.6);
    }
    assert.equal(next.elements.qr.source, 'porsi');
});


for (const [key, expected] of [
    ['ArrowLeft', { x: 29.9, y: 4 }],
    ['ArrowRight', { x: 30.1, y: 4 }],
    ['ArrowUp', { x: 30, y: 3.9 }],
    ['ArrowDown', { x: 30, y: 4.1 }],
]) {
    test(`${key} nudges the selected element by a tenth of a millimetre`, () => {
        assert.deepEqual(exports.nudgeElement({ orientation: 'portrait', elements: { qr } }, 'qr', key), expected);
    });
}

test('Shift moves one millimetre and repeated small moves have no floating point drift', () => {
    const config = { orientation: 'portrait', elements: { qr: { ...qr } } };
    assert.deepEqual(exports.nudgeElement(config, 'qr', 'ArrowDown', true), { x: 30, y: 5 });
    for (let i = 0; i < 10; i++) {
        Object.assign(config.elements.qr, exports.nudgeElement(config, 'qr', 'ArrowRight'));
    }
    assert.equal(config.elements.qr.x, 31);
});

test('keyboard movement respects the edges for text, photos and QR in both orientations', () => {
    for (const orientation of ['portrait', 'landscape']) {
        const width = orientation === 'portrait' ? 54 : 85.6;
        const height = orientation === 'portrait' ? 85.6 : 54;
        for (const element of [qr, photo, { type: 'field', width: 30, fontSize: 2, enabled: true }]) {
            const w = element.type === 'field' ? element.width : element.w;
            const h = element.type === 'field' ? element.fontSize * 1.4 : element.h;
            const config = { orientation, elements: { selected: { ...element, x: width - w, y: height - h } } };
            assert.ok(exports.nudgeElement(config, 'selected', 'ArrowRight', true).x + w <= width);
            assert.ok(exports.nudgeElement(config, 'selected', 'ArrowDown', true).y + h <= height);
            config.elements.selected.x = 0;
            config.elements.selected.y = 0;
            assert.equal(exports.nudgeElement(config, 'selected', 'ArrowLeft').x, 0);
            assert.equal(exports.nudgeElement(config, 'selected', 'ArrowUp').y, 0);
        }
    }
});

test('canvas handles arrows only when focused and preserves typing and modifier shortcuts', () => {
    const updates = [];
    const config = { orientation: 'portrait', elements: { qr } };
    const canvas = exports.CardPreview({ config, frames: [], selectedId: 'qr', onSelect() {}, onUpdate: (...args) => updates.push(args) });
    const target = {};
    let prevented = 0;
    const event = { target, currentTarget: target, key: 'ArrowRight', nativeEvent: {}, preventDefault: () => prevented++ };
    canvas.props.onKeyDown(event);
    assert.deepEqual(updates, [['qr', { x: 30.1, y: 4 }]]);
    for (const patch of [{ target: { tagName: 'INPUT' } }, { ctrlKey: true }, { metaKey: true }, { altKey: true }, { nativeEvent: { isComposing: true } }, { key: 'Tab' }]) {
        canvas.props.onKeyDown({ ...event, ...patch });
    }
    assert.equal(updates.length, 1);
    assert.equal(prevented, 1);
    assert.equal(canvas.props.tabIndex, 0);
    let focused = false;
    canvas.props.onPointerDownCapture({ currentTarget: { focus: () => { focused = true; } } });
    assert.equal(focused, true);
});

test('no selection or a disabled element leaves keyboard navigation untouched', () => {
    const config = { orientation: 'portrait', elements: { qr: { ...qr, enabled: false } } };
    assert.equal(exports.nudgeElement(config, null, 'ArrowRight'), null);
    assert.equal(exports.nudgeElement(config, 'missing', 'ArrowRight'), null);
    assert.equal(exports.nudgeElement(config, 'qr', 'ArrowRight'), null);
});

test('attendance QR remains configured when the participant dataset changes', () => {
    const attendanceQr = { ...qr, source: '__attendance' };
    const next = syncElements([{ key: 'nama', label: 'Nama', type: 'text' }], { __qr: attendanceQr });
    assert.deepEqual(next.__qr, attendanceQr);
});
