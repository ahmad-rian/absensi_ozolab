import { useForm, usePage } from '@inertiajs/react';
import { Check, CheckCircle2, Loader2, User } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import {
    RegistrationFooter as Footer,
    RegistrationSection as FormSection,
    RegistrationHeader,
    RegistrationShell,
} from '@/components/shared/registration-shell';
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
    student: {
        id: string;
        full_name: string;
        nis: string;
        nisn: string | null;
        classroom: string | null;
    };
};

const religions = [
    { value: 'ISLAM', label: 'Islam' },
    { value: 'KRISTEN', label: 'Kristen Protestan' },
    { value: 'KATOLIK', label: 'Katolik' },
    { value: 'HINDU', label: 'Hindu' },
    { value: 'BUDDHA', label: 'Buddha' },
    { value: 'KONGHUCU', label: 'Konghucu' },
];

/*
    Versi 2 karena langkah Foto dibuang.

    Draf yang tersimpan dari versi sebelumnya memuat `step` sampai 6 dan field
    foto yang sudah tidak ada. Memakai kunci yang sama berarti orang yang sedang
    mengisi form mendarat di langkah yang salah begitu versi ini tayang — dan
    langkah itu tidak merender apa pun.
*/
const STORAGE_KEY = 'daftar_form_v2';

