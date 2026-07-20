import { Head, useForm } from '@inertiajs/react';
import { Eye, Loader2, RectangleHorizontal, RectangleVertical, Save, User } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { Rnd } from 'react-rnd';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import { cn } from '@/lib/utils';

type FieldType = 'text' | 'date' | 'number' | 'select' | 'photo';

type FormField = {
    key: string;
    label: string;
    type: FieldType;
    required?: boolean;
    options?: string[];
};

type FieldElement = { type: 'field'; label: string; source: string; x: number; y: number; width: number; labelWidth: number; fontSize: number; enabled: boolean };
type PhotoElement = { type: 'photo'; source: string; x: number; y: number; w: number; h: number; enabled: boolean };
type AnyElement = FieldElement | PhotoElement;
type Elements = Record<string, AnyElement>;

type LayoutConfig = {
    orientation: 'landscape' | 'portrait';
    frame_id: string | null;
    elements: Elements;
};

type FrameItem = { id: string; name: string; image_url: string; width: number; height: number; category: string };

type Dataset = { id: string; name: string; fields: FormField[] };

type FormData = {
    id?: string;
    name: string;
    token?: string;
    card_dataset_id: string | null;
    orientation: 'landscape' | 'portrait';
    frame_id: string | null;
    is_active: boolean;
    layout_config: LayoutConfig;
    public_url?: string;
} | null;

type Props = { form: FormData; frames: FrameItem[]; datasets: Dataset[] };

type EditorFormData = {
    name: string;
    card_dataset_id: string | null;
    orientation: 'landscape' | 'portrait';
    frame_id: string | null;
    is_active: boolean;
    layout_config: LayoutConfig;
};

const S = 5.607;
const mm = (v: number) => v * S;
const toMm = (px: number) => Math.round((px / S) * 10) / 10;

// Build the element for a field with sensible defaults, keeping any existing element data.
function elementForField(field: FormField, index: number, existing?: AnyElement): AnyElement {
    if (field.type === 'photo') {
        if (existing && existing.type === 'photo') {
            return { ...existing, source: field.key };
        }
        return { type: 'photo', source: field.key, x: 2.5, y: 4, w: 16, h: 21, enabled: true };
    }
    if (existing && existing.type === 'field') {
        return { ...existing, label: field.label.toUpperCase(), source: field.key };
    }
    return {
        type: 'field',
        label: field.label.toUpperCase(),
        source: field.key,
        x: 3,
        y: 6 + index * 4,
        width: 48,
        labelWidth: 14,
        fontSize: 2.0,
        enabled: true,
    };
}

// Sync elements to the current field list — add new, drop removed, keep positions.
function syncElements(fields: FormField[], prev: Elements): Elements {
    const next: Elements = {};
    fields.forEach((field, i) => {
        next[field.key] = elementForField(field, i, prev[field.key]);
    });
    return next;
}

function buildInitialConfig(form: FormData, datasets: Dataset[]): LayoutConfig {
    const cfg = (form?.layout_config ?? {}) as Partial<LayoutConfig>;
    const fields = datasets.find((d) => d.id === form?.card_dataset_id)?.fields ?? [];
    const elements = syncElements(fields, (cfg.elements ?? {}) as Elements);
    return {
        ...cfg,
        orientation: cfg.orientation === 'portrait' ? 'portrait' : form?.orientation === 'portrait' ? 'portrait' : 'landscape',
        frame_id: (cfg.frame_id as string | null) ?? form?.frame_id ?? null,
        elements,
    };
}

