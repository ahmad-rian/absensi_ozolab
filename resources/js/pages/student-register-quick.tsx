import { AlertTriangle, CheckCircle2, Loader2, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import {
    RegistrationFooter,
    RegistrationHeader,
    RegistrationSection,
    RegistrationShell,
} from '@/components/shared/registration-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

/**
 * Pendaftaran pendek untuk sesi foto sekolah.
 *
 * Empat isian, satu halaman, tanpa wizard: nama, nomor foto, kelas, nomor absen.
 * Di depan antrean yang tersedia cuma daftar hadir dan nomor fotonya, dan form
 * panjang di `/daftar` menagih NIS/NISN, tempat & tanggal lahir, alamat, serta
 * seluruh data orang tua sebelum mau menyimpan.
 *
 * Yang sengaja TIDAK ada di sini, dan alasannya:
 *
 * - **NIS/NISN.** Tidak ditanya. `students.nis` tetap diisi otomatis oleh server
 *   karena kolomnya NOT NULL dan unik per sekolah; admin bisa menimpanya nanti.
 * - **Token QR.** Tidak diterbitkan. Siswa dari sini belum bisa absen sampai
 *   datanya dilengkapi lewat admin.
 * - **Kartu & lembar pas foto.** Tidak dirender, tidak ada yang diunggah ke
 *   Drive. Satu-satunya kerja latar adalah menarik fotonya sendiri dari Drive.
 * - **Kotak croping.** Foto Drive dipakai apa adanya — lihat catatan di
 *   `components/shared/registration-crop-reposition.tsx`.
 */

type School = { id: string; name: string; logo_path: string | null };
type Classroom = { id: string; school_id: string; name: string; grade_level: number };

type Props = {
    schools: School[];
    classrooms: Classroom[];
    registrationToken: string;
};

type FormData = {
    school_id: string;
    full_name: string;
    classroom_id: string;
    no_absen: string;
    photo_drive_filename: string;
    photo_key: string;
};

type Saved = {
    full_name: string;
    classroom: string | null;
    no_absen: string | null;
    photo_drive_filename: string | null;
};

const INITIAL: FormData = {
    school_id: '',
    full_name: '',
    classroom_id: '',
    no_absen: '',
    photo_drive_filename: '',
    photo_key: '',
};

export default function StudentRegisterQuick({ schools, classrooms, registrationToken }: Props) {
    const [data, setData] = useState<FormData>(INITIAL);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [saved, setSaved] = useState<Saved | null>(null);

    const [previewLoading, setPreviewLoading] = useState(false);
    const [previewError, setPreviewError] = useState('');
    const [photoPreview, setPhotoPreview] = useState<{ url: string; filename: string } | null>(null);
    // Gambar sudah benar-benar tampil di browser, bukan sekadar respons server.
    const [photoReady, setPhotoReady] = useState(false);
    // Pencarian jalan tiap kali user berhenti mengetik, jadi beberapa permintaan
    // bisa terbang bersamaan; hanya yang terbaru boleh menulis state.
    const photoRequestRef = useRef(0);

    const csrfToken =
        typeof document !== 'undefined' ? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '' : '';

    const filteredClassrooms = classrooms.filter((c) => String(c.school_id) === data.school_id);

    function set<K extends keyof FormData>(key: K, value: FormData[K]) {
        setData((prev) => ({ ...prev, [key]: value }));
        setErrors((prev) => {
            const next = { ...prev };
            delete next[key as string];

            return next;
        });
    }

    const loadPhoto = useCallback(async () => {
        if (!data.photo_drive_filename.trim() || !data.school_id) {
            return;
        }

        const requestId = ++photoRequestRef.current;
        const isStale = () => requestId !== photoRequestRef.current;

        setPreviewLoading(true);
        setPreviewError('');
        setPhotoPreview(null);
        setPhotoReady(false);
        setData((prev) => ({ ...prev, photo_key: '' }));

        try {
            const res = await fetch('/daftar/preview-photo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    token: registrationToken,
                    school_id: data.school_id,
                    filename: data.photo_drive_filename.trim(),
                }),
            });

            const json = await res.json();

            if (isStale()) {
                return;
            }

            if (json.found) {
                setPhotoPreview({ url: json.preview_url, filename: data.photo_drive_filename.trim() });
                setData((prev) => ({ ...prev, photo_key: json.photo_key ?? '' }));
            } else {
                setPreviewError(json.message || 'File tidak ditemukan.');
            }
        } catch {
            if (!isStale()) {
                setPreviewError('Gagal menghubungi server.');
            }
        } finally {
            if (!isStale()) {
                setPreviewLoading(false);
            }
        }
    }, [data.photo_drive_filename, data.school_id, csrfToken, registrationToken]);

    useEffect(() => {
        if (!data.photo_drive_filename.trim() || !data.school_id) {
            return;
        }

        const t = setTimeout(() => {
            loadPhoto();
        }, 650);

        return () => clearTimeout(t);
    }, [data.photo_drive_filename, data.school_id, loadPhoto]);

    async function submit() {
        setSubmitting(true);
        setErrors({});

        try {
            const res = await fetch('/quick-regis', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify(data),
            });

            const json = await res.json();

            if (res.status === 422) {
                setErrors(Object.fromEntries(Object.entries(json.errors ?? {}).map(([k, v]) => [k, (v as string[])[0]])));

                return;
            }

            if (json.success) {
                /*
                 | Panel ringkas di tempat, bukan halaman hasil `/daftar`.
                 |
                 | Halaman itu memantau status kartu dan lembar pas foto lewat
                 | `/daftar/status/{student}`; form ini tidak menghasilkan
                 | keduanya, jadi pemantauannya tidak akan pernah selesai dan
                 | pendaftar hanya melihat spinner yang berputar selamanya.
                 |
                 | Sekolah dan kelas sengaja tidak ikut dikosongkan: operator
                 | mendaftarkan satu kelas berturut-turut, dan memilih ulang
                 | keduanya di tiap siswa adalah dua ketukan yang terbuang.
                 */
                setSaved(json.student);
                setData((prev) => ({
                    ...prev,
                    full_name: '',
                    no_absen: '',
                    photo_drive_filename: '',
                    photo_key: '',
                }));
                setPhotoPreview(null);
                setPhotoReady(false);
                setPreviewError('');
            }
        } catch {
            setErrors({ full_name: 'Gagal menghubungi server. Coba lagi.' });
        } finally {
            setSubmitting(false);
        }
    }

    const selectedSchool = schools.find((s) => String(s.id) === data.school_id);
    const canSubmit =
        !submitting &&
        Boolean(data.school_id && data.full_name.trim() && data.classroom_id && data.no_absen.trim()) &&
        Boolean(data.photo_key) &&
        photoReady;

    return (
        <RegistrationShell title="Pendaftaran Data Siswa">
            <div className="mx-auto w-full max-w-2xl px-4 py-8 sm:py-12">
                <RegistrationHeader
                    logoPath={selectedSchool?.logo_path}
                    schoolName={selectedSchool?.name}
                    title="Pendaftaran Data Siswa"
                    subtitle={
                        selectedSchool
                            ? selectedSchool.name
                            : 'Empat isian saja: nama, kelas, nomor absen, dan nomor foto.'
                    }
                />

                <RegistrationSection number={1} title="Data Siswa">
                    <div className="space-y-5">
                        <div className="grid gap-2">
                            <Label htmlFor="school_id" required>
                                Sekolah
                            </Label>
                            <Select
                                value={data.school_id}
                                onValueChange={(value) => {
                                    set('school_id', value);
                                    // Kelas milik sekolah lain tidak boleh ikut terbawa.
                                    set('classroom_id', '');
                                }}
                            >
                                <SelectTrigger id="school_id" className="h-11 w-full">
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
                            <InputError message={errors.school_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="full_name" required>
                                Nama Lengkap
                            </Label>
                            <Input
                                id="full_name"
                                value={data.full_name}
                                onChange={(e) => set('full_name', e.target.value)}
                                className="h-11"
                                autoComplete="off"
                            />
                            <InputError message={errors.full_name} />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="classroom_id" required>
                                    Kelas
                                </Label>
                                <Select
                                    value={data.classroom_id}
                                    onValueChange={(value) => set('classroom_id', value)}
                                    disabled={!data.school_id}
                                >
                                    <SelectTrigger id="classroom_id" className="h-11 w-full">
                                        <SelectValue placeholder={data.school_id ? 'Pilih kelas' : 'Pilih sekolah dulu'} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {filteredClassrooms.map((classroom) => (
                                            <SelectItem key={classroom.id} value={String(classroom.id)}>
                                                {classroom.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.classroom_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="no_absen" required>
                                    No. Absen
                                </Label>
                                <Input
                                    id="no_absen"
                                    value={data.no_absen}
                                    onChange={(e) => set('no_absen', e.target.value)}
                                    className="h-11"
                                />
                                <InputError message={errors.no_absen} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="photo_drive_filename" required>
                                No. Foto (nama file di Google Drive)
                            </Label>
                            <div className="relative">
                                <Input
                                    id="photo_drive_filename"
                                    value={data.photo_drive_filename}
                                    onChange={(e) => {
                                        set('photo_drive_filename', e.target.value);
                                        setPhotoPreview(null);
                                        setPhotoReady(false);
                                        setPreviewError('');
                                    }}
                                    placeholder="Contoh: FIC_0008.JPG"
                                    className="h-11 pr-10"
                                    autoComplete="off"
                                />
                                {previewLoading && (
                                    <span className="absolute top-1/2 right-3 -translate-y-1/2">
                                        <Spinner className="size-4" />
                                    </span>
                                )}
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Ketik nomor/nama filenya — fotonya muncul otomatis di bawah.
                            </p>
                            <InputError message={errors.photo_drive_filename} />
                            {previewError && (
                                <div className="flex items-center gap-2 rounded-lg bg-red-50 p-2 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
                                    <AlertTriangle className="size-4 shrink-0" />
                                    {previewError}
                                </div>
                            )}
                        </div>

                        {photoPreview && (
                            <div className="rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900">
                                <img
                                    src={photoPreview.url}
                                    alt={photoPreview.filename}
                                    onLoad={() => setPhotoReady(true)}
                                    onError={() => {
                                        setPhotoReady(false);
                                        setPreviewError('Foto gagal dimuat. Coba ketik ulang nama berkasnya.');
                                    }}
                                    className="mx-auto max-h-80 w-auto rounded-lg object-contain"
                                />
                                <div className="mt-3 flex items-center justify-between gap-2">
                                    <p className="text-muted-foreground truncate text-xs" title={photoPreview.filename}>
                                        {photoPreview.filename}
                                    </p>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setPhotoPreview(null);
                                            setPhotoReady(false);
                                            setData((prev) => ({ ...prev, photo_drive_filename: '', photo_key: '' }));
                                        }}
                                    >
                                        <X className="mr-1 size-4" />
                                        Ganti
                                    </Button>
                                </div>
                            </div>
                        )}

                        <Button className="h-12 w-full text-base" onClick={submit} disabled={!canSubmit}>
                            {submitting && <Loader2 className="mr-2 size-4 animate-spin" />}
                            Simpan
                        </Button>
                    </div>
                </RegistrationSection>

                {saved && (
                    <div className="mt-4 rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950">
                        <p className="flex items-center gap-2 text-sm font-semibold text-green-900 dark:text-green-100">
                            <CheckCircle2 className="size-4 shrink-0" />
                            {saved.full_name} tersimpan.
                        </p>
                        <p className="mt-1 text-xs text-green-800 dark:text-green-200">
                            {[saved.classroom, saved.no_absen && `absen ${saved.no_absen}`, saved.photo_drive_filename]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                        <p className="mt-2 text-xs text-green-700 dark:text-green-300">
                            Sekolah dan kelas dibiarkan terisi — langsung ketik siswa berikutnya.
                        </p>
                    </div>
                )}
            </div>

            <RegistrationFooter />
        </RegistrationShell>
    );
}
