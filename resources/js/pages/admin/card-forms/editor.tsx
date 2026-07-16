import { Head, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Eye, GripVertical, Loader2, Plus, RectangleHorizontal, RectangleVertical, Save, Trash2, User, X } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
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

type FieldType = 'text' | 'date' | 'number' | 'select' | 'photo';

type FormField = {
    key: string;
    label: string;
    type: FieldType;
    required: boolean;
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

type FormData = {
    id?: string;
    name: string;
    token?: string;
    fields: FormField[];
    orientation: 'landscape' | 'portrait';
    frame_id: string | null;
    is_active: boolean;
    layout_config: LayoutConfig;
    public_url?: string;
} | null;

type Props = { form: FormData; frames: FrameItem[] };

type EditorFormData = {
    name: string;
    fields: FormField[];
    orientation: 'landscape' | 'portrait';
    frame_id: string | null;
    is_active: boolean;
    layout_config: LayoutConfig;
};

const S = 5.607;
const mm = (v: number) => v * S;
const toMm = (px: number) => Math.round((px / S) * 10) / 10;

function slugifyKey(label: string): string {
    return label
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^a-z0-9\s_]/g, '')
        .trim()
        .replace(/\s+/g, '_')
        .slice(0, 64);
}

const FIELD_TYPE_LABELS: Record<FieldType, string> = {
    text: 'Teks',
    date: 'Tanggal',
    number: 'Angka',
    select: 'Pilihan',
    photo: 'Foto',
};

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