function CardPreview({
    config,
    frames,
    selectedId,
    onSelect,
    onUpdate,
}: {
    config: LayoutConfig;
    frames: FrameItem[];
    selectedId: string | null;
    onSelect: (id: string | null) => void;
    onUpdate: (id: string, patch: Partial<AnyElement>) => void;
}) {
    const isPortrait = config.orientation === 'portrait';
    const cw = mm(isPortrait ? 54 : 85.6);
    const ch = mm(isPortrait ? 85.6 : 54);
    const selectedFrame = frames.find((f) => f.id === config.frame_id);

    return (
        <div
            className="relative overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-black/10"
            style={{
                width: cw,
                height: ch,
                background: selectedFrame
                    ? `url(${selectedFrame.image_url}) center/cover no-repeat`
                    : 'linear-gradient(135deg, #e6f4ea, #d4ecdc, #c7e6d1)',
            }}
            onPointerDown={(e) => {
                if (e.target === e.currentTarget) onSelect(null);
            }}
        >
            {Object.entries(config.elements).map(([id, el]) => {
                if (!el.enabled) return null;
                const selected = selectedId === id;
                const pos = { x: mm(el.x), y: mm(el.y) };

                if (el.type === 'field') {
                    return (
                        <Rnd
                            key={id}
                            bounds="parent"
                            size={{ width: mm(el.width), height: mm(el.fontSize) * 1.4 }}
                            position={pos}
                            enableResizing={{ left: true, right: true }}
                            onDragStart={() => onSelect(id)}
                            onDragStop={(_e, d) => onUpdate(id, { x: toMm(d.x), y: toMm(d.y) })}
                            onResizeStop={(_e, _dir, ref, _delta, p) => onUpdate(id, { width: toMm(ref.offsetWidth), x: toMm(p.x), y: toMm(p.y) })}
                            className={cn(selected && 'z-20')}
                        >
                            <div
                                onMouseDown={() => onSelect(id)}
                                className={cn(
                                    'flex h-full w-full cursor-move items-baseline whitespace-nowrap rounded-sm',
                                    selected ? 'outline outline-1 outline-emerald-500' : 'outline-dashed outline-1 outline-transparent hover:outline-emerald-400/50',
                                )}
                                style={{ fontFamily: "'Inter Tight', sans-serif", fontSize: mm(el.fontSize), fontWeight: 800, lineHeight: 1.25, color: '#0c1a12' }}
                            >
                                <span className="shrink-0" style={{ width: mm(el.labelWidth) }}>
                                    {el.label}
                                </span>
                                <span className="shrink-0 px-[2px]">:</span>
                                <span className="overflow-hidden font-bold text-ellipsis">XXXXXX</span>
                            </div>
                        </Rnd>
                    );
                }

                // photo
                return (
                    <Rnd
                        key={id}
                        bounds="parent"
                        size={{ width: mm(el.w), height: mm(el.h) }}
                        position={pos}
                        lockAspectRatio
                        onDragStart={() => onSelect(id)}
                        onDragStop={(_e, d) => onUpdate(id, { x: toMm(d.x), y: toMm(d.y) })}
                        onResizeStop={(_e, _dir, ref, _delta, p) =>
                            onUpdate(id, { w: toMm(ref.offsetWidth), h: toMm(ref.offsetHeight), x: toMm(p.x), y: toMm(p.y) })
                        }
                        className={cn(selected && 'z-20')}
                    >
                        <div
                            onMouseDown={() => onSelect(id)}
                            className={cn(
                                'flex size-full cursor-move items-center justify-center rounded bg-zinc-400/40 text-white/70',
                                selected ? 'outline outline-1 outline-emerald-500' : 'outline-dashed outline-1 outline-zinc-400/60',
                            )}
                        >
                            <User style={{ width: mm(el.w) * 0.4, height: mm(el.w) * 0.4 }} />
                        </div>
                    </Rnd>
                );
            })}
        </div>
    );
}