const STEPS = [
    { id: 1, title: 'Sekolah' },
    { id: 2, title: 'Data Siswa' },
    { id: 3, title: 'Kelahiran & Alamat' },
    { id: 4, title: 'Orang Tua' },
    { id: 5, title: 'Review & Kirim' },
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

    /*
        Draf dibaca sekali lewat inisialisator malas `useState`, bukan `useRef`.

        Versi sebelumnya menyimpannya di ref lalu membaca `.current` di tengah
        render — pelanggaran `react-hooks/refs` yang selama ini tidak terlihat
        karena berkasnya cukup rumit sampai React Compiler menyerah
        menganalisisnya. Begitu langkah Foto dibuang dan berkasnya menyusut,
        penganalisisnya berhasil jalan dan keduanya muncul.
    */
    const [awal] = useState(readPersisted);
    const [submitted, setSubmitted] = useState(false);
    const [captchaVerified, setCaptchaVerified] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [loadingStep, setLoadingStep] = useState('');
    const [result, setResult] = useState<RegistrationResult | null>(null);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [step, setStep] = useState(awal.step);
    const [stepErrors, setStepErrors] = useState<Record<string, string>>({});

    const { data, setData, processing, errors } = useForm<FormData>(awal.data);

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
        } else if (current === 2) {
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
        } else if (current === 3) {
            if (!data.birth_place.trim()) {
                e.birth_place = 'Tempat lahir wajib diisi.';
            }

            if (!data.birth_date) {
                e.birth_date = 'Tanggal lahir wajib diisi.';
            }

            if (!data.address.trim()) {
                e.address = 'Alamat wajib diisi.';
            }
        } else if (current === 4) {
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
        for (let s = 1; s <= 4; s++) {
            const e = validateStep(s);

            if (Object.keys(e).length > 0) {
                setStep(s);
                setStepErrors(e);

                return;
            }
        }

        doSubmit();
    }

    async function doSubmit() {
        setGenerating(true);
        setFormErrors({});
        setLoadingStep('Menyimpan data siswa...');

        const controller = new AbortController();
        // Tinggal satu INSERT sekarang — tidak ada unduhan Drive atau render
        // kartu yang menahan respons — tapi ambangnya dibiarkan longgar: yang
        // memakainya jaringan sekolah, bukan jalur ini.
        const timeout = setTimeout(() => controller.abort(), 120000);

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

    function handleNewSubmission() {
        setSubmitted(false);
        setResult(null);
        setCaptchaVerified(false);
    }

    // Halaman sukses. Tidak ada lagi yang berjalan di latar — foto dan kartu
    // diurus admin sekolah dari halaman siswa.
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
                            <div className="flex h-24 w-[72px] items-center justify-center rounded-xl border-2 border-green-300 bg-green-100 dark:bg-green-900">
                                <User className="size-8 text-green-400" />
                            </div>
                            <div>
                                <h3 className="text-lg font-bold">{result.student.full_name}</h3>
                                <p className="text-muted-foreground text-sm">
                                    NIS: {result.student.nis}
                                    {result.student.nisn && ` · NISN: ${result.student.nisn}`}
                                </p>
                                <p className="text-muted-foreground text-sm">Kelas: {result.student.classroom}</p>
                            </div>
                        </div>

                        {/*
                            Diucapkan, bukan didiamkan.

                            Sampai versi sebelumnya halaman ini memperlihatkan foto
                            dan empat kartu yang sedang dirender. Sekarang tidak ada
                            apa pun yang berjalan di latar, dan orang tua yang
                            menunggu hasil di layar ini akan menunggu selamanya
                            kalau tidak diberi tahu ke mana urusan itu pindah.
                        */}
                        <div className="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
                            <p className="text-sm font-semibold text-blue-800 dark:text-blue-200">Foto dan kartu diurus sekolah</p>
                            <p className="text-muted-foreground mt-1 text-xs">
                                Pas foto siswa dipasang oleh admin sekolah, lalu kartu OSIS dibuat dari foto itu. Tidak ada yang perlu
                                diunggah dari halaman ini.
                            </p>
                        </div>

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
                <RegistrationHeader
                    logoPath={selectedSchool?.logo_path}
                    schoolName={selectedSchool?.name}
                    title="Pendaftaran Data Siswa Baru"
                    subtitle="Lengkapi data siswa untuk didaftarkan ke sistem absensi sekolah."
                />

                {/* Progress indicator */}
                <StepProgress current={step} />

                <div className="mt-6">
                    {step === 1 && (
                        <FormSection number={1} title="Pilih Sekolah">
                            <div className="grid gap-2">
                                <Label htmlFor="school_id" className="text-sm font-medium" required>
                                    Sekolah
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

                    {step === 2 && (
                        <FormSection number={2} title="Data Siswa">
                            <div className="grid gap-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="full_name" className="text-sm font-medium" required>
                                        Nama Lengkap
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
                                        <Label htmlFor="no_absen" className="text-sm font-medium" required>
                                            No. Absen
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
                                        <Label htmlFor="nisn" className="text-sm font-medium" required>
                                            NISN
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
                                    <Label className="text-sm font-medium" required>
                                        Jenis Kelamin
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
                                    <Label htmlFor="religion" className="text-sm font-medium" required>
                                        Agama
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
                                    <Label htmlFor="classroom_id" className="text-sm font-medium" required>
                                        Kelas
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

                    {step === 3 && (
                        <FormSection number={3} title="Data Kelahiran & Alamat">
                            <div className="grid gap-5">
                                <div className="grid grid-cols-1 items-start gap-5 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="birth_place" className="text-sm font-medium" required>
                                            Tempat Lahir
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
                                        <Label htmlFor="birth_date" className="text-sm font-medium" required>
                                            Tanggal Lahir
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
                                    <Label htmlFor="address" className="text-sm font-medium" required>
                                        Alamat
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

                    {step === 4 && (
                        <FormSection number={4} title="Data Orang Tua/Wali">
                            <div className="grid gap-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="parent_name" className="text-sm font-medium" required>
                                        Nama Orang Tua/Wali
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
                                    <Label className="text-sm font-medium" required>
                                        Hubungan
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
                                    <Label htmlFor="parent_phone" className="text-sm font-medium" required>
                                        No. WhatsApp
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

                    {step === 5 && (
                        <FormSection number={5} title="Review & Kirim">
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
                            className="h-11 gap-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/25"
                        >
                            Lanjut
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
                            <p className="text-muted-foreground mt-4 text-xs">Sebentar saja. Jangan tutup halaman.</p>
                        </div>
                    </div>
                )}
            </div>

            <Footer />
        </PageWrapper>
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

function PageWrapper({ children }: { children: React.ReactNode }) {
    return <RegistrationShell title="Pendaftaran Data Siswa Baru">{children}</RegistrationShell>;
}
