import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, Image as ImageIcon, Loader2, Sparkles, XCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CardCropReposition  } from '@/components/shared/card-crop-reposition';
import type {CropRect} from '@/components/shared/card-crop-reposition';
import { StepProgress  } from '@/components/shared/step-progress';
import type {WizardStep} from '@/components/shared/step-progress';
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

const FIELDS_PER_STEP = 4;

/** Chunk fields into steps; a photo field gets its own dedicated step. */
function buildFieldSteps(fields: FormField[]): FormField[][] {
    const chunks: FormField[][] = [];
    let current: FormField[] = [];

    for (const field of fields) {
        if (field.type === 'photo') {
            if (current.length > 0) {
                chunks.push(current);
                current = [];
            }

            chunks.push([field]);

            continue;
        }

        current.push(field);

        if (current.length >= FIELDS_PER_STEP) {
            chunks.push(current);
            current = [];
        }
    }

    if (current.length > 0) {
        chunks.push(current);
    }

    return chunks;
}

export default function PublicCardForm({ form, result }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [manualCrop, setManualCrop] = useState<CropRect | null>(null);
    const [step, setStep] = useState(1);
    const [stepErrors, setStepErrors] = useState<Record<string, string>>({});

    const fieldSteps = useMemo(() => buildFieldSteps(form.fields), [form.fields]);

    const steps = useMemo<WizardStep[]>(() => {
        const fieldStepDefs = fieldSteps.map((chunk, i) => ({
            id: i + 1,
            title: chunk.length === 1 && chunk[0].type === 'photo' ? 'Foto' : `Data ${i + 1}`,
        }));

        return [...fieldStepDefs, { id: fieldSteps.length + 1, title: 'Review & Buat' }];
    }, [fieldSteps]);

    const totalSteps = steps.length;
    const reviewStep = totalSteps;

    const initialData: Record<string, string | File | null> = {};
    form.fields.forEach((f) => {
        initialData[f.key] = f.type === 'photo' ? null : '';
    });

    const { data, setData, post, processing, errors, transform } = useForm<{
        data: Record<string, string | File | null>;
        manual_crop: CropRect | null;
    }>({
        data: initialData,
        manual_crop: null,
    });

    function setField(key: string, value: string | File | null) {
        setData('data', { ...data.data, [key]: value });
    }

    function onPhotoChange(key: string, file: File | null) {
        setField(key, file);
        setManualCrop(null);
        setPhotoPreview((prev) => {
            if (prev) {
                URL.revokeObjectURL(prev);
            }

            return file ? URL.createObjectURL(file) : null;
        });
    }

    function validateStep(current: number): Record<string, string> {
        const e: Record<string, string> = {};

        if (current === reviewStep) {
            return e;
        }

        const chunk = fieldSteps[current - 1] ?? [];

        for (const field of chunk) {
            if (!field.required) {
                continue;
            }

            const val = data.data[field.key];

            if (field.type === 'photo') {
                if (!val) {
                    e[field.key] = `${field.label} wajib diunggah.`;
                }
            } else if (!(val as string)?.toString().trim()) {
                e[field.key] = `${field.label} wajib diisi.`;
            }
        }

        return e;
    }

    function goNext() {
        const e = validateStep(step);

        if (Object.keys(e).length > 0) {
            setStepErrors(e);

            return;
        }

        setStepErrors({});
        setStep((s) => Math.min(totalSteps, s + 1));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function goBack() {
        setStepErrors({});
        setStep((s) => Math.max(1, s - 1));
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function handleSubmit() {
        for (let s = 1; s < reviewStep; s++) {
            const e = validateStep(s);

            if (Object.keys(e).length > 0) {
                setStep(s);
                setStepErrors(e);

                return;
            }
        }

        // Drop the null manual_crop so the backend's required_with rules don't trip.
        transform((payload) => {
            if (payload.manual_crop) {
                return payload;
            }

            return { data: payload.data };
        });

        post(`/f/${form.token}`, { forceFormData: true, preserveScroll: true });
    }

    // Keep the Inertia form's manual_crop in sync with the crop component.
    useEffect(() => {
        setData('manual_crop', manualCrop);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [manualCrop]);

    const errorFor = (key: string) => stepErrors[key] || (errors as Record<string, string>)[`data.${key}`];

    if (result) {
        return <CardResult token={form.token} formName={form.name} initial={result} />;
    }

    const onReviewStep = step === reviewStep;
    const currentChunk = fieldSteps[step - 1] ?? [];

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

                    <div className="mb-5">
                        <StepProgress steps={steps} current={step} />
                    </div>

                    <div className="space-y-4 rounded-2xl bg-white p-6 shadow-xl ring-1 ring-emerald-100">
                        {!onReviewStep &&
                            currentChunk.map((field) => (
                                <div key={field.key} className="grid gap-1.5">
                                    <Label>
                                        {field.label}
                                        {field.required && <span className="ml-0.5 text-red-500">*</span>}
                                    </Label>

                                    {field.type === 'text' && (
                                        <Input
                                            value={(data.data[field.key] as string) ?? ''}
                                            onChange={(e) => setField(field.key, e.target.value)}
                                        />
                                    )}

                                    {field.type === 'number' && (
                                        <Input
                                            type="number"
                                            value={(data.data[field.key] as string) ?? ''}
                                            onChange={(e) => setField(field.key, e.target.value)}
                                        />
                                    )}

                                    {field.type === 'date' && (
                                        <Input
                                            type="date"
                                            value={(data.data[field.key] as string) ?? ''}
                                            onChange={(e) => setField(field.key, e.target.value)}
                                        />
                                    )}

                                    {field.type === 'select' && (
                                        <Select
                                            value={(data.data[field.key] as string) || ''}
                                            onValueChange={(v) => setField(field.key, v)}
                                        >
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
                                        <div className="grid gap-3">
                                            <input
                                                ref={fileRef}
                                                type="file"
                                                accept="image/*"
                                                className="hidden"
                                                onChange={(e) => onPhotoChange(field.key, e.target.files?.[0] ?? null)}
                                            />
                                            {!data.data[field.key] && (
                                                <button
                                                    type="button"
                                                    onClick={() => fileRef.current?.click()}
                                                    className={cn(
                                                        'flex w-full flex-col items-center gap-2 rounded-xl border-2 border-dashed p-6 text-center transition',
                                                        'border-zinc-300 hover:border-emerald-400 hover:bg-emerald-50/30',
                                                    )}
                                                >
                                                    <ImageIcon className="size-8 text-emerald-400" />
                                                    <span className="text-muted-foreground text-sm">Ketuk untuk pilih foto</span>
                                                </button>
                                            )}
                                            {data.data[field.key] && photoPreview && (
                                                <>
                                                    <CardCropReposition
                                                        imageUrl={photoPreview}
                                                        filename={(data.data[field.key] as File).name}
                                                        onChange={setManualCrop}
                                                        onClose={() => onPhotoChange(field.key, null)}
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        className="justify-self-start"
                                                        onClick={() => fileRef.current?.click()}
                                                    >
                                                        Ganti Foto
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    )}

                                    {errorFor(field.key) && <p className="text-xs text-red-500">{errorFor(field.key)}</p>}
                                </div>
                            ))}

                        {onReviewStep && (
                            <div className="grid gap-4">
                                <h2 className="text-lg font-semibold">Review &amp; Buat Kartu</h2>
                                <dl className="grid gap-1.5 rounded-lg border border-zinc-200 p-3">
                                    {form.fields.map((field) => {
                                        const val = data.data[field.key];

                                        return (
                                            <div key={field.key} className="flex items-start justify-between gap-4 text-sm">
                                                <dt className="text-muted-foreground shrink-0">{field.label}</dt>
                                                <dd className="text-right font-medium break-words">
                                                    {field.type === 'photo'
                                                        ? val
                                                            ? `${(val as File).name}${manualCrop ? ' (crop diatur)' : ''}`
                                                            : '—'
                                                        : (val as string) || '—'}
                                                </dd>
                                            </div>
                                        );
                                    })}
                                </dl>

                                {photoPreview && (
                                    <div className="flex justify-center">
                                        <img
                                            src={photoPreview}
                                            alt="Foto"
                                            className="h-40 w-auto rounded-lg border border-emerald-200 object-cover shadow"
                                        />
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Navigation */}
                        <div className="flex items-center justify-between gap-3 pt-2">
                            <Button type="button" variant="outline" onClick={goBack} disabled={step === 1 || processing}>
                                Kembali
                            </Button>
                            {step < totalSteps ? (
                                <Button type="button" onClick={goNext} className="bg-emerald-600 text-white hover:bg-emerald-700">
                                    Lanjut
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    onClick={handleSubmit}
                                    disabled={processing}
                                    className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                                >
                                    {processing ? <Loader2 className="size-4 animate-spin" /> : <Sparkles className="size-4" />}
                                    {processing ? 'Membuat Kartu…' : 'Buat Kartu'}
                                </Button>
                            )}
                        </div>
                    </div>

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
