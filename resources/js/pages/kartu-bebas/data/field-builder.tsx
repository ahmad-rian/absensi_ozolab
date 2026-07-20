import { ArrowDown, ArrowUp, GripVertical, Plus, Trash2, X } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export type FieldType = 'text' | 'date' | 'number' | 'select' | 'photo';

export type FormField = {
    key: string;
    label: string;
    type: FieldType;
    required: boolean;
    options?: string[];
};

export const FIELD_TYPE_LABELS: Record<FieldType, string> = {
    text: 'Teks',
    date: 'Tanggal',
    number: 'Angka',
    select: 'Pilihan',
    photo: 'Foto',
};

export function slugifyKey(label: string): string {
    return label
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[^a-z0-9\s_]/g, '')
        .trim()
        .replace(/\s+/g, '_')
        .slice(0, 64);
}

type Props = {
    name: string;
    fields: FormField[];
    errors: Record<string, string>;
    onName: (name: string) => void;
    onFields: (fields: FormField[]) => void;
};

/** Reusable "Format Data" builder: format name + dynamic field list. */
export default function FieldBuilder({ name, fields, errors, onName, onFields }: Props) {
    function addField() {
        const base = 'field';
        let n = fields.length + 1;
        let key = `${base}_${n}`;
        const existing = new Set(fields.map((f) => f.key));
        while (existing.has(key)) {
            n += 1;
            key = `${base}_${n}`;
        }
        onFields([...fields, { key, label: `Field ${n}`, type: 'text', required: false }]);
    }

    function updateField(index: number, patch: Partial<FormField>) {
        onFields(
            fields.map((f, i) => {
                if (i !== index) {
                    return f;
                }
                const merged = { ...f, ...patch };
                if (patch.label !== undefined) {
                    const newKey = slugifyKey(patch.label) || f.key;
                    const clash = fields.some((o, oi) => oi !== index && o.key === newKey);
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
        onFields(fields.filter((_, i) => i !== index));
    }

    function moveField(index: number, dir: -1 | 1) {
        const target = index + dir;
        if (target < 0 || target >= fields.length) {
            return;
        }
        const next = [...fields];
        [next[index], next[target]] = [next[target], next[index]];
        onFields(next);
    }

    return (
        <div className="space-y-5">
            <div className="grid gap-1.5">
                <Label>Nama Format</Label>
                <Input value={name} onChange={(e) => onName(e.target.value)} placeholder="Kartu Peserta Lomba" />
                <InputError message={errors.name} />
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between gap-2">
                    <Label>Field</Label>
                    <Button type="button" size="sm" onClick={addField} className="gap-1.5 bg-emerald-600 text-white hover:bg-emerald-700">
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
                            <OptionEditor options={field.options ?? []} onChange={(opts) => updateField(i, { options: opts })} />
                        )}
                    </div>
                ))}
            </div>
        </div>
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
