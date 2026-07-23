import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, CheckCircle2, Copy, CreditCard, Crop, Download, Loader2, Move, RotateCcw, User, X, ZoomIn } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import Cropper from 'react-easy-crop';
import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { SimpleCaptcha } from '@/components/simple-captcha';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

type School = { id: string; name: string; logo_path: string | null };
type Classroom = { id: string; school_id: string; name: string; grade_level: number };

type Props = {
    schools: School[];
    classrooms: Classroom[];
};

type RegistrationResult = {
    success: boolean;
    message: string;
    queued?: boolean;
    student: {
        id: string;
        full_name: string;
        nis: string;
        nisn: string | null;
        classroom: string | null;
        photo_url: string | null;
    };
};

type StatusItem = {
    type: 'photo' | 'card' | 'photo_sheet';
    name: string;
    status: 'processing' | 'completed' | 'failed';
    url: string | null;
    thumb_url: string | null;
};

/** Normalized crop rect (0..1) relative to the natural image. */
type CropRect = { sx: number; sy: number; sw: number; sh: number };
type AutoCrop = CropRect & { natW: number; natH: number; ratio: number };

const religions = [
    { value: 'ISLAM', label: 'Islam' },
    { value: 'KRISTEN', label: 'Kristen Protestan' },
    { value: 'KATOLIK', label: 'Katolik' },
    { value: 'HINDU', label: 'Hindu' },
    { value: 'BUDDHA', label: 'Buddha' },
    { value: 'KONGHUCU', label: 'Konghucu' },
];

const STORAGE_KEY = 'daftar_form_v1';

const STEPS = [
    { id: 1, title: 'Sekolah' },
    { id: 2, title: 'Foto' },
    { id: 3, title: 'Data Siswa' },
    { id: 4, title: 'Kelahiran & Alamat' },
    { id: 5, title: 'Orang Tua' },
    { id: 6, title: 'Review & Kirim' },
];

const TOTAL_STEPS = STEPS.length;

type FormData = {
    school_id: string;
    full_name: string;
    nis: string;
    no_absen: string;
    nisn: string;
    gender: string;
    religion: string;
    classroom_id: string;
    birth_place: string;
    birth_date: string;
    address: string;
    parent_name: string;
    parent_phone: string;
    parent_email: string;
    parent_relation: string;
    photo_drive_filename: string;
    photo_temp: string;
    manual_crop: CropRect | null;
    generate_cards: boolean;
};

const INITIAL_DATA: FormData = {
    school_id: '',
    full_name: '',
    nis: '',
    no_absen: '',
    nisn: '',
    gender: '',
    religion: '',
    classroom_id: '',
    birth_place: '',
    birth_date: '',
    address: '',
    parent_name: '',
    parent_phone: '',
    parent_email: '',
    parent_relation: 'WALI',
    photo_drive_filename: '',
    photo_temp: '',
    manual_crop: null,
    generate_cards: true,
};

/** Read the persisted wizard snapshot from localStorage (data minus transient preview state). */
function readPersisted(): { data: FormData; step: number } {
    if (typeof window === 'undefined') {
        return { data: INITIAL_DATA, step: 1 };
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (raw) {
            const parsed = JSON.parse(raw) as { data?: Partial<FormData>; step?: number };
            const step = parsed.step && parsed.step >= 1 && parsed.step <= TOTAL_STEPS ? parsed.step : 1;

            return { data: { ...INITIAL_DATA, ...parsed.data }, step };
        }
    } catch {
        // ignore corrupt storage
    }

    return { data: INITIAL_DATA, step: 1 };
}

