import { useForm } from '@inertiajs/react';
import { CheckCircle2, Loader2, Upload, User } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type SiswaAntre = {
    id: string;
    full_name: string;
    nis: string | null;
    classroom: string | null;
};

/**
 * Memasang pas foto beberapa siswa berturut-turut tanpa meninggalkan halaman.
 *
 * Sebelumnya tiap baris menautkan ke halaman edit siswa, yang berarti berpindah
 * halaman dan kembali lagi untuk setiap anak — dan untuk sekolah yang belum
 * dipilih di sesi, tautan itu bahkan 404 (lihat catatan di
 * `GenerateKartuMassalController::unggahFoto`).
 *
 * Antreannya bekerja dari SALINAN daftar, bukan dari prop yang hidup. Tiap
 * simpan memuat ulang `ringkasan`, dan daftar aslinya menyusut karena siswa
 * yang baru berfoto keluar dari sana; mengemudikan indeks dari daftar yang
 * mengerut sendiri membuat urutannya melompati orang.
 */
export function ModalPasFotoAntre({
    siswa,
    mulaiDari,
    urlUnggah,
    schoolId,
    onTutup,
}: {
    /** Daftar siswa tanpa foto pada saat modal dibuka. */
    siswa: SiswaAntre[];
    /** Indeks siswa yang tombolnya ditekan. */
    mulaiDari: number;
    urlUnggah: (studentId: string) => string;
    schoolId: string;
    onTutup: () => void;
}) {
    /*
        Salinan diambil sekali lewat inisialisator malas `useState`.

        Pemanggil merender komponen ini HANYA saat terbuka dan memberinya
        `key`, jadi tiap pembukaan adalah mount baru dan salinannya selalu
        segar. Itu yang membuat state awal boleh dibaca dari prop di sini tanpa
        menulis state saat render maupun menyalinnya di dalam efek — keduanya
        ditolak aturan React Compiler yang berlaku di repo ini.
    */
    const [antrean] = useState<SiswaAntre[]>(() => siswa);
    const [indeks, setIndeks] = useState(mulaiDari);
    const [pratinjau, setPratinjau] = useState<string | null>(null);
    const [terakhirTersimpan, setTerakhirTersimpan] = useState<string | null>(null);

    const form = useForm<{ photo: File | null; school_id: string }>({ photo: null, school_id: schoolId });

    const sekarang = antrean[indeks] ?? null;

    function gantiBerkas(file: File | null) {
        form.setData('photo', file);
        setPratinjau((lama) => {
            if (lama) {
                URL.revokeObjectURL(lama);
            }

            return file ? URL.createObjectURL(file) : null;
        });
    }

    // Object URL tidak dilepas browser sendiri; belasan siswa berarti belasan
    // gambar yang tertahan di memori sampai tabnya ditutup.
    useEffect(() => () => {
        if (pratinjau) {
            URL.revokeObjectURL(pratinjau);
        }
    }, [pratinjau]);

    function bersihkan() {
        gantiBerkas(null);
        form.clearErrors();
    }

    function tutup() {
        bersihkan();
        onTutup();
    }

    function maju(sesudahHabis: () => void) {
        bersihkan();

        if (indeks + 1 >= antrean.length) {
            sesudahHabis();

            return;
        }

        setIndeks(indeks + 1);
    }

    function simpan(e: React.FormEvent) {
        e.preventDefault();

        if (!sekarang) {
            return;
        }

        const nama = sekarang.full_name;

        form.post(urlUnggah(sekarang.id), {
            forceFormData: true,
            preserveScroll: true,
            // Tanpa `preserveState` komponennya remount dan modalnya tertutup
            // di tengah antrean. `only` menahan agar muat ulang tidak ikut
            // menarik batch yang sedang berjalan.
            preserveState: true,
            only: ['ringkasan'],
            onSuccess: () => {
                setTerakhirTersimpan(nama);
                maju(tutup);
            },
        });
    }

    if (!sekarang) {
        return null;
    }

    return (
        <Dialog open onOpenChange={(open) => !open && tutup()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {sekarang.full_name}
                        <span className="text-muted-foreground ml-2 text-sm font-normal tabular-nums">
                            {indeks + 1} dari {antrean.length}
                        </span>
                    </DialogTitle>
                    <DialogDescription>
                        {[sekarang.classroom, sekarang.nis ? `NIS ${sekarang.nis}` : null].filter(Boolean).join(' · ') ||
                            'Pasang pas foto siswa ini.'}
                    </DialogDescription>
                </DialogHeader>

                {terakhirTersimpan && (
                    <div className="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                        <CheckCircle2 className="size-4 shrink-0" />
                        Foto {terakhirTersimpan} tersimpan.
                    </div>
                )}

                <form onSubmit={simpan} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="foto-antre">Berkas foto</Label>
                        <Input
                            id="foto-antre"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) => gantiBerkas(e.target.files?.[0] ?? null)}
                        />
                        <p className="text-muted-foreground text-xs">JPG, PNG, atau WEBP. Maksimal 5 MB.</p>
                        <InputError message={form.errors.photo} />
                        <InputError message={form.errors.school_id} />
                    </div>

                    <div className="flex items-center gap-4">
                        {pratinjau ? (
                            <img src={pratinjau} alt="Pratinjau" className="size-32 rounded-lg border object-cover" />
                        ) : (
                            <div className="flex size-32 items-center justify-center rounded-lg border border-dashed bg-zinc-50 dark:bg-zinc-900">
                                <User className="size-10 text-zinc-400" />
                            </div>
                        )}
                        <p className="text-muted-foreground text-xs">
                            {pratinjau ? 'Pratinjau foto yang akan disimpan.' : 'Belum ada berkas dipilih.'}
                        </p>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-2">
                        <Button type="button" variant="ghost" onClick={() => maju(tutup)} disabled={form.processing}>
                            Lewati
                        </Button>
                        <Button type="button" variant="outline" onClick={tutup} disabled={form.processing}>
                            Tutup
                        </Button>
                        <Button type="submit" disabled={!form.data.photo || form.processing}>
                            {form.processing ? <Loader2 className="mr-2 size-4 animate-spin" /> : <Upload className="mr-2 size-4" />}
                            Simpan
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
