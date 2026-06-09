import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, CreditCard, Download, Eye, ExternalLink, Loader2, User, X } from 'lucide-react';
import { useCallback, useState } from 'react';
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

type GeneratedCard = {
    type: string;
    name: string;
    url: string | null;
    drive_url: string | null;
    status: string;
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
        photo_url: string | null;
    };
    photo_downloaded: boolean;
    cards: GeneratedCard[];
};

const religions = [
    { value: 'ISLAM', label: 'Islam' },
    { value: 'KRISTEN', label: 'Kristen Protestan' },
    { value: 'KATOLIK', label: 'Katolik' },
    { value: 'HINDU', label: 'Hindu' },
    { value: 'BUDDHA', label: 'Buddha' },
    { value: 'KONGHUCU', label: 'Konghucu' },
];

export default function StudentRegister({ schools, classrooms }: Props) {
    const { flash } = usePage().props as unknown as { flash: { success?: string } };
    const [submitted, setSubmitted] = useState(false);
    const [captchaVerified, setCaptchaVerified] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [loadingStep, setLoadingStep] = useState('');
    const [result, setResult] = useState<RegistrationResult | null>(null);
    const [showConfirm, setShowConfirm] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [photoPreview, setPhotoPreview] = useState<{ url: string; filename: string } | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState('');

    const { data, setData, processing, errors, reset } = useForm({
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
        parent_relation: 'WALI',
        photo_drive_filename: '',
        generate_cards: true,
    });

    const selectedSchool = schools.find((s) => String(s.id) === data.school_id);
    const filteredClassrooms = classrooms.filter((c) => String(c.school_id) === data.school_id);

    const csrfToken = typeof document !== 'undefined'
        ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
        : '';

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
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
        setLoadingStep(data.photo_drive_filename
            ? 'Menyimpan data & mengunduh foto dari Drive...'
            : data.generate_cards
                ? 'Menyimpan data & generate kartu...'
                : 'Menyimpan data siswa...');

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

            // Handle HTTP errors
            if (!res.ok) {
                const errorJson = await res.json().catch(() => null);
                if (res.status === 422 && errorJson?.errors) {
                    // Validation errors
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
                setResult(json);
                setSubmitted(true);
                reset();
            } else {
                throw new Error(json.message || 'Pendaftaran gagal');
            }
        } catch (err: unknown) {
            const message = err instanceof Error
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

    const handlePreviewPhoto = useCallback(async () => {
        if (!data.photo_drive_filename.trim() || !data.school_id) return;

        setPreviewLoading(true);
        setPreviewError('');
        setPhotoPreview(null);

        try {
            const res = await fetch('/daftar/preview-photo', {
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
            } else {
                setPreviewError(json.message || 'File tidak ditemukan.');
            }
        } catch {
            setPreviewError('Gagal menghubungi server.');
        } finally {
            setPreviewLoading(false);
        }
    }, [data.photo_drive_filename, data.school_id, csrfToken]);

    function handleNewSubmission() {
        setSubmitted(false);
        setResult(null);
        setCaptchaVerified(false);
    }

    // Success page with generated cards
    if (submitted && result) {
        return (
            <PageWrapper>
                <div className="mx-auto max-w-2xl px-4 py-8">
                    <div className="rounded-2xl border border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-950">
                        <CheckCircle2 className="mx-auto mb-4 size-16 text-green-600 dark:text-green-400" />
                        <h2 className="mb-2 text-center text-2xl font-bold text-green-800 dark:text-green-200">
                            Pendaftaran Berhasil!
                        </h2>
                        <p className="mb-6 text-center text-green-700 dark:text-green-300">{result.message}</p>

                        {/* Student info */}
                        <div className="mb-6 flex items-center gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-800">
                            {result.student.photo_url ? (
                                <img src={result.student.photo_url} alt={result.student.full_name} className="h-24 w-[72px] rounded-xl border-2 border-green-300 object-cover" />
                            ) : (
                                <div className="flex h-24 w-[72px] items-center justify-center rounded-xl border-2 border-green-300 bg-green-100 dark:bg-green-900"><User className="size-8 text-green-400" /></div>
                            )}
                            <div>
                                <h3 className="text-lg font-bold">{result.student.full_name}</h3>
                                <p className="text-muted-foreground text-sm">
                                    NIS: {result.student.nis}
                                    {result.student.nisn && ` · NISN: ${result.student.nisn}`}
                                </p>
                                <p className="text-muted-foreground text-sm">Kelas: {result.student.classroom}</p>
                                {result.photo_downloaded && (
                                    <span className="mt-1 inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">
                                        <CheckCircle2 className="size-3" /> Foto berhasil diambil dari Drive
                                    </span>
                                )}
                            </div>
                        </div>

                        {/* Generated cards */}
                        {result.cards.length > 0 && (
                            <div className="space-y-4">
                                <h3 className="flex items-center gap-2 text-lg font-semibold text-green-800 dark:text-green-200">
                                    <CreditCard className="size-5" /> Kartu yang Digenerate
                                </h3>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {result.cards.map((card, i) => (
                                        <div key={i} className="overflow-hidden rounded-xl border bg-white shadow-sm dark:bg-zinc-800">
                                            {card.url && card.status === 'completed' ? (
                                                <>
                                                    <img src={card.url} alt={card.name} className="w-full border-b object-contain" style={{ maxHeight: 300 }} />
                                                    <div className="flex items-center justify-between p-3">
                                                        <span className="text-sm font-medium">{card.name}</span>
                                                        <div className="flex gap-1">
                                                            <a href={card.url} target="_blank" rel="noreferrer" download>
                                                                <button className="rounded-lg bg-blue-600 p-1.5 text-white hover:bg-blue-700">
                                                                    <Download className="size-4" />
                                                                </button>
                                                            </a>
                                                            {card.drive_url && (
                                                                <a href={card.drive_url} target="_blank" rel="noreferrer">
                                                                    <button className="rounded-lg bg-green-600 p-1.5 text-white hover:bg-green-700">
                                                                        <ExternalLink className="size-4" />
                                                                    </button>
                                                                </a>
                                                            )}
                                                        </div>
                                                    </div>
                                                </>
                                            ) : (
                                                <div className="flex items-center gap-2 p-4 text-sm text-red-600">
                                                    <AlertTriangle className="size-4" />
                                                    {card.name} — Gagal digenerate
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Drive summary */}
                        {result.cards.some((c) => c.drive_url) ? (
                            <div className="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
                                <p className="text-sm font-medium text-blue-800 dark:text-blue-200">
                                    {result.photo_downloaded ? '3' : '2'} file tersimpan di Google Drive:
                                </p>
                                <ul className="mt-1 list-inside list-disc text-xs text-blue-700 dark:text-blue-300">
                                    {result.photo_downloaded && <li>Foto siswa (crop 3:4 portrait, WebP)</li>}
                                    {result.cards.filter((c) => c.status === 'completed').map((c, i) => (
                                        <li key={i}>{c.name}</li>
                                    ))}
                                </ul>
                                <p className="text-muted-foreground mt-2 text-xs">
                                    Lokasi: Kartu Siswa / {result.student.classroom} / {result.student.nis} - {result.student.full_name}
                                </p>
                            </div>
                        ) : result.cards.length > 0 && (
                            <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                                <p className="text-sm font-medium text-amber-800 dark:text-amber-200">
                                    <AlertTriangle className="mr-1 inline size-4" />
                                    Google Drive belum dikonfigurasi — kartu tersimpan lokal saja.
                                </p>
                                <p className="text-muted-foreground mt-1 text-xs">Hubungi admin untuk setup Google Drive agar kartu otomatis tersimpan ke cloud.</p>
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
                        <Button onClick={handleNewSubmission} className="bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/25">
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
                <div className="mb-8 text-center">
                    {selectedSchool?.logo_path ? (
                        <img src={`/storage/${selectedSchool.logo_path}`} alt={selectedSchool.name} className="mx-auto mb-4 size-16 rounded-xl object-contain" />
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

                <form onSubmit={handleSubmit} className="space-y-8">
                    {/* Section 1: Pilih Sekolah */}
                    <FormSection number={1} title="Pilih Sekolah">
                        <div className="grid gap-2">
                            <Label htmlFor="school_id" className="text-sm font-medium">Sekolah <span className="text-red-500">*</span></Label>
                            <Select value={data.school_id} onValueChange={(val) => { setData((prev) => ({ ...prev, school_id: val, classroom_id: '' })); }}>
                                <SelectTrigger className="h-11 w-full"><SelectValue placeholder="Pilih sekolah" /></SelectTrigger>
                                <SelectContent>
                                    {schools.map((school) => (<SelectItem key={school.id} value={String(school.id)}>{school.name}</SelectItem>))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.school_id || formErrors.school_id} />
                        </div>
                    </FormSection>

                    {/* Section 2: Data Siswa */}
                    <FormSection number={2} title="Data Siswa">
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="full_name" className="text-sm font-medium">Nama Lengkap <span className="text-red-500">*</span></Label>
                                <Input id="full_name" value={data.full_name} onChange={(e) => setData('full_name', e.target.value)} placeholder="Nama lengkap siswa" className="h-11" />
                                <InputError message={errors.full_name || formErrors.full_name} />
                            </div>

                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="nis" className="text-sm font-medium">NIS</Label>
                                    <Input id="nis" value={data.nis} onChange={(e) => setData('nis', e.target.value)} placeholder="Nomor Induk Siswa" className="h-11" />
                                    <InputError message={errors.nis || formErrors.nis} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="no_absen" className="text-sm font-medium">No. Absen</Label>
                                    <Input id="no_absen" value={data.no_absen} onChange={(e) => setData('no_absen', e.target.value)} placeholder="Nomor absen" className="h-11" />
                                    <InputError message={errors.no_absen || formErrors.no_absen} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="nisn" className="text-sm font-medium">NISN</Label>
                                    <Input id="nisn" value={data.nisn} onChange={(e) => setData('nisn', e.target.value)} placeholder="Nomor Induk Siswa Nasional" className="h-11" />
                                    <InputError message={errors.nisn || formErrors.nisn} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label className="text-sm font-medium">Jenis Kelamin <span className="text-red-500">*</span></Label>
                                <RadioGroup value={data.gender} onValueChange={(v) => setData('gender', v)} className="flex gap-4">
                                    <label htmlFor="gender-l" className={`flex flex-1 cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition ${data.gender === 'LAKI_LAKI' ? 'border-blue-500 bg-blue-50 dark:bg-blue-950' : 'border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-900'}`}>
                                        <RadioGroupItem value="LAKI_LAKI" id="gender-l" /> Laki-laki
                                    </label>
                                    <label htmlFor="gender-p" className={`flex flex-1 cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 text-sm transition ${data.gender === 'PEREMPUAN' ? 'border-pink-500 bg-pink-50 dark:bg-pink-950' : 'border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-900'}`}>
                                        <RadioGroupItem value="PEREMPUAN" id="gender-p" /> Perempuan
                                    </label>
                                </RadioGroup>
                                <InputError message={errors.gender || formErrors.gender} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="religion" className="text-sm font-medium">Agama</Label>
                                <Select value={data.religion} onValueChange={(val) => setData('religion', val)}>
                                    <SelectTrigger className="h-11 w-full"><SelectValue placeholder="Pilih agama" /></SelectTrigger>
                                    <SelectContent>
                                        {religions.map((r) => (<SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.religion} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="classroom_id" className="text-sm font-medium">Kelas <span className="text-red-500">*</span></Label>
                                <Select value={data.classroom_id} onValueChange={(val) => setData('classroom_id', val)} disabled={!data.school_id}>
                                    <SelectTrigger className="h-11 w-full"><SelectValue placeholder={data.school_id ? 'Pilih kelas' : 'Pilih sekolah terlebih dahulu'} /></SelectTrigger>
                                    <SelectContent>
                                        {filteredClassrooms.map((c) => (<SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.classroom_id || formErrors.classroom_id} />
                            </div>
                        </div>
                    </FormSection>

                    {/* Section 3: Data Kelahiran & Alamat */}
                    <FormSection number={3} title="Data Kelahiran & Alamat">
                        <div className="grid gap-5">
                            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="birth_place" className="text-sm font-medium">Tempat Lahir</Label>
                                    <Input id="birth_place" value={data.birth_place} onChange={(e) => setData('birth_place', e.target.value)} placeholder="Kota kelahiran" className="h-11" />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="birth_date" className="text-sm font-medium">Tanggal Lahir</Label>
                                    <Input id="birth_date" type="date" value={data.birth_date} onChange={(e) => setData('birth_date', e.target.value)} className="h-11" />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="address" className="text-sm font-medium">Alamat</Label>
                                <Textarea id="address" value={data.address} onChange={(e) => setData('address', e.target.value)} placeholder="Alamat lengkap siswa" rows={3} />
                            </div>
                        </div>
                    </FormSection>

                    {/* Section 4: Data Orang Tua/Wali */}
                    <FormSection number={4} title="Data Orang Tua/Wali">
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="parent_name" className="text-sm font-medium">Nama Orang Tua/Wali</Label>
                                <Input id="parent_name" value={data.parent_name} onChange={(e) => setData('parent_name', e.target.value)} placeholder="Nama lengkap orang tua atau wali" className="h-11" />
                            </div>
                            <div className="grid gap-2">
                                <Label className="text-sm font-medium">Hubungan</Label>
                                <Select value={data.parent_relation} onValueChange={(v) => setData('parent_relation', v)}>
                                    <SelectTrigger className="h-11"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="AYAH">Ayah</SelectItem>
                                        <SelectItem value="IBU">Ibu</SelectItem>
                                        <SelectItem value="WALI">Wali</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="parent_phone" className="text-sm font-medium">No. WhatsApp</Label>
                                <div className="flex">
                                    <span className="border-input bg-muted/50 text-muted-foreground inline-flex items-center rounded-l-md border border-r-0 px-3.5 text-sm font-medium">+62</span>
                                    <Input id="parent_phone" type="tel" value={data.parent_phone} onChange={(e) => setData('parent_phone', e.target.value)} placeholder="812xxxxxxxx" className="h-11 rounded-l-none" />
                                </div>
                                <p className="text-muted-foreground text-xs">Nomor ini akan menerima notifikasi absensi via WhatsApp.</p>
                            </div>
                        </div>
                    </FormSection>

                    {/* Section 5: Foto & Kartu */}
                    <FormSection number={5} title="Foto & Generate Kartu">
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="photo_drive_filename" className="text-sm font-medium">Nama File Foto di Google Drive</Label>
                                <div className="flex gap-2">
                                    <Input
                                        id="photo_drive_filename"
                                        value={data.photo_drive_filename}
                                        onChange={(e) => {
                                            setData('photo_drive_filename', e.target.value);
                                            setPhotoPreview(null);
                                            setPreviewError('');
                                        }}
                                        placeholder="Contoh: FIC_0008.JPG atau IMG_0234.png"
                                        className="h-11"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={!data.photo_drive_filename.trim() || !data.school_id || previewLoading}
                                        onClick={handlePreviewPhoto}
                                        className="h-11 shrink-0 gap-1.5"
                                    >
                                        {previewLoading ? <Loader2 className="size-4 animate-spin" /> : <Eye className="size-4" />}
                                        Cek Foto
                                    </Button>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Masukkan nama file foto siswa yang sudah diupload ke folder Foto Siswa di Google Drive.
                                    Klik "Cek Foto" untuk preview sebelum mendaftar.
                                </p>

                                {/* Preview Error */}
                                {previewError && (
                                    <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                                        <AlertTriangle className="size-4 shrink-0" />
                                        {previewError}
                                    </div>
                                )}

                                {/* Photo Preview */}
                                {photoPreview && (
                                    <div className="relative overflow-hidden rounded-xl border-2 border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-950">
                                        <div className="flex items-center justify-between border-b border-green-200 px-3 py-2 dark:border-green-800">
                                            <span className="flex items-center gap-1.5 text-sm font-medium text-green-800 dark:text-green-200">
                                                <CheckCircle2 className="size-4" /> {photoPreview.filename}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => setPhotoPreview(null)}
                                                className="rounded-md p-1 text-green-600 hover:bg-green-200 dark:hover:bg-green-800"
                                            >
                                                <X className="size-4" />
                                            </button>
                                        </div>
                                        <div className="flex justify-center p-4">
                                            <img
                                                src={photoPreview.url}
                                                alt={photoPreview.filename}
                                                className="max-h-64 rounded-lg object-contain shadow-md"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>

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
                                    <span className="text-muted-foreground block text-xs">Kartu otomatis digenerate dan disimpan ke Google Drive setelah data tersimpan.</span>
                                </label>
                            </div>
                        </div>
                    </FormSection>

                    {/* Captcha */}
                    <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                        <SimpleCaptcha onVerified={(token) => setCaptchaVerified(!!token)} />
                    </div>

                    {/* General Error */}
                    {(formErrors._general || Object.keys(formErrors).length > 0) && (
                        <div className="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-950">
                            {formErrors._general && (
                                <p className="mb-2 font-semibold text-red-700 dark:text-red-300">{formErrors._general}</p>
                            )}
                            {Object.entries(formErrors).filter(([k]) => k !== '_general').map(([key, msg]) => (
                                <p key={key} className="text-sm text-red-600 dark:text-red-400">• {msg}</p>
                            ))}
                        </div>
                    )}

                    {/* Submit */}
                    <Button
                        type="submit"
                        disabled={processing || generating || !captchaVerified}
                        className="h-12 w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-base font-semibold text-white shadow-lg shadow-blue-500/25 disabled:opacity-50"
                    >
                        {generating ? <Spinner /> : 'Daftarkan Siswa'}
                    </Button>
                </form>

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
                                {data.photo_drive_filename && <li>Foto diambil dari Drive: <b>{data.photo_drive_filename}</b></li>}
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
                    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
                        <div className="w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-2xl dark:bg-zinc-900">
                            <Loader2 className="mx-auto mb-4 size-12 animate-spin text-blue-600" />
                            <h3 className="mb-2 text-lg font-bold">Memproses Pendaftaran</h3>
                            <p className="text-muted-foreground mb-4 text-sm">{loadingStep || 'Mohon tunggu...'}</p>
                            <div className="mx-auto h-1.5 w-48 overflow-hidden rounded-full bg-zinc-200">
                                <div className="h-full animate-pulse rounded-full bg-blue-600" style={{ width: '60%' }} />
                            </div>
                            <p className="text-muted-foreground mt-4 text-xs">
                                Proses ini membutuhkan 10-30 detik. Jangan tutup halaman.
                            </p>
                        </div>
                    </div>
                )}
            </div>

            <Footer />
        </PageWrapper>
    );
}

function PageWrapper({ children }: { children: React.ReactNode }) {
    return (
        <>
            <Head title="Pendaftaran Data Siswa Baru" />
            <div className="min-h-screen bg-zinc-50 dark:bg-zinc-950">{children}</div>
        </>
    );
}

function FormSection({ number, title, children }: { number: number; title: string; children: React.ReactNode }) {
    return (
        <div className="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-900 sm:p-6">
            <div className="mb-5 flex items-center gap-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 text-sm font-bold text-white">{number}</span>
                <h2 className="text-lg font-semibold">{title}</h2>
            </div>
            {children}
        </div>
    );
}

function Footer() {
    return (
        <footer className="border-t border-zinc-200 py-6 text-center dark:border-zinc-800">
            <p className="text-muted-foreground text-sm">Powered by <span className="font-semibold">Tyas Photo</span></p>
        </footer>
    );
}
