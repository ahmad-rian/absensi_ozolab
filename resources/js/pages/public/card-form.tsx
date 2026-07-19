import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, Image as ImageIcon, Loader2, Sparkles, XCircle } from 'lucide-react';
import { type FormEvent, useEffect, useRef, useState } from 'react';
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

type SubmissionResult = {
    submission_id: string;
    status: 'processing' | 'completed' | 'failed';
    card_url: string | null;
    download_url: string | null;
};

type Props = {
    form: { name: string; token: string; fields: FormField[] };
    result: SubmissionResult | null;
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
        return <CardResult token={form.token} formName={form.name} initial={result} />;
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

function CardResult({ token, formName, initial }: { token: string; formName: string; initial: SubmissionResult }) {
    const [state, setState] = useState<SubmissionResult>(initial);

    useEffect(() => {
        if (state.status !== 'processing') {
            return;
        }

        let cancelled = false;
        let inFlight = false;

        const poll = async () => {
            if (inFlight) {
                return;
            }
            inFlight = true;
            try {
                const res = await fetch(`/f/${token}/status/${state.submission_id}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) {
                    return;
                }
                const json = (await res.json()) as Pick<SubmissionResult, 'status' | 'card_url' | 'download_url'>;
                if (!cancelled && json.status !== 'processing') {
                    setState((prev) => ({ ...prev, ...json }));
                }
            } catch {
                // ignore transient network errors; keep polling
            } finally {
                inFlight = false;
            }
        };

        const interval = window.setInterval(poll, 2500);

        return () => {
            cancelled = true;
            window.clearInterval(interval);
        };
    }, [state.status, state.submission_id, token]);

    if (state.status === 'processing') {
        return (
            <>
                <Head title={`${formName} — Memproses`} />
                <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl ring-1 ring-emerald-100">
                        <div className="flex flex-col items-center text-center">
                            <Loader2 className="mb-3 size-12 animate-spin text-emerald-500" />
                            <h1 className="text-xl font-bold text-emerald-700">Kartu sedang dibuat…</h1>
                            <p className="text-muted-foreground mt-1 text-sm">Mohon tunggu sebentar, halaman ini akan diperbarui otomatis.</p>
                        </div>
                    </div>
                </div>
            </>
        );
    }

    if (state.status === 'failed') {
        return (
            <>
                <Head title={`${formName} — Gagal`} />
                <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-rose-50 via-white to-red-50 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl ring-1 ring-rose-100">
                        <div className="mb-4 flex flex-col items-center text-center">
                            <XCircle className="mb-2 size-12 text-rose-500" />
                            <h1 className="text-xl font-bold text-rose-700">Gagal Membuat Kartu</h1>
                            <p className="text-muted-foreground mt-1 text-sm">Terjadi kesalahan saat membuat kartu Anda. Silakan coba lagi.</p>
                        </div>
                        <Button
                            className="w-full gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                            onClick={() => (window.location.href = `/f/${token}`)}
                        >
                            Coba Lagi
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`${formName} — Selesai`} />
            <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-emerald-50 via-white to-teal-50 p-4">
                <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl ring-1 ring-emerald-100">
                    <div className="mb-4 flex flex-col items-center text-center">
                        <CheckCircle2 className="mb-2 size-12 text-emerald-500" />
                        <h1 className="text-xl font-bold text-emerald-700">Kartu Berhasil Dibuat!</h1>
                        <p className="text-muted-foreground mt-1 text-sm">Berikut kartu Anda. Silakan unduh.</p>
                    </div>
                    {state.card_url && (
                        <div className="mb-4 overflow-hidden rounded-xl border bg-zinc-50 p-3">
                            <img src={state.card_url} alt="Kartu" className="mx-auto max-h-80 w-auto rounded-md shadow" />
                        </div>
                    )}
                    <a href={state.download_url ?? state.card_url ?? '#'} target="_blank" rel="noopener noreferrer" download>
                        <Button className="w-full gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                            <Download className="size-4" /> Unduh Kartu
                        </Button>
                    </a>
                    <Button variant="ghost" className="mt-2 w-full" onClick={() => (window.location.href = `/f/${token}`)}>
                        Isi Lagi
                    </Button>
                </div>
            </div>
        </>
    );
}
