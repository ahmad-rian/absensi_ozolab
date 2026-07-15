import { Head, useForm } from '@inertiajs/react';
import { AlignVerticalJustifyCenter, Eye, Image as ImageIcon, Loader2, Minus, Plus, QrCode, RectangleHorizontal, RectangleVertical, Redo2, Save, Type, Undo2, User } from 'lucide-react';
import { type FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Rnd } from 'react-rnd';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type FrameItem = {
    id: string;
    name: string;
    image_url: string;
    width: number;
    height: number;
    category: string;
};

type FieldElement = { type: 'field'; label: string; source: string; x: number; y: number; width: number; labelWidth: number; fontSize: number; enabled: boolean };
type PhotoElement = { type: 'photo'; x: number; y: number; w: number; h: number; enabled: boolean };
type QrElement = { type: 'qr'; x: number; y: number; size: number; enabled: boolean };
type AnyElement = FieldElement | PhotoElement | QrElement;
type Elements = Record<string, AnyElement>;

type LayoutConfig = {
    orientation: 'landscape' | 'portrait';
    frame_id: string | null;
    elements: Elements;
    [key: string]: unknown;
};

type LayoutData = {
    id?: string;
    name: string;
    type: string;
    is_default: boolean;
    is_active: boolean;
    layout_config: LayoutConfig;
} | null;

type Props = {
    layout: LayoutData;
    defaultElements: Elements;
    frames: FrameItem[];
};

// 1mm = 5.607px at 480px card width (480 / 85.6)
const S = 5.607;
const mm = (v: number) => v * S;
const toMm = (px: number) => Math.round((px / S) * 10) / 10;

function buildInitialConfig(layout: LayoutData, defaults: Elements): LayoutConfig {
    const cfg = (layout?.layout_config ?? {}) as Partial<LayoutConfig>;
    const merged: Elements = {};
    for (const [key, def] of Object.entries(defaults)) {
        merged[key] = { ...def, ...(cfg.elements?.[key] ?? {}) } as AnyElement;
    }
    return {
        ...cfg,
        orientation: cfg.orientation === 'portrait' ? 'portrait' : 'landscape',
        frame_id: (cfg.frame_id as string | null) ?? null,
        elements: merged,
    };
}

// Element bounding box in px (for snapping + guides).
function boxPx(el: AnyElement): { w: number; h: number } {
    if (el.type === 'field') {
        return { w: mm(el.width), h: mm(el.fontSize) * 1.4 };
    }
    if (el.type === 'photo') {
        return { w: mm(el.w), h: mm(el.h) };
    }
    return { w: mm(el.size), h: mm(el.size) };
}

const SNAP_TH = 5; // px

// Canva-style smart snapping: align a dragged element's edges/centers to
// other elements + canvas edges/center. Returns snapped px position + guide lines.
function computeSnap(
    movingId: string,
    px: number,
    py: number,
    elements: Elements,
    cw: number,
    ch: number,
): { x: number; y: number; v: number[]; h: number[] } {
    const { w, h } = boxPx(elements[movingId]);

    const vTargets: number[] = [0, cw / 2, cw];
    const hTargets: number[] = [0, ch / 2, ch];
    for (const [oid, oel] of Object.entries(elements)) {
        if (oid === movingId || !oel.enabled) {
            continue;
        }
        const ob = boxPx(oel);
        const ol = mm(oel.x);
        const ot = mm(oel.y);
        vTargets.push(ol, ol + ob.w / 2, ol + ob.w);
        hTargets.push(ot, ot + ob.h / 2, ot + ob.h);
    }

    let snapX = px;
    let bestDX = SNAP_TH + 1;
    const v: number[] = [];
    for (const t of vTargets) {
        for (const edge of [px, px + w / 2, px + w]) {
            const d = Math.abs(edge - t);
            if (d <= SNAP_TH) {
                v.push(t);
                if (d < bestDX) {
                    bestDX = d;
                    snapX = px + (t - edge);
                }
            }
        }
    }

    let snapY = py;
    let bestDY = SNAP_TH + 1;
    const hLines: number[] = [];
    for (const t of hTargets) {
        for (const edge of [py, py + h / 2, py + h]) {
            const d = Math.abs(edge - t);
            if (d <= SNAP_TH) {
                hLines.push(t);
                if (d < bestDY) {
                    bestDY = d;
                    snapY = py + (t - edge);
                }
            }
        }
    }

    return { x: snapX, y: snapY, v: [...new Set(v)], h: [...new Set(hLines)] };
}

