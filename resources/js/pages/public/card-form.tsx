import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, Image as ImageIcon, Loader2, Sparkles } from 'lucide-react';
import { type FormEvent, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

type FieldType = 'text' | 'date' | 'number' | 'select' | 'photo';

type FormField = {
    key: string;
    label: string;
    type: FieldType;
    required: boolean;
    options?: string[];
};

type Props = {
    form: { name: string; token: string; fields: FormField[] };
    result: { card_url: string; download_url: string | null } | null;
};

export default function PublicCardForm({ form, result }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);

    const initialData: Record<string, string | File | null> = {};
    form.fields.forEach((f) => {
        initialData[f.key] = f.type === 'photo' ? null : '';
    });

    const { data, setData, post, processing, errors } = useForm<{ data: Record<string, string | File | null> }>({
        data: initialData,
    });

    function setField(key: string, value: string | File | null) {
        setData('data', { ...data.data, [key]: value });
    }

    function onPhotoChange(key: string, file: File | null) {
        setField(key, file);
        setPhotoPreview(file ? URL.createObjectURL(file) : null);
    }

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post(`/f/${form.token}`, { forceFormData: true, preserveScroll: true });
    }

    const errorFor = (key: string) => (errors as Record<string, string>)[`data.${key}`];

    if (result) {
        return (
            <>
                <Head title={`${form.name} — Selesai`} />
                <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl ring-1 ring-emerald-100">
                        <div className="mb-4 flex flex-col items-center text-center">
                            <CheckCircle2 className="mb-2 size-12 text-emerald-500" />
                            <h1 className="text-xl font-bold text-emerald-700">Kartu Berhasil Dibuat!</h1>
                            <p className="text-muted-foreground mt-1 text-sm">Berikut kartu Anda. Silakan unduh.</p>
                        </div>
                        <div className="mb-4 overflow-hidden rounded-xl border bg-zinc-50 p-3">
                            <img src={result.card_url} alt="Kartu" className="mx-auto max-h-80 w-auto rounded-md shadow" />
                        </div>
                        <a href={result.download_url ?? result.card_url} target="_blank" rel="noopener noreferrer" download>
                            <Button className="w-full gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                                <Download className="size-4" /> Unduh Kartu
                            </Button>
                        </a>
                        <Button variant="ghost" className="mt-2 w-full" onClick={() => (window.location.href = `/f/${form.token}`)}>
                            Isi Lagi
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={form.name} />
            <div className="flex min-h-screen items-start justify-center bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-4 py-10">
                <div className="w-full max-w-lg">
                    <div className="mb-6 flex flex-col items-center text-center">
                        <div className="mb-3 flex size-12 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-600">
                            <Sparkles className="size-6" />
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight text-emerald-700">{form.name}</h1>
                        <p className="text-muted-foreground mt-1 text-sm">Isi data berikut untuk membuat kartu Anda.</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-emerald-100">
                        {form.fields.map((field) => (
                            <div key={field.key} className="grid gap-1.5">
                                <Label>
                                    {field.label}
                                    {field.required && <span className="ml-0.5 text-red-500">*</span>}
                                </Label>

                                {field.type === 'text' && (
                                    <Input
                                        value={(data.data[field.key] as string) ?? ''}
                                        onChange={(e) => setField(field.key, e.target.value)}
                                        required={field.required}
                                    />
                                )}

                                {field.type === 'number' && (
                                    <Input
                                        type="number"
                                        value={(data.data[field.key] as string) ?? ''}
                                        onChange={(e) => setField(field.key, e.target.value)}
                                        required={field.required}
                                    />
                                )}

                                {field.type === 'date' && (
                                    <Input
                                        type="date"
                                        value={(data.data[field.key] as string) ?? ''}
                                        onChange={(e) => setField(field.key, e.target.value)}
                                        required={field.required}
                                    />
                                )}

                                {field.type === 'select' && (
                                    <Select value={(data.data[field.key] as string) || ''} onValueChange={(v) => setField(field.key, v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih…" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {(field.options ?? []).map((opt) => (
                                                <SelectItem key={opt} value={opt}>
                                                    {opt}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}

                                {field.type === 'photo' && (
                                    <div>
                                        <input
                                            ref={fileRef}
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            onChange={(e) => onPhotoChange(field.key, e.target.files?.[0] ?? null)}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => fileRef.current?.click()}
                                            className={cn(
                                                'flex w-full flex-col items-center gap-2 rounded-xl border-2 border-dashed p-6 text-center transition',
                                                photoPreview ? 'border-emerald-300 bg-emerald-50/40' : 'border-zinc-300 hover:border-emerald-400 hover:bg-emerald-50/30',
                                            )}
                                        >
                                            {photoPreview ? (
                                                <img src={photoPreview} alt="Preview" className="h-40 w-auto rounded-lg object-cover shadow" />
                                            ) : (
                                                <>
                                                    <ImageIcon className="size-8 text-emerald-400" />
                                                    <span className="text-muted-foreground text-sm">Ketuk untuk pilih foto</span>
                                                </>
                                            )}
                                        </button>
                                    </div>
                                )}

                                {errorFor(field.key) && <p className="text-xs text-red-500">{errorFor(field.key)}</p>}
                            </div>
                        ))}

                        <Button type="submit" disabled={processing} className="w-full gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                            {processing ? <Loader2 className="size-4 animate-spin" /> : <Sparkles className="size-4" />}
                            {processing ? 'Membuat Kartu…' : 'Buat Kartu'}
                        </Button>
                    </form>

                    <p className="text-muted-foreground mt-4 text-center text-xs">Ditenagai oleh sistem kartu dinamis.</p>
                </div>
            </div>
        </>
    );
}
