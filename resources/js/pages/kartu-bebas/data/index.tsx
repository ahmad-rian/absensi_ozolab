import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Database, GripVertical, Loader2, Pencil, Plus, Trash2, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';

type FieldType = 'text' | 'date' | 'number' | 'select' | 'photo';

type FormField = {
    key: string;
    label: string;
    type: FieldType;
    required: boolean;
    options?: string[];
};

type Dataset = {
    id: string;
    name: string;
    fields: FormField[];
    layouts_count: number;
    created_at: string | null;
};

type Props = { datasets: Dataset[] };

const FIELD_TYPE_LABELS: Record<FieldType, string> = {
    text: 'Teks',
    date: 'Tanggal',
    number: 'Angka',
    select: 'Pilihan',
    photo: 'Foto',
};

function slugifyKey(label: string): string {
    return label
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^a-z0-9\s_]/g, '')
        .trim()
        .replace(/\s+/g, '_')
        .slice(0, 64);
}

type Errors = Record<string, string>;

export default function KartuBebasDataIndex({ datasets }: Props) {
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [name, setName] = useState('');
    const [fields, setFields] = useState<FormField[]>([]);
    const [errors, setErrors] = useState<Errors>({});
    const [processing, setProcessing] = useState(false);

    function openCreate() {
        setEditingId(null);
        setName('');
        setFields([]);
        setErrors({});
        setOpen(true);
    }

    function openEdit(dataset: Dataset) {
        setEditingId(dataset.id);
        setName(dataset.name);
        setFields(
            dataset.fields.map((f) => ({
                key: f.key,
                label: f.label,
                type: f.type,
                required: !!f.required,
                ...(f.type === 'select' ? { options: f.options ?? [] } : {}),
            })),
        );
        setErrors({});
        setOpen(true);
    }

    // --- Field builder operations (ported from layouts/editor.tsx) ---
    function addField() {
        const base = 'field';
        let n = fields.length + 1;
        let key = `${base}_${n}`;
        const existing = new Set(fields.map((f) => f.key));
        while (existing.has(key)) {
            n += 1;
            key = `${base}_${n}`;
        }
        setFields([...fields, { key, label: `Field ${n}`, type: 'text', required: false }]);
    }

    function updateField(index: number, patch: Partial<FormField>) {
        setFields((prev) =>
            prev.map((f, i) => {
                if (i !== index) {
                    return f;
                }
                const merged = { ...f, ...patch };
                // Re-slug the key from the label unless the user is editing the key elsewhere.
                if (patch.label !== undefined) {
                    const newKey = slugifyKey(patch.label) || f.key;
                    const clash = prev.some((o, oi) => oi !== index && o.key === newKey);
                    merged.key = clash ? f.key : newKey || f.key;
                }
                if (patch.type && patch.type !== 'select') {
                    delete merged.options;
                }
                if (patch.type === 'select' && !merged.options) {
                    merged.options = ['Opsi 1'];
                }
                return merged;
            }),
        );
    }

    function removeField(index: number) {
        setFields((prev) => prev.filter((_, i) => i !== index));
    }

    function moveField(index: number, dir: -1 | 1) {
        const target = index + dir;
        if (target < 0 || target >= fields.length) {
            return;
        }
        setFields((prev) => {
            const next = [...prev];
            [next[index], next[target]] = [next[target], next[index]];
            return next;
        });
    }

    function updateOptions(index: number, options: string[]) {
        updateField(index, { options });
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const payload = { name, fields };
        const options = {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: (errs: Errors) => setErrors(errs),
            onFinish: () => setProcessing(false),
        };

        if (editingId) {
            router.put(`/kartu-bebas/data/${editingId}`, payload, options);
        } else {
            router.post('/kartu-bebas/data', payload, options);
        }
    }

    function handleDelete(dataset: Dataset) {
        if (!confirm(`Hapus format "${dataset.name}"?`)) {
            return;
        }
        router.delete(`/kartu-bebas/data/${dataset.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Format Data" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">Data / Format Data</h1>
                        <p className="text-muted-foreground text-sm">
                            Buat format field kartu (bisa macam-macam). Layout nanti tinggal pilih format ini.
                        </p>
                    </div>
                    <Button onClick={openCreate} className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                        <Plus className="size-4" /> Buat Format
                    </Button>
                </div>

                {datasets.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <Database className="mb-4 size-12 text-emerald-500/70" />
                            <p className="text-muted-foreground text-sm">Belum ada format data. Buat format dulu.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {datasets.map((dataset) => (
                            <Card key={dataset.id}>
                                <CardContent className="p-5">
                                    <div className="mb-3 flex items-start gap-2">
                                        <Database className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                        <h3 className="font-semibold break-words">{dataset.name}</h3>
                                    </div>
                                    <div className="text-muted-foreground mb-4 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                        <span>{dataset.fields.length} field</span>
                                        <span>·</span>
                                        <span>{dataset.layouts_count} layout pakai</span>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" className="flex-1" onClick={() => openEdit(dataset)}>
                                            <Pencil className="mr-1 size-3" /> Edit
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => handleDelete(dataset)}>
                                            <Trash2 className="size-4 text-red-500" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingId ? 'Edit Format Data' : 'Buat Format Data'}</DialogTitle>
                        <DialogDescription>Tentukan nama format dan daftar field yang akan diisi.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid gap-1.5">
                            <Label>Nama Format</Label>
                            <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="Kartu Peserta Lomba" />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between gap-2">
                                <Label>Field</Label>
                                <Button
                                    type="button"
                                    size="sm"
                                    onClick={addField}
                                    className="gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700"
                                >
                                    <Plus className="size-3.5" /> Tambah Field
                                </Button>
                            </div>

                            {fields.length === 0 && <p className="text-muted-foreground text-sm">Belum ada field. Klik “Tambah Field”.</p>}
                            {typeof errors.fields === 'string' && <InputError message={errors.fields} />}

                            {fields.map((field, i) => (
                                <div key={i} className="space-y-2 rounded-lg border border-border p-3">
                                    <div className="flex items-center gap-2">
                                        <GripVertical className="text-muted-foreground size-4 shrink-0" />
                                        <Input
                                            value={field.label}
                                            onChange={(e) => updateField(i, { label: e.target.value })}
                                            placeholder="Label field"
                                            className="h-8 flex-1"
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 shrink-0"
                                            onClick={() => moveField(i, -1)}
                                            disabled={i === 0}
                                        >
                                            <ArrowUp className="size-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 shrink-0"
                                            onClick={() => moveField(i, 1)}
                                            disabled={i === fields.length - 1}
                                        >
                                            <ArrowDown className="size-3.5" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-8 shrink-0"
                                            onClick={() => removeField(i)}
                                        >
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
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing} className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                                {processing && <Loader2 className="size-4 animate-spin" />}
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function OptionEditor({ options, onChange }: { options: string[]; onChange: (opts: string[]) => void }) {
    return (
        <div className="bg-muted/40 space-y-1.5 rounded-md p-2">
            <Label className="text-muted-foreground text-[10px]">Opsi Pilihan</Label>
            {options.map((opt, i) => (
                <div key={i} className="flex items-center gap-1.5">
                    <Input
                        value={opt}
                        onChange={(e) => onChange(options.map((o, oi) => (oi === i ? e.target.value : o)))}
                        className="h-7 text-xs"
                        placeholder={`Opsi ${i + 1}`}
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-7 shrink-0"
                        onClick={() => onChange(options.filter((_, oi) => oi !== i))}
                    >
                        <X className="size-3" />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="h-7 w-full gap-1 text-xs"
                onClick={() => onChange([...options, `Opsi ${options.length + 1}`])}
            >
                <Plus className="size-3" /> Tambah Opsi
            </Button>
        </div>
    );
}

KartuBebasDataIndex.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Data', href: '/kartu-bebas/data' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