function CardPreview({
    config,
    frames,
    selectedIds,
    onSelect,
    onUpdate,
    onUpdateMany,
}: {
    config: LayoutConfig;
    frames: FrameItem[];
    selectedIds: string[];
    onSelect: (id: string | null, additive?: boolean) => void;
    onUpdate: (id: string, patch: Partial<AnyElement>) => void;
    onUpdateMany: (patch: Record<string, Partial<AnyElement>>) => void;
}) {
    const isPortrait = config.orientation === 'portrait';
    const cardW = isPortrait ? 54 : 85.6;
    const cardH = isPortrait ? 85.6 : 54;
    const cw = mm(cardW);
    const ch = mm(cardH);
    const selectedFrame = frames.find((f) => f.id === config.frame_id);
    const [guides, setGuides] = useState<{ v: number[]; h: number[] }>({ v: [], h: [] });
    // Marquee (rubber-band) selection rect in canvas px.
    const [marquee, setMarquee] = useState<{ x0: number; y0: number; x1: number; y1: number } | null>(null);
    const marqueeStart = useRef<{ x: number; y: number } | null>(null);
    // DOM nodes per element — used to translate the whole selection live during a group drag.
    const elNodeRefs = useRef<Record<string, HTMLElement | null>>({});

    const isMulti = selectedIds.length > 1;

    function beginMarquee(e: React.PointerEvent<HTMLDivElement>) {
        if (e.target !== e.currentTarget) {
            return; // started on an element, not the empty canvas
        }
        const rect = e.currentTarget.getBoundingClientRect();
        marqueeStart.current = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        e.currentTarget.setPointerCapture(e.pointerId);
    }
    function moveMarquee(e: React.PointerEvent<HTMLDivElement>) {
        if (!marqueeStart.current) {
            return;
        }
        const rect = e.currentTarget.getBoundingClientRect();
        setMarquee({ x0: marqueeStart.current.x, y0: marqueeStart.current.y, x1: e.clientX - rect.left, y1: e.clientY - rect.top });
    }
    function endMarquee() {
        if (!marqueeStart.current) {
            return;
        }
        const m = marquee;
        marqueeStart.current = null;
        setMarquee(null);
        if (!m) {
            onSelect(null); // simple click on empty canvas clears selection
            return;
        }
        const rx0 = Math.min(m.x0, m.x1);
        const ry0 = Math.min(m.y0, m.y1);
        const rx1 = Math.max(m.x0, m.x1);
        const ry1 = Math.max(m.y0, m.y1);
        if (rx1 - rx0 < 4 && ry1 - ry0 < 4) {
            onSelect(null);
            return;
        }
        const hits: string[] = [];
        for (const [id, el] of Object.entries(config.elements)) {
            if (!el.enabled) {
                continue;
            }
            const b = boxPx(el);
            const l = mm(el.x);
            const t = mm(el.y);
            if (l < rx1 && l + b.w > rx0 && t < ry1 && t + b.h > ry0) {
                hits.push(id);
            }
        }
        onSelect(null);
        hits.forEach((id, i) => onSelect(id, i > 0));
    }

    return (
        <div
            className="relative overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black/10"
            style={{
                width: cw,
                height: ch,
                background: selectedFrame
                    ? `url(${selectedFrame.image_url}) center/cover no-repeat`
                    : 'linear-gradient(135deg, #e0f3ff, #c7e9fb, #b8e1f7)',
            }}
            onPointerDown={beginMarquee}
            onPointerMove={moveMarquee}
            onPointerUp={endMarquee}
        >
            {Object.entries(config.elements).map(([id, el]) => {
                if (!el.enabled) {
                    return null;
                }
                const selected = selectedIds.includes(id);
                const pos = { x: mm(el.x), y: mm(el.y) };

                const onMouseDownSelect = (e: React.MouseEvent) => {
                    if (e.shiftKey || e.metaKey || e.ctrlKey) {
                        onSelect(id, true); // toggle in/out of the selection
                        return;
                    }
                    // Don't collapse an existing multi-selection on plain mousedown —
                    // keep it so dragging this element moves the whole group.
                    if (!selected) {
                        onSelect(id);
                    }
                };
                const common = {
                    bounds: 'parent' as const,
                    onDragStart: () => {
                        if (!selected) {
                            onSelect(id);
                        }
                    },
                    onDrag: (_e: unknown, d: { x: number; y: number }) => {
                        const s = computeSnap(id, d.x, d.y, config.elements, cw, ch);
                        setGuides({ v: s.v, h: s.h });
                        // Live group drag: translate the other selected elements imperatively
                        // (React state would fight react-rnd's internal drag on the primary).
                        if (isMulti && selected) {
                            const dx = d.x - mm(el.x);
                            const dy = d.y - mm(el.y);
                            for (const sid of selectedIds) {
                                if (sid === id) {
                                    continue;
                                }
                                const node = elNodeRefs.current[sid];
                                if (node) {
                                    node.style.transform = `translate(${dx}px, ${dy}px)`;
                                }
                            }
                        }
                    },
                    className: cn('group', selected && 'z-20'),
                };
                const clearGroupTransforms = () => {
                    for (const sid of selectedIds) {
                        const node = elNodeRefs.current[sid];
                        if (node) {
                            node.style.transform = '';
                        }
                    }
                };
                const dragStop = (d: { x: number; y: number }) => {
                    setGuides({ v: [], h: [] });
                    // Treat a barely-moved drag as a click — don't snap/jump the element.
                    if (Math.abs(d.x - mm(el.x)) < 1.5 && Math.abs(d.y - mm(el.y)) < 1.5) {
                        clearGroupTransforms();
                        return;
                    }
                    const s = computeSnap(id, d.x, d.y, config.elements, cw, ch);
                    // Multi-select: move every selected element by the same delta.
                    if (isMulti && selected) {
                        const ddx = toMm(s.x - mm(el.x));
                        const ddy = toMm(s.y - mm(el.y));
                        const patch: Record<string, Partial<AnyElement>> = {};
                        for (const sid of selectedIds) {
                            const sel = config.elements[sid];
                            if (sel) {
                                patch[sid] = { x: Math.round((sel.x + ddx) * 10) / 10, y: Math.round((sel.y + ddy) * 10) / 10 } as Partial<AnyElement>;
                            }
                        }
                        clearGroupTransforms();
                        onUpdateMany(patch);
                        return;
                    }
                    onUpdate(id, { x: toMm(s.x), y: toMm(s.y) } as Partial<AnyElement>);
                };

                if (el.type === 'field') {
                    return (
                        <Rnd
                            key={id}
                            {...common}
                            size={{ width: mm(el.width), height: mm(el.fontSize) * 1.4 }}
                            position={pos}
                            enableResizing={!isMulti ? { left: true, right: true } : false}
                            onDragStop={(_e, d) => dragStop(d)}
                            onResizeStop={(_e, _dir, ref, _delta, p) =>
                                onUpdate(id, { width: toMm(ref.offsetWidth), x: toMm(p.x), y: toMm(p.y) })
                            }
                        >
                            <div
                                ref={(n) => {
                                    elNodeRefs.current[id] = n;
                                }}
                                onMouseDown={onMouseDownSelect}
                                className={cn(
                                    'relative flex h-full w-full cursor-move items-baseline whitespace-nowrap rounded-sm',
                                    selected ? 'outline outline-1 outline-sky-500' : 'outline-dashed outline-1 outline-transparent group-hover:outline-sky-400/50',
                                )}
                                style={{
                                    fontFamily: "'Inter Tight', sans-serif",
                                    fontSize: mm(el.fontSize),
                                    fontWeight: 800,
                                    lineHeight: 1.25,
                                    color: '#0c0c14',
                                }}
                            >
                                <span className="shrink-0" style={{ width: mm(el.labelWidth) }}>{el.label}</span>
                                <span className="shrink-0 px-[2px]">:</span>
                                <span className="overflow-hidden font-bold text-ellipsis">XXXXXXX</span>
                                {selected && !isMulti && (
                                    <div
                                        title="Geser posisi titik dua"
                                        onMouseDown={(e) => e.stopPropagation()}
                                        onClick={(e) => e.stopPropagation()}
                                        onPointerDown={(e) => {
                                            e.stopPropagation();
                                            (e.target as HTMLElement).setPointerCapture(e.pointerId);
                                        }}
                                        onPointerMove={(e) => {
                                            if (e.buttons !== 1) {
                                                return;
                                            }
                                            const rect = e.currentTarget.parentElement!.getBoundingClientRect();
                                            const mmX = Math.round(toMm(e.clientX - rect.left) * 10) / 10;
                                            onUpdate(id, { labelWidth: Math.max(0, Math.min(el.width - 4, mmX)) } as Partial<AnyElement>);
                                        }}
                                        className="absolute top-0 z-10 h-full w-[3px] -translate-x-1/2 cursor-col-resize rounded-full bg-sky-500/80 hover:bg-sky-500"
                                        style={{ left: mm(el.labelWidth) }}
                                    />
                                )}
                            </div>
                        </Rnd>
                    );
                }

                if (el.type === 'photo') {
                    return (
                        <Rnd
                            key={id}
                            {...common}
                            size={{ width: mm(el.w), height: mm(el.h) }}
                            position={pos}
                            enableResizing={false}
                            onDragStop={(_e, d) => dragStop(d)}
                        >
                            <div
                                ref={(n) => {
                                    elNodeRefs.current[id] = n;
                                }}
                                onMouseDown={onMouseDownSelect}
                                className={cn(
                                    'flex size-full cursor-move items-center justify-center rounded bg-zinc-400/40 text-white/70',
                                    selected ? 'outline outline-1 outline-sky-500' : 'outline-dashed outline-1 outline-zinc-400/60',
                                )}
                            >
                                <User style={{ width: mm(el.w) * 0.4, height: mm(el.w) * 0.4 }} />
                            </div>
                        </Rnd>
                    );
                }

                // qr
                return (
                    <Rnd
                        key={id}
                        {...common}
                        size={{ width: mm(el.size), height: mm(el.size) }}
                        position={pos}
                        enableResizing={false}
                        onDragStop={(_e, d) => dragStop(d)}
                    >
                        <div
                            ref={(n) => {
                                elNodeRefs.current[id] = n;
                            }}
                            onMouseDown={onMouseDownSelect}
                            className={cn(
                                'grid size-full cursor-move grid-cols-5 grid-rows-5 gap-px rounded bg-white p-0.5',
                                selected ? 'outline outline-1 outline-sky-500' : 'outline-dashed outline-1 outline-zinc-400/60',
                            )}
                        >
                            {Array.from({ length: 25 }).map((_, i) => (
                                <div key={i} className={i % 3 === 0 ? 'bg-zinc-800' : 'bg-zinc-200'} />
                            ))}
                        </div>
                    </Rnd>
                );
            })}

            {/* Smart alignment guides */}
            {guides.v.map((x, i) => (
                <div key={`v${i}`} className="pointer-events-none absolute top-0 z-30 w-px bg-sky-500" style={{ left: x, height: ch }} />
            ))}
            {guides.h.map((y, i) => (
                <div key={`h${i}`} className="pointer-events-none absolute left-0 z-30 h-px bg-sky-500" style={{ top: y, width: cw }} />
            ))}

            {/* Marquee rubber-band */}
            {marquee && (
                <div
                    className="pointer-events-none absolute z-40 rounded-sm border border-sky-500 bg-sky-500/10"
                    style={{
                        left: Math.min(marquee.x0, marquee.x1),
                        top: Math.min(marquee.y0, marquee.y1),
                        width: Math.abs(marquee.x1 - marquee.x0),
                        height: Math.abs(marquee.y1 - marquee.y0),
                    }}
                />
            )}
        </div>
    );
}