export default function StudentRegister({ schools, classrooms }: Props) {
    const { flash } = usePage().props as unknown as { flash: { success?: string } };

    // Restore persisted wizard state once, synchronously, via lazy initializers.
    const persistedRef = useRef(readPersisted());
    const [submitted, setSubmitted] = useState(false);
    const [captchaVerified, setCaptchaVerified] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [loadingStep, setLoadingStep] = useState('');
    const [result, setResult] = useState<RegistrationResult | null>(null);
    const [statusItems, setStatusItems] = useState<StatusItem[]>([]);
    const [statusDone, setStatusDone] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [step, setStep] = useState(() => persistedRef.current.step);
    const [stepErrors, setStepErrors] = useState<Record<string, string>>({});

    // Photo step state (transient — not persisted)
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState('');
    const [photoPreview, setPhotoPreview] = useState<{ url: string; filename: string } | null>(null);
    const [autoCrop, setAutoCrop] = useState<AutoCrop | null>(null);

    const { data, setData, processing, errors } = useForm<FormData>(persistedRef.current.data);

    // ---- localStorage persist (data + current step, minus transient preview state) ----
    useEffect(() => {
        if (submitted) {
            return;
        }

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ data, step }));
        } catch {
            // ignore quota errors
        }
    }, [data, step, submitted]);

    const selectedSchool = schools.find((s) => String(s.id) === data.school_id);
    const filteredClassrooms = classrooms.filter((c) => String(c.school_id) === data.school_id);

    const csrfToken =
        typeof document !== 'undefined' ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '' : '';

    // ---- Step validation ----
    function validateStep(current: number): Record<string, string> {
        const e: Record<string, string> = {};

        if (current === 1) {
            if (!data.school_id) {
                e.school_id = 'Pilih sekolah terlebih dahulu.';
            }
        } else if (current === 3) {
            if (!data.full_name.trim()) {
                e.full_name = 'Nama lengkap wajib diisi.';
            }

            if (!data.no_absen.trim()) {
                e.no_absen = 'No. absen wajib diisi.';
            }

            if (!data.nisn.trim()) {
                e.nisn = 'NISN wajib diisi.';
            }

            if (!data.gender) {
                e.gender = 'Pilih jenis kelamin.';
            }

            if (!data.religion) {
                e.religion = 'Pilih agama.';
            }

            if (!data.classroom_id) {
                e.classroom_id = 'Pilih kelas terlebih dahulu.';
            }
        } else if (current === 4) {
            if (!data.birth_place.trim()) {
                e.birth_place = 'Tempat lahir wajib diisi.';
            }

            if (!data.birth_date) {
                e.birth_date = 'Tanggal lahir wajib diisi.';
            }

            if (!data.address.trim()) {
                e.address = 'Alamat wajib diisi.';
            }
        } else if (current === 5) {
            if (!data.parent_name.trim()) {
                e.parent_name = 'Nama orang tua wajib diisi.';
            }

            if (!data.parent_phone.trim()) {
                e.parent_phone = 'No. WhatsApp orang tua wajib diisi.';
            }

            if (!data.parent_relation) {
                e.parent_relation = 'Pilih hubungan orang tua.';
            }
        }
        // step 2 (Foto) has no required fields — optional

        return e;
    }

    function goNext() {
        const e = validateStep(step);

        if (Object.keys(e).length > 0) {
            setStepErrors(e);

            return;
        }

        setStepErrors({});
        setStep((s) => Math.min(TOTAL_STEPS, s + 1));

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

    function err(key: string): string | undefined {
        return stepErrors[key] || (errors as Record<string, string>)[key] || formErrors[key];
    }

    function handleFinalSubmit() {
        // Validate all input steps defensively before submit.
        for (let s = 1; s <= 5; s++) {
            const e = validateStep(s);

            if (Object.keys(e).length > 0) {
                setStep(s);
                setStepErrors(e);

                return;
            }
        }

        if (data.generate_cards) {
            setShowConfirm(true);
        } else {
            doSubmit();
        }
    }

    async function doSubmit() {
        setShowConfirm(false);
        setGenerating(true);
        setFormErrors({});
        setLoadingStep(
            data.photo_drive_filename
                ? 'Menyimpan data & mengunduh foto dari Drive...'
                : data.generate_cards
                  ? 'Menyimpan data & generate kartu...'
                  : 'Menyimpan data siswa...',
        );

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 120000); // 2 menit timeout

        try {
            const res = await fetch('/daftar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify(data),
                signal: controller.signal,
            });

            clearTimeout(timeout);

            if (!res.ok) {
                const errorJson = await res.json().catch(() => null);

                if (res.status === 422 && errorJson?.errors) {
                    const errs: Record<string, string> = {};
                    Object.entries(errorJson.errors).forEach(([key, messages]) => {
                        errs[key] = Array.isArray(messages) ? messages[0] : String(messages);
                    });
                    setFormErrors(errs);
                    setGenerating(false);
                    setLoadingStep('');

                    return;
                }

                throw new Error(errorJson?.message || `Server error (${res.status})`);
            }

            const json = await res.json();

            if (json.success) {
                try {
                    localStorage.removeItem(STORAGE_KEY);
                } catch {
                    // ignore
                }

                // Queued flow → bookmarkable result page (parent can reopen later).
                if (json.queued && json.student?.id) {
                    router.visit(`/daftar/${json.student.id}/hasil`);

                    return;
                }

                setResult(json);
                setSubmitted(true);
                setData(INITIAL_DATA);
                setStep(1);
            } else {
                throw new Error(json.message || 'Pendaftaran gagal');
            }
        } catch (err: unknown) {
            const message =
                err instanceof Error
                    ? err.name === 'AbortError'
                        ? 'Proses terlalu lama (timeout 2 menit). Silakan coba lagi.'
                        : err.message
                    : 'Gagal menghubungi server';
            setFormErrors({ _general: message });
        } finally {
            clearTimeout(timeout);
            setGenerating(false);
            setLoadingStep('');
        }
    }

    const handleLoadPhoto = useCallback(async () => {
        if (!data.photo_drive_filename.trim() || !data.school_id) {
            return;
        }

        setPreviewLoading(true);
        setPreviewError('');
        setPhotoPreview(null);
        setAutoCrop(null);
        setData((prev) => ({ ...prev, manual_crop: null, photo_temp: '' }));

        try {
            const res = await fetch('/daftar/crop-preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    school_id: data.school_id,
                    filename: data.photo_drive_filename.trim(),
                }),
            });

            const json = await res.json();

            if (json.found) {
                setPhotoPreview({ url: json.preview_url, filename: json.filename });
                setData('photo_temp', json.photo_temp ?? '');

                if (json.crop) {
                    setAutoCrop(json.crop);
                    // Seed manual_crop with the auto rect so a non-dragging user still crops correctly.
                    setData('manual_crop', {
                        sx: json.crop.sx,
                        sy: json.crop.sy,
                        sw: json.crop.sw,
                        sh: json.crop.sh,
                    });
                }
            } else {
                setPreviewError(json.message || 'File tidak ditemukan.');
            }
        } catch {
            setPreviewError('Gagal menghubungi server.');
        } finally {
            setPreviewLoading(false);
        }
    }, [data.photo_drive_filename, data.school_id, csrfToken, setData]);

    // Auto-search the Drive photo as the user types (debounced) — no button needed.
    useEffect(() => {
        const name = data.photo_drive_filename.trim();
        if (!name || !data.school_id) {
            return;
        }
        const t = setTimeout(() => {
            handleLoadPhoto();
        }, 650);
        return () => clearTimeout(t);
    }, [data.photo_drive_filename, data.school_id, handleLoadPhoto]);

    // ---- Poll the async generation status (photo, cards, photo sheet) ----
    const shouldPoll = submitted && result?.queued === true && !!result?.student.id && !statusDone;

    useEffect(() => {
        if (!shouldPoll) {
            return;
        }

        const studentId = result!.student.id;
        let cancelled = false;
        let inFlight = false;

        async function poll() {
            if (inFlight) {
                return;
            }
            inFlight = true;

            try {
                const res = await fetch(`/daftar/status/${studentId}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!res.ok) {
                    return;
                }

                const json = (await res.json()) as { done: boolean; items: StatusItem[] };

                if (!cancelled) {
                    setStatusItems(Array.isArray(json.items) ? json.items : []);

                    if (json.done) {
                        setStatusDone(true);
                    }
                }
            } catch {
                // transient network error — keep polling
            } finally {
                inFlight = false;
            }
        }

        poll();
        const interval = setInterval(poll, 3000);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [shouldPoll, result]);

    function handleNewSubmission() {
        setSubmitted(false);
        setResult(null);
        setStatusItems([]);
        setStatusDone(false);
        setCaptchaVerified(false);
        setPhotoPreview(null);
        setAutoCrop(null);
    }

    // Success page with async generation results
    if (submitted && result) {
        return (
            <PageWrapper>
                <div className="mx-auto max-w-2xl px-4 py-8">
                    <div className="rounded-2xl border border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-950">
                        <CheckCircle2 className="mx-auto mb-4 size-16 text-green-600 dark:text-green-400" />
                        <h2 className="mb-2 text-center text-2xl font-bold text-green-800 dark:text-green-200">Pendaftaran Berhasil!</h2>
                        <p className="mb-6 text-center text-green-700 dark:text-green-300">{result.message}</p>

                        {/* Student info */}
                        <div className="mb-6 flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-800">
                            {result.student.photo_url ? (
                                <img
                                    src={result.student.photo_url}
                                    alt={result.student.full_name}
                                    className="h-24 w-[72px] rounded-xl border-2 border-green-300 object-cover"
                                />
                            ) : (
                                <div className="flex h-24 w-[72px] items-center justify-center rounded-xl border-2 border-green-300 bg-green-100 dark:bg-green-900">
                                    <User className="size-8 text-green-400" />
                                </div>
                            )}
                            <div>
                                <h3 className="text-lg font-bold">{result.student.full_name}</h3>
                                <p className="text-muted-foreground text-sm">
                                    NIS: {result.student.nis}
                                    {result.student.nisn && ` · NISN: ${result.student.nisn}`}
                                </p>
                                <p className="text-muted-foreground text-sm">Kelas: {result.student.classroom}</p>
                            </div>
                        </div>

                        {/* Async generation results */}
                        {result.queued && (
                            <div className="space-y-4">
                                {/* Header */}
                                {statusDone ? (
                                    <div className="flex items-center gap-3 rounded-xl border border-green-200 bg-green-100/60 p-4 dark:border-green-800 dark:bg-green-900/40">
                                        <CheckCircle2 className="size-5 shrink-0 text-green-600 dark:text-green-400" />
                                        <p className="text-sm font-semibold text-green-800 dark:text-green-200">Semua Selesai!</p>
                                    </div>
                                ) : (
                                    <div className="flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
                                        <Loader2 className="mt-0.5 size-5 shrink-0 animate-spin text-blue-600" />
                                        <div>
                                            <p className="text-sm font-semibold text-blue-800 dark:text-blue-200">
                                                Foto & kartu sedang diproses…
                                            </p>
                                            <p className="text-muted-foreground mt-0.5 text-xs">
                                                Foto siswa, kartu, dan lembar pas foto otomatis dibuat dan tersimpan ke Google Drive.
                                            </p>
                                        </div>
                                    </div>
                                )}

                                {/* Result tiles */}
                                {statusItems.length === 0 ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {[0, 1, 2, 3].map((i) => (
                                            <div
                                                key={i}
                                                className="flex h-48 animate-pulse items-center justify-center rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800"
                                            >
                                                <Loader2 className="size-6 animate-spin text-zinc-400" />
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {statusItems.map((item, i) => (
                                            <ResultTile key={`${item.type}-${i}`} item={item} />
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="mt-6 text-center">
                            <Button
                                onClick={handleNewSubmission}
                                className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/25"
                            >
                                Daftarkan Siswa Lain
                            </Button>
                        </div>
                    </div>
                </div>
                <Footer />
            </PageWrapper>
        );
    }

    // Fallback for non-card registration success
    if (submitted && flash?.success) {
        return (
            <PageWrapper>
                <div className="mx-auto max-w-2xl px-4 py-16">
                    <div className="rounded-2xl border border-green-200 bg-green-50 p-8 text-center dark:border-green-800 dark:bg-green-950">
                        <CheckCircle2 className="mx-auto mb-4 size-16 text-green-600 dark:text-green-400" />
                        <h2 className="mb-2 text-2xl font-bold text-green-800 dark:text-green-200">Pendaftaran Berhasil!</h2>
                        <p className="mb-6 text-green-700 dark:text-green-300">{flash.success}</p>
                        <Button
                            onClick={handleNewSubmission}
                            className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/25"
                        >
                            Daftarkan Siswa Lain
                        </Button>
                    </div>
                </div>
                <Footer />
            </PageWrapper>
        );
    }

    return (
        <PageWrapper>
            <div className="mx-auto max-w-2xl px-4 py-8 sm:py-12">
                {/* Header */}
                <div className="mb-6 text-center">
                    {selectedSchool?.logo_path ? (
                        <img
                            src={`/storage/${selectedSchool.logo_path}`}
                            alt={selectedSchool.name}
                            className="mx-auto mb-4 size-16 rounded-xl object-contain"
                        />
                    ) : (
                        <div className="mx-auto mb-4 flex size-16 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600">
                            <AppLogoIcon className="size-8 fill-current text-white" />
                        </div>
                    )}
                    <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">Pendaftaran Data Siswa Baru</h1>
                    <p className="text-muted-foreground mt-2 text-sm sm:text-base">
                        Lengkapi data siswa untuk didaftarkan ke sistem absensi sekolah.
                    </p>
                </div>

                {/* Progress indicator */}
                <StepProgress current={step} />

                <div className="mt-6">
                    {step === 1 && (
                        <FormSection number={1} title="Pilih Sekolah">
                            <div className="grid gap-2">
                                <Label htmlFor="school_id" className="text-sm font-medium">
                                    Sekolah <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={data.school_id}
                                    onValueChange={(val) => {
                                        setData((prev) => ({ ...prev, school_id: val, classroom_id: '' }));
                                    }}
                                >
                                    <SelectTrigger className="h-11 w-full">
                                        <SelectValue placeholder="Pilih sekolah" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {schools.map((school) => (
                                            <SelectItem key={school.id} value={String(school.id)}>
                                                {school.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={err('school_id')} />
                            </div>
                        </FormSection>
                    )}

                    {step === 3 && (
                        <FormSection number={3} title="Data Siswa">
                            <div className="grid gap-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="full_name" className="text-sm font-medium">
                                        Nama Lengkap <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="full_name"
                                        value={data.full_name}
                                        onChange={(e) => setData('full_name', e.target.value)}
                                        placeholder="Nama lengkap siswa"
                                        className="h-11"
                                    />
                                    <InputError message={err('full_name')} />
                                </div>

                                <div className="grid grid-cols-1 items-start gap-5 sm:grid-cols-3">
                                    <div className="grid gap-2">
                                        <Label htmlFor="nis" className="text-sm font-medium">
                                            NIS
                                        </Label>
                                        <Input
                                            id="nis"
                                            value={data.nis}
                                            onChange={(e) => setData('nis', e.target.value)}
                                            placeholder="Nomor Induk Siswa"
                                            className="h-11"
                                        />
                                        <InputError message={err('nis')} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="no_absen" className="text-sm font-medium">
                                            No. Absen <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="no_absen"
                                            value={data.no_absen}
                                            onChange={(e) => setData('no_absen', e.target.value)}
                                            placeholder="Nomor absen"
                                            className="h-11"
                                        />
                                        <InputError message={err('no_absen')} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="nisn" className="text-sm font-medium">
                                            NISN <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="nisn"
                                            value={data.nisn}
                                            onChange={(e) => setData('nisn', e.target.value)}
                                            placeholder="Nomor Induk Siswa Nasional"
                                            className="h-11"
                                        />
                                        <InputError message={err('nisn')} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label className="text-sm font-medium">
                                        Jenis Kelamin <span className="text-red-500">*</span>
                                    </Label>
                                    <RadioGroup value={data.gender} onValueChange={(v) => setData('gender', v)} className="flex gap-4">
                                        <label
                                            htmlFor="gender-l"
                                            className={`flex flex-1 cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition ${data.gender === 'LAKI_LAKI' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-900'}`}
                                        >
                                            <RadioGroupItem value="LAKI_LAKI" id="gender-l" /> Laki-laki
                                        </label>
                                        <label
                                            htmlFor="gender-p"
                                            className={`flex flex-1 cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition ${data.gender === 'PEREMPUAN' ? 'border-pink-500 bg-pink-50 dark:bg-pink-950' : 'border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-900'}`}
                                        >
                                            <RadioGroupItem value="PEREMPUAN" id="gender-p" /> Perempuan
                                        </label>
                                    </RadioGroup>
                                    <InputError message={err('gender')} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="religion" className="text-sm font-medium">
                                        Agama <span className="text-red-500">*</span>
                                    </Label>
                                    <Select value={data.religion} onValueChange={(val) => setData('religion', val)}>
                                        <SelectTrigger className="h-11 w-full">
                                            <SelectValue placeholder="Pilih agama" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {religions.map((r) => (
                                                <SelectItem key={r.value} value={r.value}>
                                                    {r.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={err('religion')} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="classroom_id" className="text-sm font-medium">
                                        Kelas <span className="text-red-500">*</span>
                                    </Label>
                                    <Select
                                        value={data.classroom_id}
                                        onValueChange={(val) => setData('classroom_id', val)}
                                        disabled={!data.school_id}
                                    >
                                        <SelectTrigger className="h-11 w-full">
                                            <SelectValue placeholder={data.school_id ? 'Pilih kelas' : 'Pilih sekolah terlebih dahulu'} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {filteredClassrooms.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={err('classroom_id')} />
                                </div>
                            </div>
                        </FormSection>
                    )}

                    {step === 4 && (
                        <FormSection number={4} title="Data Kelahiran & Alamat">
                            <div className="grid gap-5">
                                <div className="grid grid-cols-1 items-start gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="birth_place" className="text-sm font-medium">
                                            Tempat Lahir <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="birth_place"
                                            value={data.birth_place}
                                            onChange={(e) => setData('birth_place', e.target.value)}
                                            placeholder="Kota kelahiran"
                                            className="h-11"
                                        />
                                        <InputError message={err('birth_place')} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="birth_date" className="text-sm font-medium">
                                            Tanggal Lahir <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="birth_date"
                                            type="date"
                                            value={data.birth_date}
                                            onChange={(e) => setData('birth_date', e.target.value)}
                                            className="h-11"
                                        />
                                        <InputError message={err('birth_date')} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="address" className="text-sm font-medium">
                                        Alamat <span className="text-red-500">*</span>
                                    </Label>
                                    <Textarea
                                        id="address"
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value.slice(0, 90))}
                                        placeholder="Alamat lengkap siswa"
                                        rows={3}
                                        maxLength={90}
                                    />
                                    <div className="flex items-center justify-between">
                                        <InputError message={err('address')} />
                                        <span className="text-muted-foreground ml-auto text-xs">{data.address.length}/90</span>
                                    </div>
                                </div>
                            </div>
                        </FormSection>
                    )}

                    {step === 5 && (
                        <FormSection number={5} title="Data Orang Tua/Wali">
                            <div className="grid gap-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="parent_name" className="text-sm font-medium">
                                        Nama Orang Tua/Wali <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        id="parent_name"
                                        value={data.parent_name}
                                        onChange={(e) => setData('parent_name', e.target.value)}
                                        placeholder="Nama lengkap orang tua atau wali"
                                        className="h-11"
                                    />
                                    <InputError message={err('parent_name')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label className="text-sm font-medium">
                                        Hubungan <span className="text-red-500">*</span>
                                    </Label>
                                    <Select value={data.parent_relation} onValueChange={(v) => setData('parent_relation', v)}>
                                        <SelectTrigger className="h-11">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="AYAH">Ayah</SelectItem>
                                            <SelectItem value="IBU">Ibu</SelectItem>
                                            <SelectItem value="WALI">Wali</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={err('parent_relation')} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="parent_phone" className="text-sm font-medium">
                                        No. WhatsApp <span className="text-red-500">*</span>
                                    </Label>
                                    <div className="flex">
                                        <span className="border-input bg-muted/50 text-muted-foreground inline-flex items-center rounded-l-md border border-r-0 px-3.5 text-sm font-medium">
                                            +62
                                        </span>
                                        <Input
                                            id="parent_phone"
                                            type="tel"
                                            value={data.parent_phone}
                                            onChange={(e) => setData('parent_phone', e.target.value)}
                                            placeholder="812xxxxxxxx"
                                            className="h-11 rounded-l-none"
                                        />
                                    </div>
                                    <InputError message={err('parent_phone')} />
                                    <p className="text-muted-foreground text-xs">Nomor ini akan menerima notifikasi absensi via WhatsApp.</p>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="parent_email" className="text-sm font-medium">
                                        Email Orang Tua/Wali
                                    </Label>
                                    <Input
                                        id="parent_email"
                                        type="email"
                                        value={data.parent_email}
                                        onChange={(e) => setData('parent_email', e.target.value)}
                                        placeholder="email@contoh.com"
                                        className="h-11"
                                    />
                                    <InputError message={err('parent_email')} />
                                    <p className="text-muted-foreground text-xs">
                                        Email ini akan menerima notifikasi kehadiran (absen) siswa. Opsional.
                                    </p>
                                </div>
                            </div>
                        </FormSection>
                    )}

                    {step === 2 && (
                        <FormSection number={2} title="Foto Siswa">
                            <div className="grid gap-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="photo_drive_filename" className="text-sm font-medium">
                                        Nama File Foto di Google Drive
                                    </Label>
                                    <div className="relative">
                                        <Input
                                            id="photo_drive_filename"
                                            value={data.photo_drive_filename}
                                            onChange={(e) => {
                                                setData((prev) => ({
                                                    ...prev,
                                                    photo_drive_filename: e.target.value,
                                                    manual_crop: null,
                                                    photo_temp: '',
                                                }));
                                                setPhotoPreview(null);
                                                setAutoCrop(null);
                                                setPreviewError('');
                                            }}
                                            placeholder="Contoh: FIC_0008.JPG atau IMG_0234.png"
                                            className="h-11 pr-10"
                                        />
                                        {previewLoading && (
                                            <Loader2 className="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin" />
                                        )}
                                    </div>
                                    <p className="text-muted-foreground text-xs">
                                        Ketik nama file foto yang sudah diupload ke folder Foto Siswa di Google Drive — foto muncul otomatis untuk atur posisi crop wajah.
                                    </p>

                                    {previewError && (
                                        <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                                            <AlertTriangle className="size-4 shrink-0" />
                                            {previewError}
                                        </div>
                                    )}
                                </div>

                                {/* Crop reposition */}
                                {photoPreview && autoCrop && (
                                    <CropReposition
                                        imageUrl={photoPreview.url}
                                        filename={photoPreview.filename}
                                        auto={autoCrop}
                                        onChange={(rect) => setData('manual_crop', rect)}
                                        onClose={() => {
                                            setPhotoPreview(null);
                                            setAutoCrop(null);
                                            setData((prev) => ({ ...prev, manual_crop: null, photo_temp: '' }));
                                        }}
                                    />
                                )}

                                <div className="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950">
                                    <input
                                        type="checkbox"
                                        id="generate_cards"
                                        checked={data.generate_cards}
                                        onChange={(e) => setData('generate_cards', e.target.checked)}
                                        className="size-4 rounded border-zinc-300"
                                    />
                                    <label htmlFor="generate_cards" className="text-sm">
                                        <span className="font-medium text-blue-800 dark:text-blue-200">Generate Kartu OSIS & Perpustakaan</span>
                                        <span className="text-muted-foreground block text-xs">
                                            Kartu otomatis digenerate dan disimpan ke Google Drive setelah data tersimpan.
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </FormSection>
                    )}

                    {step === 6 && (
                        <FormSection number={6} title="Review & Kirim">
                            <div className="grid gap-5">
                                <ReviewGroup title="Sekolah">
                                    <ReviewRow label="Sekolah" value={selectedSchool?.name} />
                                </ReviewGroup>
                                <ReviewGroup title="Data Siswa">
                                    <ReviewRow label="Nama Lengkap" value={data.full_name} />
                                    <ReviewRow label="NIS" value={data.nis || '(auto-generate)'} />
                                    <ReviewRow label="No. Absen" value={data.no_absen} />
                                    <ReviewRow label="NISN" value={data.nisn} />
                                    <ReviewRow label="Jenis Kelamin" value={data.gender === 'LAKI_LAKI' ? 'Laki-laki' : data.gender === 'PEREMPUAN' ? 'Perempuan' : ''} />
                                    <ReviewRow label="Agama" value={religions.find((r) => r.value === data.religion)?.label} />
                                    <ReviewRow label="Kelas" value={filteredClassrooms.find((c) => String(c.id) === data.classroom_id)?.name} />
                                </ReviewGroup>
                                <ReviewGroup title="Kelahiran & Alamat">
                                    <ReviewRow label="Tempat Lahir" value={data.birth_place} />
                                    <ReviewRow label="Tanggal Lahir" value={data.birth_date} />
                                    <ReviewRow label="Alamat" value={data.address} />
                                </ReviewGroup>
                                <ReviewGroup title="Orang Tua/Wali">
                                    <ReviewRow label="Nama" value={data.parent_name} />
                                    <ReviewRow label="Hubungan" value={data.parent_relation} />
                                    <ReviewRow label="No. WhatsApp" value={data.parent_phone ? `+62${data.parent_phone}` : ''} />
                                    <ReviewRow label="Email" value={data.parent_email || '—'} />
                                </ReviewGroup>
                                <ReviewGroup title="Foto & Kartu">
                                    <ReviewRow label="File Foto" value={data.photo_drive_filename || '(tidak ada)'} />
                                    <ReviewRow label="Crop Manual" value={data.manual_crop ? 'Ya (posisi diatur)' : data.photo_drive_filename ? 'Otomatis' : '—'} />
                                    <ReviewRow label="Generate Kartu" value={data.generate_cards ? 'Ya (OSIS & Perpustakaan)' : 'Tidak'} />
                                </ReviewGroup>

                                {/* Captcha */}
                                <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                                    <SimpleCaptcha onVerified={(token) => setCaptchaVerified(!!token)} />
                                </div>

                                {/* General / server errors */}
                                {(formErrors._general || Object.keys(formErrors).length > 0) && (
                                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                                        {formErrors._general && (
                                            <p className="mb-2 font-semibold text-red-700 dark:text-red-300">{formErrors._general}</p>
                                        )}
                                        {Object.entries(formErrors)
                                            .filter(([k]) => k !== '_general')
                                            .map(([key, msg]) => (
                                                <p key={key} className="text-sm text-red-600 dark:text-red-400">
                                                    • {msg}
                                                </p>
                                            ))}
                                    </div>
                                )}
                            </div>
                        </FormSection>
                    )}
                </div>

                {/* Navigation */}
                <div className="mt-6 flex items-center justify-between gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={goBack}
                        disabled={step === 1 || generating}
                        className="h-11 border-zinc-300 bg-white text-zinc-700 shadow-sm hover:bg-zinc-50 disabled:opacity-40 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200"
                    >
                        Kembali
                    </Button>
                    {step < TOTAL_STEPS ? (
                        <Button
                            type="button"
                            onClick={goNext}
                            disabled={
                                step === 2 &&
                                (previewLoading ||
                                    (data.photo_drive_filename.trim() !== '' && !photoPreview && !previewError))
                            }
                            className="h-11 gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/25 disabled:opacity-50"
                        >
                            {step === 2 && previewLoading && <Loader2 className="size-4 animate-spin" />}
                            {step === 2 && previewLoading ? 'Memuat foto…' : 'Lanjut'}
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            onClick={handleFinalSubmit}
                            disabled={processing || generating || !captchaVerified}
                            className="h-11 bg-gradient-to-r from-blue-600 to-indigo-600 font-semibold text-white shadow-lg shadow-blue-500/25 disabled:opacity-50"
                        >
                            {generating ? <Spinner /> : 'Daftarkan Siswa'}
                        </Button>
                    )}
                </div>

                {/* Confirmation Dialog */}
                {showConfirm && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                        <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                            <div className="mb-4 flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                    <CreditCard className="size-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <h3 className="text-lg font-bold">Konfirmasi Pendaftaran</h3>
                            </div>
                            <p className="mb-2 text-sm text-zinc-600 dark:text-zinc-400">
                                Data siswa <b>{data.full_name || '—'}</b> akan disimpan dan kartu akan digenerate:
                            </p>
                            <ul className="mb-4 list-inside list-disc space-y-1 text-sm text-zinc-600 dark:text-zinc-400">
                                <li>Kartu OSIS</li>
                                <li>Kartu Perpustakaan</li>
                                {data.photo_drive_filename && (
                                    <li>
                                        Foto diambil dari Drive: <b>{data.photo_drive_filename}</b>
                                    </li>
                                )}
                                <li>Hasil disimpan ke Google Drive</li>
                            </ul>
                            <p className="mb-6 text-xs text-amber-600 dark:text-amber-400">
                                Proses ini membutuhkan waktu beberapa detik. Pastikan data sudah benar.
                            </p>
                            <div className="flex gap-3">
                                <Button variant="outline" className="flex-1" onClick={() => setShowConfirm(false)}>
                                    Batal
                                </Button>
                                <Button className="flex-1 bg-blue-600 text-white" onClick={doSubmit}>
                                    Ya, Daftarkan & Generate
                                </Button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Loading Overlay */}
                {generating && (
                    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
                        <div className="w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-2xl dark:bg-zinc-900">
                            <Loader2 className="mx-auto mb-4 size-12 animate-spin text-blue-600" />
                            <h3 className="mb-2 text-lg font-bold">Memproses Pendaftaran</h3>
                            <p className="text-muted-foreground mb-4 text-sm">{loadingStep || 'Mohon tunggu...'}</p>
                            <div className="mx-auto h-1.5 w-48 overflow-hidden rounded-full bg-zinc-200">
                                <div className="h-full animate-pulse rounded-full bg-blue-600" style={{ width: '60%' }} />
                            </div>
                            <p className="text-muted-foreground mt-4 text-xs">Proses ini membutuhkan 10-30 detik. Jangan tutup halaman.</p>
                        </div>
                    </div>
                )}
            </div>

            <Footer />
        </PageWrapper>
    );
}

/** Canva-style crop: fixed 16:21 frame, image pans + zooms behind it. */
function CropReposition({
    imageUrl,
    filename,
    auto,
    onChange,
    onClose,
}: {
    imageUrl: string;
    filename: string;
    auto: AutoCrop;
    onChange: (rect: CropRect) => void;
    onClose: () => void;
}) {
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [resetKey, setResetKey] = useState(0);
    const [showGuide, setShowGuide] = useState(false);
    // Actual natural size of the FULL-RES preview image (backend auto.natW/natH is
    // 1600-capped, so it can't be used to seed the crop box — scale would mismatch).
    const [natural, setNatural] = useState<{ w: number; h: number } | null>(null);

    useEffect(() => {
        setNatural(null);
        const img = new Image();
        img.onload = () => setNatural({ w: img.naturalWidth, h: img.naturalHeight });
        img.src = imageUrl;
    }, [imageUrl]);

    // Seed the crop box at the smart-detected face position, using the real image
    // dimensions × the scale-independent normalized rect from the backend.
    const initialArea = natural
        ? {
              x: auto.sx * natural.w,
              y: auto.sy * natural.h,
              width: auto.sw * natural.w,
              height: auto.sh * natural.h,
          }
        : undefined;

    const handleComplete = useCallback(
        (_area: unknown, px: { x: number; y: number; width: number; height: number }) => {
            if (!natural) {
                return;
            }
            onChange({
                sx: Math.max(0, Math.min(1, px.x / natural.w)),
                sy: Math.max(0, Math.min(1, px.y / natural.h)),
                sw: Math.max(0, Math.min(1, px.width / natural.w)),
                sh: Math.max(0, Math.min(1, px.height / natural.h)),
            });
        },
        [natural, onChange],
    );

    function reset() {
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setResetKey((k) => k + 1);
    }

    return (
        <div className="overflow-hidden rounded-xl border-2 border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-950">
            <div className="flex items-center justify-between border-b border-green-200 px-3 py-2 dark:border-green-800">
                <span className="flex items-center gap-1.5 text-sm font-medium text-green-800 dark:text-green-200">
                    <CheckCircle2 className="size-4" /> {filename}
                </span>
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-md p-1 text-green-600 hover:bg-green-200 dark:hover:bg-green-800"
                >
                    <X className="size-4" />
                </button>
            </div>
            <div className="p-4">
                <p className="mb-3 text-center text-xs font-medium text-green-700 dark:text-green-300">
                    Geser & zoom foto untuk atur posisi wajah
                </p>
                <div className="relative mx-auto h-80 w-full max-w-sm overflow-hidden rounded-lg bg-zinc-900">
                    {natural && initialArea ? (
                        <Cropper
                            key={resetKey}
                            image={imageUrl}
                            crop={crop}
                            zoom={zoom}
                            aspect={16 / 21}
                            minZoom={1}
                            maxZoom={5}
                            restrictPosition
                            objectFit="contain"
                            initialCroppedAreaPixels={initialArea}
                            onCropChange={setCrop}
                            onZoomChange={setZoom}
                            onCropComplete={handleComplete}
                            showGrid={false}
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center">
                            <Loader2 className="size-6 animate-spin text-white/70" />
                        </div>
                    )}
                </div>
                {/* Panduan crop */}
                <div className="mt-3 rounded-lg border border-green-200 bg-white/70 p-3 dark:border-green-800 dark:bg-zinc-900/40">
                    <p className="mb-2 text-xs font-semibold text-green-800 dark:text-green-200">Cara mengatur foto:</p>

                    {/* Poster panduan */}
                    <button type="button" onClick={() => setShowGuide(true)} className="mb-3 block w-full" title="Ketuk untuk perbesar">
                        <img
                            src="/images/panduan-foto.webp"
                            alt="Panduan atur foto: geser, zoom in, zoom out"
                            className="w-full rounded-lg border border-green-200 dark:border-green-800"
                            loading="lazy"
                        />
                        <span className="text-muted-foreground mt-1 block text-center text-[11px]">Ketuk gambar untuk perbesar</span>
                    </button>

                    <ul className="space-y-1.5 text-xs text-green-700 dark:text-green-300">
                        <li className="flex items-start gap-2">
                            <Move className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Geser foto:</b> tahan lalu tarik foto untuk atur posisi wajah.
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <ZoomIn className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Perbesar/perkecil:</b> pakai slider <b>Zoom</b> di bawah.
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <Crop className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Pastikan wajah penuh</b> di dalam kotak (rasio pas foto 3×4).
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <RotateCcw className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Reset:</b> kembalikan ke posisi otomatis.
                            </span>
                        </li>
                    </ul>
                </div>
                <div className="mt-3 flex items-center gap-3">
                    <span className="text-muted-foreground text-xs">Zoom</span>
                    <input
                        type="range"
                        min={1}
                        max={5}
                        step={0.05}
                        value={zoom}
                        onChange={(e) => setZoom(Number(e.target.value))}
                        className="h-1.5 flex-1 cursor-pointer accent-green-600"
                    />
                    <button
                        type="button"
                        onClick={reset}
                        className="rounded-md border border-green-300 px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-100 dark:border-green-700 dark:text-green-300 dark:hover:bg-green-900"
                    >
                        Reset
                    </button>
                </div>
            </div>

            {showGuide && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                    onClick={() => setShowGuide(false)}
                    role="button"
                    tabIndex={0}
                >
                    <img
                        src="/images/panduan-foto.webp"
                        alt="Panduan atur foto"
                        className="max-h-full max-w-full rounded-lg object-contain"
                    />
                    <button
                        type="button"
                        onClick={() => setShowGuide(false)}
                        className="absolute top-4 right-4 rounded-full bg-white/90 p-2 text-zinc-800 shadow"
                        aria-label="Tutup"
                    >
                        <X className="size-5" />
                    </button>
                </div>
            )}
        </div>
    );
}

function StepProgress({ current }: { current: number }) {
    return (
        <div className="flex items-center">
            {STEPS.map((s, i) => {
                const done = s.id < current;
                const active = s.id === current;

                return (
                    <div key={s.id} className="flex flex-1 items-center last:flex-none">
                        <div className="flex flex-col items-center">
                            <span
                                className={`flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-bold transition ${
                                    done
                                        ? 'bg-green-600 text-white'
                                        : active
                                          ? 'bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/25'
                                          : 'bg-zinc-200 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400'
                                }`}
                            >
                                {done ? <Check className="size-4" /> : s.id}
                            </span>
                            <span
                                className={`mt-1 hidden max-w-[72px] text-center text-[10px] leading-tight sm:block ${
                                    active ? 'font-semibold text-blue-600 dark:text-blue-400' : 'text-muted-foreground'
                                }`}
                            >
                                {s.title}
                            </span>
                        </div>
                        {i < STEPS.length - 1 && (
                            <div className={`mx-1 h-0.5 flex-1 rounded ${done ? 'bg-green-600' : 'bg-zinc-200 dark:bg-zinc-800'}`} />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function ReviewGroup({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
            <h4 className="mb-2 text-xs font-bold tracking-wide text-blue-600 uppercase dark:text-blue-400">{title}</h4>
            <dl className="grid gap-1.5">{children}</dl>
        </div>
    );
}

function ReviewRow({ label, value }: { label: string; value?: string | null }) {
    return (
        <div className="flex items-start justify-between gap-4 text-sm">
            <dt className="text-muted-foreground shrink-0">{label}</dt>
            <dd className="text-right font-medium break-words">{value || '—'}</dd>
        </div>
    );
}

function ResultTile({ item }: { item: StatusItem }) {
    const [copied, setCopied] = useState(false);

    async function copyLink() {
        if (!item.url) {
            return;
        }

        try {
            await navigator.clipboard.writeText(item.url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // clipboard unavailable — ignore
        }
    }

    return (
        <div className="flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            {/* Thumbnail / placeholder */}
            <div className="flex items-center justify-center bg-zinc-50 p-3 dark:bg-zinc-900" style={{ minHeight: 140 }}>
                {item.status === 'completed' && (item.thumb_url ?? item.url) ? (
                    <img src={item.thumb_url ?? item.url ?? undefined} alt={item.name} className="max-h-[200px] w-full object-contain" />
                ) : item.status === 'processing' ? (
                    <Loader2 className="size-8 animate-spin text-amber-500" />
                ) : (
                    <AlertTriangle className="size-8 text-red-500" />
                )}
            </div>

            {/* Meta + actions */}
            <div className="flex flex-1 flex-col gap-2 p-3">
                <div className="flex items-center justify-between gap-2">
                    <span className="text-sm font-medium">{item.name}</span>
                    {item.status === 'processing' && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                            <Loader2 className="size-3 animate-spin" /> Diproses
                        </span>
                    )}
                    {item.status === 'completed' && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300">
                            <Check className="size-3" /> Selesai
                        </span>
                    )}
                    {item.status === 'failed' && (
                        <span className="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/50 dark:text-red-300">
                            <X className="size-3" /> Gagal
                        </span>
                    )}
                </div>

                {item.status === 'completed' && item.url && (
                    <div className="mt-auto flex gap-2">
                        <button
                            type="button"
                            onClick={copyLink}
                            className="inline-flex flex-1 items-center justify-center gap-1 rounded-lg border border-zinc-300 px-2 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-200 dark:hover:bg-zinc-700"
                        >
                            {copied ? (
                                <>
                                    <Check className="size-3.5 text-green-600" /> Tersalin!
                                </>
                            ) : (
                                <>
                                    <Copy className="size-3.5" /> Salin Link
                                </>
                            )}
                        </button>
                        <a
                            href={item.thumb_url ?? item.url}
                            download
                            className="inline-flex flex-1 items-center justify-center gap-1 rounded-lg bg-blue-600 px-2 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                        >
                            <Download className="size-3.5" /> Unduh
                        </a>
                    </div>
                )}
            </div>
        </div>
    );
}

function PageWrapper({ children }: { children: React.ReactNode }) {
    return (
        <>
            <Head title="Pendaftaran Data Siswa Baru" />
            <div className="relative flex min-h-screen flex-col bg-zinc-50 dark:bg-zinc-950">
                {/* Grid pattern + gradient glow background */}
                <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
                    <div
                        className="absolute inset-0 opacity-[0.05] dark:opacity-[0.08] [mask-image:radial-gradient(ellipse_70%_55%_at_50%_0%,black,transparent)]"
                        style={{
                            backgroundImage:
                                'linear-gradient(to right, var(--foreground) 1px, transparent 1px), linear-gradient(to bottom, var(--foreground) 1px, transparent 1px)',
                            backgroundSize: '40px 40px',
                        }}
                    />
                    <div className="absolute -top-32 left-1/2 size-[42rem] -translate-x-1/2 rounded-full bg-blue-500/10 blur-[120px] dark:bg-blue-500/15" />
                </div>
                <div className="relative z-10 flex flex-1 flex-col">{children}</div>
            </div>
        </>
    );
}

function FormSection({ number, title, children }: { number: number; title: string; children: React.ReactNode }) {
    return (
        <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <div className="mb-5 flex items-center gap-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 text-sm font-bold text-white">
                    {number}
                </span>
                <h2 className="text-lg font-semibold">{title}</h2>
            </div>
            {children}
        </div>
    );
}

function Footer() {
    return (
        <footer className="mt-auto border-t border-zinc-200 py-6 text-center dark:border-zinc-800">
            <p className="text-muted-foreground text-sm">
                Powered by <span className="font-semibold">Tyas Photo</span>
            </p>
        </footer>
    );
}