function buildInitialConfig(form: FormData): LayoutConfig {
    const cfg = (form?.layout_config ?? {}) as Partial<LayoutConfig>;
    const fields = form?.fields ?? [];
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

export default function CardFormEditor({ form, frames }: Props) {
    const isEditing = !!form?.id;
    const [selectedId, setSelectedId] = useState<string | null>(null);

    const inertiaForm = useForm<EditorFormData>({
        name: form?.name ?? '',
        fields: form?.fields ?? [],
        orientation: form?.orientation ?? 'landscape',
        frame_id: form?.frame_id ?? null,
        is_active: form?.is_active ?? true,
        layout_config: buildInitialConfig(form),
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

    // --- Field builder operations ---
    function commitFields(fields: FormField[]) {
        setData((prev) => ({
            ...prev,
            fields,
            layout_config: { ...prev.layout_config, elements: syncElements(fields, prev.layout_config.elements) },
        }));
    }

    function addField() {
        const base = 'field';
        let n = data.fields.length + 1;
        let key = `${base}_${n}`;
        const existing = new Set(data.fields.map((f) => f.key));
        while (existing.has(key)) {
            n += 1;
            key = `${base}_${n}`;
        }
        commitFields([...data.fields, { key, label: `Field ${n}`, type: 'text', required: false }]);
    }

    function updateField(index: number, patch: Partial<FormField>) {
        const fields = data.fields.map((f, i) => {
            if (i !== index) return f;
            const merged = { ...f, ...patch };
            // Re-slug the key from the label unless the user is editing the key elsewhere.
            if (patch.label !== undefined) {
                const newKey = slugifyKey(patch.label) || f.key;
                const clash = data.fields.some((o, oi) => oi !== index && o.key === newKey);
                merged.key = clash ? f.key : newKey || f.key;
            }
            if (patch.type && patch.type !== 'select') {
                delete merged.options;
            }
            if (patch.type === 'select' && !merged.options) {
                merged.options = ['Opsi 1'];
            }
            return merged;
        });
        commitFields(fields);
    }

    function removeField(index: number) {
        commitFields(data.fields.filter((_, i) => i !== index));
    }

    function moveField(index: number, dir: -1 | 1) {
        const target = index + dir;
        if (target < 0 || target >= data.fields.length) return;
        const fields = [...data.fields];
        [fields[index], fields[target]] = [fields[target], fields[index]];
        commitFields(fields);
    }

    function updateOptions(index: number, options: string[]) {
        updateField(index, { options });
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
            inertiaForm.put(`/admin/card-forms/${form!.id}`, { preserveScroll: true });
        } else {
            inertiaForm.post('/admin/card-forms', { preserveScroll: true });
        }
    }

    const frameOptions = useMemo(() => frames, [frames]);
    const selected = selectedId ? config.elements[selectedId] : null;

    return (
        <>
            <Head title={isEditing ? 'Edit Form Kartu' : 'Buat Form Kartu'} />
            <form onSubmit={handleSubmit} className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">
                        {isEditing ? 'Edit Form Kartu' : 'Buat Form Kartu Baru'}
                    </h1>
                    <Button type="submit" disabled={processing} className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                        {processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                        Simpan Form
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
                                    {data.fields.length === 0 ? (
                                        <p className="text-muted-foreground py-16 text-center text-sm">Tambahkan field terlebih dahulu di panel kanan.</p>
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

                    {/* RIGHT: settings + field builder */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Informasi Form</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="grid gap-1.5">
                                    <Label>Nama Form</Label>
                                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Kartu Peserta Lomba" />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(c) => setData('is_active', c === true)} />
                                    <Label htmlFor="is_active">Form aktif (dapat diisi publik)</Label>
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

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between gap-2">
                                <CardTitle className="text-base">Field Form</CardTitle>
                                <Button type="button" size="sm" onClick={addField} className="gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700">
                                    <Plus className="size-3.5" /> Tambah
                                </Button>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {data.fields.length === 0 && <p className="text-muted-foreground text-sm">Belum ada field. Klik “Tambah”.</p>}
                                {typeof errors.fields === 'string' && <InputError message={errors.fields} />}
                                {data.fields.map((field, i) => (
                                    <div
                                        key={i}
                                        className={cn(
                                            'space-y-2 rounded-lg border p-3',
                                            selectedId === field.key ? 'border-emerald-400 bg-emerald-50/40 dark:bg-emerald-950/20' : 'border-border',
                                        )}
                                    >
                                        <div className="flex items-center gap-2">
                                            <GripVertical className="text-muted-foreground size-4 shrink-0" />
                                            <Input
                                                value={field.label}
                                                onChange={(e) => updateField(i, { label: e.target.value })}
                                                placeholder="Label field"
                                                className="h-8 flex-1"
                                                onFocus={() => setSelectedId(field.key)}
                                            />
                                            <Button type="button" variant="ghost" size="icon" className="size-8 shrink-0" onClick={() => moveField(i, -1)} disabled={i === 0}>
                                                <ArrowUp className="size-3.5" />
                                            </Button>
                                            <Button type="button" variant="ghost" size="icon" className="size-8 shrink-0" onClick={() => moveField(i, 1)} disabled={i === data.fields.length - 1}>
                                                <ArrowDown className="size-3.5" />
                                            </Button>
                                            <Button type="button" variant="ghost" size="icon" className="size-8 shrink-0" onClick={() => removeField(i)}>
                                                <Trash2 className="size-3.5 text-red-500" />
                                            </Button>
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <Select value={field.type} onValueChange={(v) => updateField(i, { type: v as FieldType })}>
                                                <SelectTrigger className="h-8">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {(Object.keys(FIELD_TYPE_LABELS) as FieldType[]).map((t) => (
                                                        <SelectItem key={t} value={t}>
                                                            {FIELD_TYPE_LABELS[t]}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <label className="flex items-center gap-2 text-xs">
                                                <Checkbox checked={field.required} onCheckedChange={(c) => updateField(i, { required: c === true })} />
                                                Wajib diisi
                                            </label>
                                        </div>
                                        <p className="text-muted-foreground text-[10px]">
                                            key: <code>{field.key}</code>
                                        </p>
                                        {field.type === 'select' && (
                                            <OptionEditor options={field.options ?? []} onChange={(opts) => updateOptions(i, opts)} />
                                        )}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </form>
        </>
    );
}

function OptionEditor({ options, onChange }: { options: string[]; onChange: (opts: string[]) => void }) {
    return (
        <div className="space-y-1.5 rounded-md bg-muted/40 p-2">
            <Label className="text-muted-foreground text-[10px]">Opsi Pilihan</Label>
            {options.map((opt, i) => (
                <div key={i} className="flex items-center gap-1.5">
                    <Input
                        value={opt}
                        onChange={(e) => onChange(options.map((o, oi) => (oi === i ? e.target.value : o)))}
                        className="h-7 text-xs"
                        placeholder={`Opsi ${i + 1}`}
                    />
                    <Button type="button" variant="ghost" size="icon" className="size-7 shrink-0" onClick={() => onChange(options.filter((_, oi) => oi !== i))}>
                        <X className="size-3" />
                    </Button>
                </div>
            ))}
            <Button type="button" variant="outline" size="sm" className="h-7 w-full gap-1 text-xs" onClick={() => onChange([...options, `Opsi ${options.length + 1}`])}>
                <Plus className="size-3" /> Tambah Opsi
            </Button>
        </div>
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

CardFormEditor.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Form Kartu', href: '/admin/card-forms' },
        { title: 'Editor' },
    ],
};