const ELEMENT_ICONS: Record<string, typeof Type> = { field: Type, photo: ImageIcon, qr: QrCode };

export default function CardLayoutEditor({ layout, defaultElements, frames }: Props) {
    const isEditing = !!layout?.id;
    const [selectedIds, setSelectedIds] = useState<string[]>([]);

    const selectElement = useCallback((id: string | null, additive = false) => {
        setSelectedIds((prev) => {
            if (id === null) {
                return [];
            }
            if (additive) {
                return prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id];
            }
            return [id];
        });
    }, []);

    const form = useForm({
        name: layout?.name ?? '',
        type: layout?.type ?? 'osis',
        is_default: layout?.is_default ?? false,
        is_active: layout?.is_active ?? true,
        layout_config: buildInitialConfig(layout, defaultElements),
    });

    const config = form.data.layout_config;

    // --- Undo / redo history (Canva-style) ---
    const pastRef = useRef<LayoutConfig[]>([]);
    const futureRef = useRef<LayoutConfig[]>([]);
    const beforeBurstRef = useRef<LayoutConfig | null>(null);
    const flushTimerRef = useRef<number | null>(null);
    const [, bumpHistory] = useState(0);

    const flushBurst = useCallback(() => {
        if (flushTimerRef.current !== null) {
            window.clearTimeout(flushTimerRef.current);
            flushTimerRef.current = null;
        }
        if (beforeBurstRef.current !== null) {
            pastRef.current.push(beforeBurstRef.current);
            futureRef.current = [];
            beforeBurstRef.current = null;
            bumpHistory((n) => n + 1);
        }
    }, []);

    const setLayoutConfig = useCallback(
        (next: LayoutConfig) => {
            form.setData('layout_config', next);
        },
        [form],
    );

    function setConfig(patch: Partial<LayoutConfig>) {
        // Capture the pre-change snapshot once per rapid burst (coalesces drag streams).
        if (beforeBurstRef.current === null) {
            beforeBurstRef.current = config;
        }
        if (flushTimerRef.current !== null) {
            window.clearTimeout(flushTimerRef.current);
        }
        flushTimerRef.current = window.setTimeout(flushBurst, 450);
        setLayoutConfig({ ...config, ...patch });
    }

    const undo = useCallback(() => {
        flushBurst();
        const prev = pastRef.current.pop();
        if (!prev) {
            return;
        }
        futureRef.current.push(form.data.layout_config);
        setLayoutConfig(prev);
        bumpHistory((n) => n + 1);
    }, [flushBurst, form.data.layout_config, setLayoutConfig]);

    const redo = useCallback(() => {
        flushBurst();
        const next = futureRef.current.pop();
        if (!next) {
            return;
        }
        pastRef.current.push(form.data.layout_config);
        setLayoutConfig(next);
        bumpHistory((n) => n + 1);
    }, [flushBurst, form.data.layout_config, setLayoutConfig]);

    useEffect(() => {
        function onKey(e: KeyboardEvent) {
            if (!(e.metaKey || e.ctrlKey)) {
                return;
            }
            const target = e.target as HTMLElement;
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName) || target.isContentEditable) {
                return;
            }
            const key = e.key.toLowerCase();
            if (key === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
            } else if ((key === 'z' && e.shiftKey) || key === 'y') {
                e.preventDefault();
                redo();
            }
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [undo, redo]);

    const canUndo = pastRef.current.length > 0 || beforeBurstRef.current !== null;
    const canRedo = futureRef.current.length > 0;

    function updateElement(id: string, patch: Partial<AnyElement>) {
        const el = config.elements[id];
        if (!el) {
            return;
        }
        setConfig({ elements: { ...config.elements, [id]: { ...el, ...patch } as AnyElement } });
    }

    function updateElements(patch: Record<string, Partial<AnyElement>>) {
        const next = { ...config.elements };
        for (const [id, p] of Object.entries(patch)) {
            if (next[id]) {
                next[id] = { ...next[id], ...p } as AnyElement;
            }
        }
        setConfig({ elements: next });
    }

    function toggleElement(id: string, enabled: boolean) {
        updateElement(id, { enabled } as Partial<AnyElement>);
        if (enabled) {
            selectElement(id);
        }
    }

    // Align every enabled field to the same left edge + label column so all colons line up.
    function alignColons() {
        const fields = Object.entries(config.elements).filter(([, el]) => el.type === 'field' && el.enabled) as [string, FieldElement][];
        if (fields.length === 0) {
            return;
        }
        const commonX = Math.min(...fields.map(([, el]) => el.x));
        const commonLW = Math.max(...fields.map(([, el]) => el.labelWidth));
        const next = { ...config.elements };
        for (const [id, el] of fields) {
            next[id] = { ...el, x: commonX, labelWidth: commonLW };
        }
        setConfig({ elements: next });
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        if (isEditing) {
            form.put(`/admin/card-layouts/${layout!.id}`, { preserveScroll: true });
        } else {
            form.post('/admin/card-layouts', { preserveScroll: true });
        }
    }

    const soleSelectedId = selectedIds.length === 1 ? selectedIds[0] : null;
    const selected = soleSelectedId ? config.elements[soleSelectedId] : null;
    const frameOptions = useMemo(() => frames.filter((f) => f.category === form.data.type), [frames, form.data.type]);

    return (
        <>
            <Head title={isEditing ? 'Edit Layout Kartu' : 'Buat Layout Kartu'} />
            <form onSubmit={handleSubmit} className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight">{isEditing ? 'Edit Layout Kartu' : 'Buat Layout Kartu Baru'}</h1>
                    <div className="flex items-center gap-2">
                        <Button type="button" variant="outline" size="icon" onClick={undo} disabled={!canUndo} title="Urungkan (Ctrl/⌘+Z)">
                            <Undo2 className="size-4" />
                        </Button>
                        <Button type="button" variant="outline" size="icon" onClick={redo} disabled={!canRedo} title="Ulangi (Ctrl/⌘+Shift+Z)">
                            <Redo2 className="size-4" />
                        </Button>
                        <Button type="submit" disabled={form.processing} className="gap-2">
                            {form.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            Simpan Layout
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
                    {/* Live drag canvas */}
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-2">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Eye className="size-4" /> Preview Kartu <span className="text-muted-foreground text-xs font-normal">— seret elemen untuk mengatur posisi</span>
                            </CardTitle>
                            <Button type="button" variant="outline" size="sm" onClick={alignColons} className="gap-1.5">
                                <AlignVerticalJustifyCenter className="size-3.5" /> Rapikan Titik Dua
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div className="flex justify-center py-4">
                                <CardPreview config={config} frames={frames} selectedIds={selectedIds} onSelect={selectElement} onUpdate={updateElement} onUpdateMany={updateElements} />
                            </div>
                            <p className="text-muted-foreground mt-3 text-center text-xs">
                                {config.orientation === 'portrait' ? '54 × 85.6 mm · Portrait' : '85.6 × 54 mm · Landscape'} · ATM Card Size · 400 DPI Export
                            </p>
                        </CardContent>
                    </Card>

                    {/* Settings panel */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader><CardTitle className="text-base">Informasi Layout</CardTitle></CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid gap-1.5">
                                    <Label>Nama Layout</Label>
                                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Kartu OSIS Biru" />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label>Jenis Kartu</Label>
                                    <Select value={form.data.type} onValueChange={(v) => form.setData('type', v)}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="osis">Kartu OSIS</SelectItem>
                                            <SelectItem value="perpustakaan">Kartu Perpustakaan</SelectItem>
                                            <SelectItem value="identitas">Kartu Identitas</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox checked={form.data.is_default} onCheckedChange={(c) => form.setData('is_default', c === true)} id="is_default" />
                                    <Label htmlFor="is_default">Jadikan default</Label>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Orientasi</CardTitle></CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-2">
                                    {([
                                        { v: 'landscape', label: 'Landscape', Icon: RectangleHorizontal },
                                        { v: 'portrait', label: 'Portrait', Icon: RectangleVertical },
                                    ] as const).map(({ v, label, Icon }) => (
                                        <button
                                            key={v}
                                            type="button"
                                            onClick={() => setConfig({ orientation: v })}
                                            className={cn(
                                                'flex flex-col items-center gap-1.5 rounded-lg border p-3 text-xs font-medium transition',
                                                config.orientation === v ? 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300' : 'border-border hover:bg-muted',
                                            )}
                                        >
                                            <Icon className="size-5" /> {label}
                                        </button>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Frame</CardTitle></CardHeader>
                            <CardContent>
                                <Select value={String(config.frame_id ?? 'none')} onValueChange={(v) => setConfig({ frame_id: v === 'none' ? null : v })}>
                                    <SelectTrigger><SelectValue placeholder="Tanpa frame" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">Tanpa Frame</SelectItem>
                                        {frameOptions.map((f) => (
                                            <SelectItem key={f.id} value={String(f.id)}>{f.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle className="text-base">Elemen Kartu</CardTitle></CardHeader>
                            <CardContent className="space-y-1">
                                {Object.entries(config.elements).map(([id, el]) => {
                                    const Icon = ELEMENT_ICONS[el.type] ?? Type;
                                    const name = el.type === 'field' ? el.label : el.type === 'photo' ? 'Foto' : 'QR Code';
                                    return (
                                        <div
                                            key={id}
                                            role="button"
                                            tabIndex={0}
                                            onClick={(e) => selectElement(id, e.shiftKey || e.metaKey || e.ctrlKey)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' || e.key === ' ') {
                                                    e.preventDefault();
                                                    selectElement(id);
                                                }
                                            }}
                                            className={cn(
                                                'flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition',
                                                selectedIds.includes(id) ? 'bg-sky-50 dark:bg-sky-950/40' : 'hover:bg-muted',
                                                !el.enabled && 'opacity-50',
                                            )}
                                        >
                                            <Checkbox
                                                checked={el.enabled}
                                                onCheckedChange={(c) => toggleElement(id, c === true)}
                                                onClick={(e) => e.stopPropagation()}
                                            />
                                            <Icon className="text-muted-foreground size-3.5 shrink-0" />
                                            <span className="truncate">{name}</span>
                                        </div>
                                    );
                                })}
                            </CardContent>
                        </Card>

                        {selected && (
                            <Card>
                                <CardHeader><CardTitle className="text-base">Atur Elemen Terpilih</CardTitle></CardHeader>
                                <CardContent className="grid grid-cols-2 gap-2">
                                    <NumField label="X (mm)" value={selected.x} onChange={(v) => updateElement(soleSelectedId!, { x: v } as Partial<AnyElement>)} />
                                    <NumField label="Y (mm)" value={selected.y} onChange={(v) => updateElement(soleSelectedId!, { y: v } as Partial<AnyElement>)} />
                                    {selected.type === 'field' && (
                                        <>
                                            <NumField label="Lebar (mm)" value={selected.width} onChange={(v) => updateElement(soleSelectedId!, { width: v } as Partial<AnyElement>)} />
                                            <NumField label="Posisi Titik Dua (mm)" value={selected.labelWidth} onChange={(v) => updateElement(soleSelectedId!, { labelWidth: v } as Partial<AnyElement>)} />
                                            <FontControl value={selected.fontSize} onChange={(v) => updateElement(soleSelectedId!, { fontSize: v } as Partial<AnyElement>)} />
                                        </>
                                    )}
                                    {(selected.type === 'photo' || selected.type === 'qr') && (
                                        <p className="text-muted-foreground col-span-2 text-[11px]">Ukuran {selected.type === 'photo' ? 'foto' : 'QR'} tetap — hanya bisa digeser posisinya.</p>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {selectedIds.length > 1 && (
                            <Card>
                                <CardContent className="text-muted-foreground py-3 text-xs">
                                    <span className="text-foreground font-semibold">{selectedIds.length} elemen</span> terpilih — seret salah satunya untuk memindahkan semua sekaligus. Shift/⌘+klik untuk tambah/kurang, atau seret di area kosong untuk memilih.
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </form>
        </>
    );
}

function NumField({ label, value, onChange, step = 0.5 }: { label: string; value: number; onChange: (v: number) => void; step?: number }) {
    return (
        <div className="grid gap-1">
            <Label className="text-muted-foreground text-[10px]">{label}</Label>
            <Input type="number" step={step} value={value} onChange={(e) => onChange(Number(e.target.value))} className="h-8 text-xs" />
        </div>
    );
}

const MM_TO_PT = 2.83465;
const FONT_MIN = 1.0;
const FONT_MAX = 6.0;
const FONT_PRESETS: { label: string; mm: number }[] = [
    { label: 'S', mm: 1.6 },
    { label: 'M', mm: 2.0 },
    { label: 'L', mm: 2.6 },
];

function clampFont(mm: number): number {
    return Math.min(FONT_MAX, Math.max(FONT_MIN, Math.round(mm * 100) / 100));
}

function FontControl({ value, onChange }: { value: number; onChange: (mm: number) => void }) {
    const pt = Math.round(value * MM_TO_PT);
    return (
        <div className="col-span-2 grid gap-1.5">
            <Label className="text-muted-foreground text-[10px]">Ukuran Font</Label>
            <div className="flex items-center gap-1.5">
                <Button type="button" variant="outline" size="icon" className="size-8 shrink-0" onClick={() => onChange(clampFont(value - 0.35))}>
                    <Minus className="size-3.5" />
                </Button>
                <div className="flex h-8 flex-1 items-center justify-center rounded-md border text-xs font-medium tabular-nums">{pt} pt</div>
                <Button type="button" variant="outline" size="icon" className="size-8 shrink-0" onClick={() => onChange(clampFont(value + 0.35))}>
                    <Plus className="size-3.5" />
                </Button>
                {FONT_PRESETS.map((p) => (
                    <Button
                        key={p.label}
                        type="button"
                        variant={Math.abs(value - p.mm) < 0.05 ? 'default' : 'outline'}
                        size="icon"
                        className="size-8 shrink-0 text-xs"
                        onClick={() => onChange(p.mm)}
                    >
                        {p.label}
                    </Button>
                ))}
            </div>
            <input
                type="range"
                min={FONT_MIN}
                max={FONT_MAX}
                step={0.05}
                value={value}
                onChange={(e) => onChange(clampFont(Number(e.target.value)))}
                className="accent-sky-500"
            />
        </div>
    );
}

CardLayoutEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Layout Kartu', href: '/admin/card-layouts' },
        { title: 'Editor' },
    ],
};