export default function KartuBebasLayoutEditor({ form, frames, datasets }: Props) {
    const isEditing = !!form?.id;
    const [selectedId, setSelectedId] = useState<string | null>(null);

    const inertiaForm = useForm<EditorFormData>({
        name: form?.name ?? '',
        card_dataset_id: form?.card_dataset_id ?? null,
        orientation: form?.orientation ?? 'landscape',
        frame_id: form?.frame_id ?? null,
        is_active: form?.is_active ?? true,
        layout_config: buildInitialConfig(form, datasets),
    });

    const { data, setData, errors, processing } = inertiaForm;
    const config = data.layout_config;

    function setConfig(patch: Partial<LayoutConfig>) {
        setData('layout_config', { ...config, ...patch });
    }

    function updateElement(id: string, patch: Partial<AnyElement>) {
        const el = config.elements[id];
        if (!el) return;
        setConfig({ elements: { ...config.elements, [id]: { ...el, ...patch } as AnyElement } });
    }

    // Re-sync designer elements when a different dataset is selected.
    function selectDataset(id: string | null) {
        const fields = datasets.find((d) => d.id === id)?.fields ?? [];
        setSelectedId(null);
        setData((prev) => ({
            ...prev,
            card_dataset_id: id,
            layout_config: { ...prev.layout_config, elements: syncElements(fields, prev.layout_config.elements) },
        }));
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        // Keep top-level orientation/frame in sync with the designer config.
        inertiaForm.transform((d) => ({
            ...d,
            orientation: config.orientation,
            frame_id: config.frame_id,
        }));
        if (isEditing) {
            inertiaForm.put(`/kartu-bebas/layouts/${form!.id}`, { preserveScroll: true });
        } else {
            inertiaForm.post('/kartu-bebas/layouts', { preserveScroll: true });
        }
    }

    const frameOptions = useMemo(() => frames, [frames]);
    const selected = selectedId ? config.elements[selectedId] : null;
    const hasDataset = !!data.card_dataset_id;

    return (
        <>
            <Head title={isEditing ? 'Edit Layout Kartu' : 'Buat Layout Kartu'} />
            <form onSubmit={handleSubmit} className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">
                        {isEditing ? 'Edit Layout Kartu' : 'Buat Layout Kartu Baru'}
                    </h1>
                    <Button type="submit" disabled={processing} className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                        {processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                        Simpan Layout
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_400px]">
                    {/* LEFT: card designer */}
                    <div className="space-y-4">
                        <Card className="border-emerald-200 dark:border-emerald-900">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base text-emerald-700 dark:text-emerald-400">
                                    <Eye className="size-4" /> Desain Kartu
                                    <span className="text-muted-foreground text-xs font-normal">— seret elemen untuk mengatur posisi</span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex justify-center py-4">
                                    {!hasDataset ? (
                                        <p className="text-muted-foreground py-16 text-center text-sm">Pilih Format Data terlebih dahulu di panel kanan.</p>
                                    ) : (
                                        <CardPreview config={config} frames={frames} selectedId={selectedId} onSelect={setSelectedId} onUpdate={updateElement} />
                                    )}
                                </div>
                                <p className="text-muted-foreground mt-3 text-center text-xs">
                                    {config.orientation === 'portrait' ? '54 × 85.6 mm · Portrait' : '85.6 × 54 mm · Landscape'} · ATM Card Size
                                </p>
                            </CardContent>
                        </Card>

                        {selected && (
                            <Card className="border-emerald-200 dark:border-emerald-900">
                                <CardHeader>
                                    <CardTitle className="text-base">Atur Elemen Terpilih</CardTitle>
                                </CardHeader>
                                <CardContent className="grid grid-cols-2 gap-2">
                                    <NumField label="X (mm)" value={selected.x} onChange={(v) => updateElement(selectedId!, { x: v } as Partial<AnyElement>)} />
                                    <NumField label="Y (mm)" value={selected.y} onChange={(v) => updateElement(selectedId!, { y: v } as Partial<AnyElement>)} />
                                    {selected.type === 'field' && (
                                        <>
                                            <NumField label="Lebar (mm)" value={selected.width} onChange={(v) => updateElement(selectedId!, { width: v } as Partial<AnyElement>)} />
                                            <NumField label="Titik Dua (mm)" value={selected.labelWidth} onChange={(v) => updateElement(selectedId!, { labelWidth: v } as Partial<AnyElement>)} />
                                            <NumField label="Font (mm)" step={0.1} value={selected.fontSize} onChange={(v) => updateElement(selectedId!, { fontSize: v } as Partial<AnyElement>)} />
                                        </>
                                    )}
                                    {selected.type === 'photo' && (
                                        <>
                                            <NumField label="Lebar (mm)" value={selected.w} onChange={(v) => updateElement(selectedId!, { w: v } as Partial<AnyElement>)} />
                                            <NumField label="Tinggi (mm)" value={selected.h} onChange={(v) => updateElement(selectedId!, { h: v } as Partial<AnyElement>)} />
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* RIGHT: settings */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Informasi Layout</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid gap-1.5">
                                    <Label>Nama Layout</Label>
                                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Kartu Peserta Lomba" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(c) => setData('is_active', c === true)} />
                                    <Label htmlFor="is_active">Layout aktif (dapat diisi publik)</Label>
                                </div>
                                {form?.public_url && (
                                    <div className="grid gap-1.5">
                                        <Label className="text-muted-foreground text-[11px]">Tautan Publik</Label>
                                        <code className="truncate rounded-md bg-emerald-50 px-2 py-1.5 text-[11px] dark:bg-emerald-950/40">{form.public_url}</code>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Orientasi & Frame</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid gap-1.5">
                                    <Label>Format Data</Label>
                                    <Select value={data.card_dataset_id ?? ''} onValueChange={(v) => selectDataset(v || null)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih format data" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {datasets.map((d) => (
                                                <SelectItem key={d.id} value={d.id}>
                                                    {d.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.card_dataset_id} />
                                    {!hasDataset && <p className="text-[11px] text-red-500">Format data wajib dipilih.</p>}
                                </div>
                                <div className="grid grid-cols-2 gap-2">
                                    {(
                                        [
                                            { v: 'landscape', label: 'Landscape', Icon: RectangleHorizontal },
                                            { v: 'portrait', label: 'Portrait', Icon: RectangleVertical },
                                        ] as const
                                    ).map(({ v, label, Icon }) => (
                                        <button
                                            key={v}
                                            type="button"
                                            onClick={() => {
                                                setData('orientation', v);
                                                setConfig({ orientation: v });
                                            }}
                                            className={cn(
                                                'flex flex-col items-center gap-1.5 rounded-lg border p-3 text-xs font-medium transition',
                                                config.orientation === v
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                    : 'border-border hover:bg-muted',
                                            )}
                                        >
                                            <Icon className="size-5" /> {label}
                                        </button>
                                    ))}
                                </div>
                                <div className="grid gap-1.5">
                                    <Label>Frame</Label>
                                    <Select
                                        value={String(config.frame_id ?? 'none')}
                                        onValueChange={(v) => {
                                            const val = v === 'none' ? null : v;
                                            setData('frame_id', val);
                                            setConfig({ frame_id: val });
                                        }}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Tanpa frame" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">Tanpa Frame</SelectItem>
                                            {frameOptions.map((f) => (
                                                <SelectItem key={f.id} value={String(f.id)}>
                                                    {f.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>
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

KartuBebasLayoutEditor.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Layout Kartu', href: '/kartu-bebas/layouts' },
            { title: 'Editor', href: '/kartu-bebas/layouts' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
