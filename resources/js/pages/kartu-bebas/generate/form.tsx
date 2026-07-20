import { Head } from '@inertiajs/react';
import { AlertTriangle, Check, CheckCircle2, Copy, Download, ImageIcon, Loader2, Sparkles, Wand2, XCircle } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CardCropReposition  } from '@/components/shared/card-crop-reposition';
import type {CropRect} from '@/components/shared/card-crop-reposition';
import { StepProgress  } from '@/components/shared/step-progress';
import type {WizardStep} from '@/components/shared/step-progress';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import { cn } from '@/lib/utils';

type FieldType = 'text' | 'date' | 'number' | 'select' | 'photo';

type LayoutField = {
    key: string;
    label: string;
    type: FieldType;
    required?: boolean;
    options?: string[];
};

type Layout = {
    id: string;
    name: string;
    orientation: 'landscape' | 'portrait';
    frame_url: string | null;
    fields: LayoutField[];
};

type Props = { layout: Layout };

type StatusResponse = {
    status: 'processing' | 'completed' | 'failed';
    card_url: string | null;
    thumb_url: string | null;
};

const FIELDS_PER_STEP = 4;

/** Chunk fields into steps; a photo field gets its own dedicated step. */
function buildFieldSteps(fields: LayoutField[]): LayoutField[][] {
    const chunks: LayoutField[][] = [];
    let current: LayoutField[] = [];

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

export default function KartuBebasGenerateForm({ layout }: Props) {
    const fieldSteps = useMemo(() => buildFieldSteps(layout.fields), [layout.fields]);

    const steps = useMemo<WizardStep[]>(() => {
        const fieldStepDefs = fieldSteps.map((chunk, i) => ({
            id: i + 1,
            title: chunk.length === 1 && chunk[0].type === 'photo' ? 'Foto' : `Data ${i + 1}`,
        }));

        return [...fieldStepDefs, { id: fieldSteps.length + 1, title: 'Review & Generate' }];
    }, [fieldSteps]);

    const totalSteps = steps.length;
    const reviewStep = totalSteps;

    const [step, setStep] = useState(1);
    const [values, setValues] = useState<Record<string, string>>(() => {
        const init: Record<string, string> = {};
        layout.fields.forEach((f) => {
            if (f.type !== 'photo') {
                init[f.key] = '';
            }
        });

        return init;
    });

    const [photoFile, setPhotoFile] = useState<File | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [manualCrop, setManualCrop] = useState<CropRect | null>(null);
    const fileRef = useRef<HTMLInputElement>(null);

    const [stepErrors, setStepErrors] = useState<Record<string, string>>({});
    const [generalError, setGeneralError] = useState('');
    const [generating, setGenerating] = useState(false);

    const [submissionId, setSubmissionId] = useState<string | null>(null);
    const [statusState, setStatusState] = useState<StatusResponse | null>(null);

    const csrfToken =
        typeof document !== 'undefined' ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '' : '';

    function setField(key: string, value: string) {
        setValues((prev) => ({ ...prev, [key]: value }));
    }

    function onPhotoChange(file: File | null) {
        setPhotoFile(file);
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

            if (field.type === 'photo') {
                if (!photoFile) {
                    e[field.key] = `${field.label} wajib diunggah.`;
                }
            } else if (!values[field.key]?.toString().trim()) {
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

        if (typeof window !== 'undefined') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function goBack() {
        setStepErrors({});
        setStep((s) => Math.max(1, s - 1));

        if (typeof window !== 'undefined') {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    async function handleSubmit() {
        // Defensive: validate all field steps before generating.
        for (let s = 1; s < reviewStep; s++) {
            const e = validateStep(s);

            if (Object.keys(e).length > 0) {
                setStep(s);
                setStepErrors(e);

                return;
            }
        }

        setGenerating(true);
        setGeneralError('');

        const formData = new FormData();
        layout.fields.forEach((f) => {
            if (f.type === 'photo') {
                if (photoFile) {
                    formData.append(`data[${f.key}]`, photoFile);
                }
            } else {
                formData.append(`data[${f.key}]`, values[f.key] ?? '');
            }
        });

        if (manualCrop) {
            formData.append('manual_crop[sx]', String(manualCrop.sx));
            formData.append('manual_crop[sy]', String(manualCrop.sy));
            formData.append('manual_crop[sw]', String(manualCrop.sw));
            formData.append('manual_crop[sh]', String(manualCrop.sh));
        }

        try {
            const res = await fetch(`/kartu-bebas/generate/${layout.id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: formData,
            });

            if (!res.ok) {
                const errorJson = await res.json().catch(() => null);

                if (res.status === 422 && errorJson?.errors) {
                    const errs: Record<string, string> = {};
                    Object.entries(errorJson.errors as Record<string, string[] | string>).forEach(([key, messages]) => {
                        const cleanKey = key.replace(/^data\./, '');
                        errs[cleanKey] = Array.isArray(messages) ? messages[0] : String(messages);
                    });
                    setStepErrors(errs);
                    setGeneralError('Beberapa isian belum valid. Periksa kembali.');
                    setGenerating(false);

                    return;
                }

                throw new Error(errorJson?.message || `Server error (${res.status})`);
            }

            const json = (await res.json()) as { success: boolean; submission: { id: string } };

            if (json.success && json.submission?.id) {
                setSubmissionId(json.submission.id);
                setStatusState({ status: 'processing', card_url: null, thumb_url: null });
            } else {
                throw new Error('Gagal membuat kartu.');
            }
        } catch (err: unknown) {
            setGeneralError(err instanceof Error ? err.message : 'Gagal menghubungi server.');
        } finally {
            setGenerating(false);
        }
    }

    // Poll the generation status once submitted.
    useEffect(() => {
        if (!submissionId || statusState?.status !== 'processing') {
            return;
        }

        let cancelled = false;
        let inFlight = false;

        async function poll() {
            if (inFlight) {
                return;
            }

            inFlight = true;

            try {
                const res = await fetch(`/kartu-bebas/generate/status/${submissionId}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!res.ok) {
                    return;
                }

                const json = (await res.json()) as StatusResponse;

                if (!cancelled && json.status !== 'processing') {
                    setStatusState(json);
                }
            } catch {
                // transient error — keep polling
            } finally {
                inFlight = false;
            }
        }

        poll();
        const interval = window.setInterval(poll, 3000);

        return () => {
            cancelled = true;
            window.clearInterval(interval);
        };
    }, [submissionId, statusState?.status]);

    function resetWizard() {
        setStep(1);
        setValues(() => {
            const init: Record<string, string> = {};
            layout.fields.forEach((f) => {
                if (f.type !== 'photo') {
                    init[f.key] = '';
                }
            });

            return init;
        });
        onPhotoChange(null);
        setStepErrors({});
        setGeneralError('');
        setSubmissionId(null);
        setStatusState(null);
    }

    // ---- Result view (after submit) ----
    if (submissionId && statusState) {
        return (
            <>
                <Head title={`Generate — ${layout.name}`} />
                <ResultView statusState={statusState} onReset={resetWizard} />
            </>
        );
    }

    const onReviewStep = step === reviewStep;
    const currentChunk = fieldSteps[step - 1] ?? [];

    return (
        <>
            <Head title={`Generate — ${layout.name}`} />
            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="flex items-center gap-2 text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">
                        <Wand2 className="size-6" /> {layout.name}
                    </h1>
                    <p className="text-muted-foreground text-sm">Isi data berikut untuk membuat kartu.</p>
                </div>

                <StepProgress steps={steps} current={step} />

                <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
                    {!onReviewStep && (
                        <div className="grid gap-5">
                            {currentChunk.map((field) => (
                                <FieldInput
                                    key={field.key}
                                    field={field}
                                    value={values[field.key] ?? ''}
                                    onValueChange={(v) => setField(field.key, v)}
                                    photoFile={photoFile}
                                    photoPreview={photoPreview}
                                    onPhotoChange={onPhotoChange}
                                    onCropChange={setManualCrop}
                                    fileRef={fileRef}
                                    error={stepErrors[field.key]}
                                />
                            ))}
                        </div>
                    )}

                    {onReviewStep && (
                        <div className="grid gap-4">
                            <h2 className="text-lg font-semibold">Review &amp; Generate</h2>
                            <dl className="grid gap-1.5 rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                                {layout.fields.map((field) => (
                                    <div key={field.key} className="flex items-start justify-between gap-4 text-sm">
                                        <dt className="text-muted-foreground shrink-0">{field.label}</dt>
                                        <dd className="text-right font-medium break-words">
                                            {field.type === 'photo'
                                                ? photoFile
                                                    ? `${photoFile.name}${manualCrop ? ' (crop diatur)' : ''}`
                                                    : '—'
                                                : values[field.key] || '—'}
                                        </dd>
                                    </div>
                                ))}
                            </dl>

                            {photoPreview && (
                                <div className="flex justify-center">
                                    <img
                                        src={photoPreview}
                                        alt="Foto"
                                        className="h-40 w-auto rounded-lg border border-emerald-200 object-cover shadow dark:border-emerald-900"
                                    />
                                </div>
                            )}

                            {generalError && (
                                <div className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                                    {generalError}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {/* Navigation */}
                <div className="flex items-center justify-between gap-3">
                    <Button type="button" variant="outline" onClick={goBack} disabled={step === 1 || generating} className="h-11">
                        Kembali
                    </Button>
                    {step < totalSteps ? (
                        <Button
                            type="button"
                            onClick={goNext}
                            className="h-11 bg-emerald-600 text-white hover:bg-emerald-700"
                        >
                            Lanjut
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            onClick={handleSubmit}
                            disabled={generating}
                            className="h-11 gap-2 bg-emerald-600 font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {generating ? <Spinner /> : <Sparkles className="size-4" />}
                            {generating ? 'Membuat…' : 'Generate Kartu'}
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

function FieldInput({
    field,
    value,
    onValueChange,
    photoFile,
    photoPreview,
    onPhotoChange,
    onCropChange,
    fileRef,
    error,
}: {
    field: LayoutField;
    value: string;
    onValueChange: (v: string) => void;
    photoFile: File | null;
    photoPreview: string | null;
    onPhotoChange: (file: File | null) => void;
    onCropChange: (rect: CropRect) => void;
    fileRef: React.RefObject<HTMLInputElement | null>;
    error?: string;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>
                {field.label}
                {field.required && <span className="ml-0.5 text-red-500">*</span>}
            </Label>

            {field.type === 'text' && (
                <Input className="h-11" value={value} onChange={(e) => onValueChange(e.target.value)} />
            )}

            {field.type === 'number' && (
                <Input className="h-11" type="number" value={value} onChange={(e) => onValueChange(e.target.value)} />
            )}

            {field.type === 'date' && (
                <Input className="h-11" type="date" value={value} onChange={(e) => onValueChange(e.target.value)} />
            )}

            {field.type === 'select' && (
                <Select value={value} onValueChange={onValueChange}>
                    <SelectTrigger className="h-11">
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
                        onChange={(e) => onPhotoChange(e.target.files?.[0] ?? null)}
                    />
                    {!photoFile && (
                        <button
                            type="button"
                            onClick={() => fileRef.current?.click()}
                            className={cn(
                                'flex w-full flex-col items-center gap-2 rounded-xl border-2 border-dashed p-6 text-center transition',
                                'border-zinc-300 hover:border-emerald-400 hover:bg-emerald-50/30 dark:border-zinc-700',
                            )}
                        >
                            <ImageIcon className="size-8 text-emerald-400" />
                            <span className="text-muted-foreground text-sm">Ketuk untuk pilih foto</span>
                        </button>
                    )}
                    {photoFile && photoPreview && (
                        <>
                            <CardCropReposition
                                imageUrl={photoPreview}
                                filename={photoFile.name}
                                onChange={onCropChange}
                                onClose={() => onPhotoChange(null)}
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

            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    );
}

function ResultView({ statusState, onReset }: { statusState: StatusResponse; onReset: () => void }) {
    const [copied, setCopied] = useState(false);

    async function copyLink() {
        if (!statusState.card_url) {
            return;
        }

        try {
            await navigator.clipboard.writeText(statusState.card_url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            window.prompt('Salin tautan kartu:', statusState.card_url);
        }
    }

    if (statusState.status === 'processing') {
        return (
            <div className="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                <Loader2 className="size-12 animate-spin text-emerald-500" />
                <div>
                    <h1 className="text-xl font-bold text-emerald-700 dark:text-emerald-400">Kartu sedang dibuat…</h1>
                    <p className="text-muted-foreground mt-1 text-sm">Mohon tunggu sebentar, halaman ini akan diperbarui otomatis.</p>
                </div>
            </div>
        );
    }

    if (statusState.status === 'failed') {
        return (
            <div className="mx-auto flex w-full max-w-md flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                <XCircle className="size-12 text-rose-500" />
                <div>
                    <h1 className="text-xl font-bold text-rose-700 dark:text-rose-400">Gagal Membuat Kartu</h1>
                    <p className="text-muted-foreground mt-1 text-sm">Terjadi kesalahan saat membuat kartu. Silakan coba lagi.</p>
                </div>
                <Button className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700" onClick={onReset}>
                    <Wand2 className="size-4" /> Generate Lagi
                </Button>
            </div>
        );
    }

    const imageUrl = statusState.thumb_url ?? statusState.card_url;
    const downloadUrl = statusState.thumb_url ?? statusState.card_url;

    return (
        <div className="mx-auto flex w-full max-w-md flex-1 flex-col gap-4 p-6">
            <div className="flex flex-col items-center text-center">
                <CheckCircle2 className="mb-2 size-12 text-emerald-500" />
                <h1 className="text-xl font-bold text-emerald-700 dark:text-emerald-400">Kartu Berhasil Dibuat!</h1>
                <p className="text-muted-foreground mt-1 text-sm">Berikut kartu Anda. Salin tautan atau unduh.</p>
            </div>

            {imageUrl && (
                <div className="overflow-hidden rounded-xl border bg-zinc-50 p-3 dark:bg-zinc-900">
                    <img src={imageUrl} alt="Kartu" className="mx-auto max-h-80 w-auto rounded-md shadow" />
                </div>
            )}

            <div className="flex gap-2">
                <Button variant="outline" className="flex-1 gap-2" onClick={copyLink} disabled={!statusState.card_url}>
                    {copied ? <Check className="size-4 text-emerald-600" /> : <Copy className="size-4" />}
                    {copied ? 'Tersalin!' : 'Salin Link'}
                </Button>
                <a href={downloadUrl ?? '#'} target="_blank" rel="noopener noreferrer" download className="flex-1">
                    <Button className="w-full gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                        <Download className="size-4" /> Unduh
                    </Button>
                </a>
            </div>

            <Button variant="ghost" className="gap-2" onClick={onReset}>
                <Wand2 className="size-4" /> Generate Lagi
            </Button>
        </div>
    );
}

KartuBebasGenerateForm.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Generate', href: '/kartu-bebas/generate' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
