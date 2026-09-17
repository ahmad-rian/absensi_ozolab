import { router } from '@inertiajs/react';
import { AlertTriangle, ChevronUp, Folder, HardDrive, ImageOff, Loader2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

type Subfolder = { id: string; name: string };
type Gambar = { id: string; name: string; size: number | null; thumb: boolean };

type Isi = {
    tersedia: boolean;
    pesan?: string;
    akar?: string;
    folder?: { id: string; nama: string; induk: string | null };
    subfolder?: Subfolder[];
    gambar?: Gambar[];
};

function ukuran(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    return bytes >= 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`;
}

/**
 * Memilih pas foto dengan melihat isi folder Google Drive.
 *
 * Pelengkap pencocokan otomatis, bukan penggantinya. Pencocokan menebak dari
 * NIS dan nama siswa; begitu penamaan di Drive tidak mengikuti pola, tebakannya
 * meleset dan admin tidak punya jalan lain dari dalam aplikasi. Di sini yang
 * memutuskan matanya sendiri.
 *
 * Pratinjaunya dialirkan lewat server (`/drive/thumb/{id}`) karena
 * `thumbnailLink` milik Drive berumur pendek dan menuntut sesi Google si
 * pemilik berkas — dipasang langsung di `<img src>` ia hanya menghasilkan
 * gambar rusak.
 */
export function DrivePhotoPicker({ studentId }: { studentId: string }) {
    const [open, setOpen] = useState(false);
    const [isi, setIsi] = useState<Isi | null>(null);
    const [memuat, setMemuat] = useState(false);
    const [galat, setGalat] = useState('');
    const [dipilih, setDipilih] = useState<Gambar | null>(null);
    const [memasang, setMemasang] = useState(false);

    const muat = useCallback(
        async (tujuan: string | null) => {
            setMemuat(true);
            setGalat('');
            setDipilih(null);

            try {
                const url = tujuan
                    ? `/admin/siswa/${studentId}/drive/jelajah?folder=${encodeURIComponent(tujuan)}`
                    : `/admin/siswa/${studentId}/drive/jelajah`;

                const res = await fetch(url, { headers: { Accept: 'application/json' } });

                if (!res.ok) {
                    setGalat(
                        res.status === 403
                            ? 'Folder ini di luar folder sekolah.'
                            : 'Gagal membaca folder Google Drive.',
                    );

                    return;
                }

                const json = (await res.json()) as Isi;
                setIsi(json);
            } catch {
                setGalat('Gagal menghubungi server.');
            } finally {
                setMemuat(false);
            }
        },
        [studentId],
    );

    /*
        Dimuat di penangan klik, bukan di dalam `useEffect`.

        Dua alasan, dan keduanya nyata. Pertama: memanggil Drive saat halaman
        dirender berarti setiap kunjungan menembak API dua kali, padahal halaman
        siswa jauh lebih sering dibuka untuk mencetak QR daripada untuk
        mengganti foto. Kedua: `setState` di dalam efek memicu rentetan render
        dan ditolak `react-hooks/set-state-in-effect`. Membuka dialog memang
        sebuah peristiwa — di situlah tempatnya.
    */
    function buka() {
        setOpen(true);

        if (isi === null) {
            muat(null);
        }
    }

    function pasang() {
        if (!dipilih) {
            return;
        }

        setMemasang(true);
        router.post(
            `/admin/siswa/${studentId}/foto/drive`,
            { file_id: dipilih.id },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setMemasang(false),
            },
        );
    }

    return (
        <>
            <Button type="button" variant="outline" onClick={buka}>
                <HardDrive className="mr-2 size-4" />
                Ambil dari Google Drive
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-hidden sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>Ambil Pas Foto dari Google Drive</DialogTitle>
                        <DialogDescription>
                            {isi?.folder ? `Folder: ${isi.folder.nama}` : 'Membuka folder siswa…'}
                        </DialogDescription>
                    </DialogHeader>

                    {galat && (
                        <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                            <AlertTriangle className="size-4 shrink-0" />
                            {galat}
                        </div>
                    )}

                    {isi && !isi.tersedia && (
                        <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                            {isi.pesan}
                        </div>
                    )}

                    <div className="max-h-[55vh] min-h-40 overflow-y-auto">
                        {memuat ? (
                            <div className="flex h-40 items-center justify-center">
                                <Loader2 className="size-6 animate-spin text-zinc-400" />
                            </div>
                        ) : (
                            <div className="grid gap-4">
                                {(isi?.folder?.induk || (isi?.subfolder?.length ?? 0) > 0) && (
                                    <div className="grid gap-1">
                                        {isi?.folder?.induk && (
                                            <button
                                                type="button"
                                                onClick={() => muat(isi.folder!.induk)}
                                                className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                            >
                                                <ChevronUp className="size-4 shrink-0 text-zinc-500" />
                                                Naik satu tingkat
                                            </button>
                                        )}
                                        {isi?.subfolder?.map((f) => (
                                            <button
                                                key={f.id}
                                                type="button"
                                                onClick={() => muat(f.id)}
                                                className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                            >
                                                <Folder className="size-4 shrink-0 text-blue-500" />
                                                <span className="truncate">{f.name}</span>
                                            </button>
                                        ))}
                                    </div>
                                )}

                                {isi?.tersedia && (isi.gambar?.length ?? 0) === 0 && (
                                    <p className="text-muted-foreground py-6 text-center text-sm">
                                        Tidak ada gambar di folder ini.
                                    </p>
                                )}

                                {(isi?.gambar?.length ?? 0) > 0 && (
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        {isi!.gambar!.map((g) => (
                                            <button
                                                key={g.id}
                                                type="button"
                                                onClick={() => setDipilih(g)}
                                                className={`overflow-hidden rounded-lg border-2 text-left transition ${
                                                    dipilih?.id === g.id
                                                        ? 'border-blue-500 ring-2 ring-blue-500/30'
                                                        : 'border-zinc-200 hover:border-zinc-400 dark:border-zinc-700'
                                                }`}
                                            >
                                                <div className="flex h-24 items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                                                    {g.thumb ? (
                                                        <img
                                                            src={`/admin/siswa/${studentId}/drive/thumb/${g.id}`}
                                                            alt={g.name}
                                                            loading="lazy"
                                                            className="size-full object-cover"
                                                        />
                                                    ) : (
                                                        <ImageOff className="size-6 text-zinc-400" />
                                                    )}
                                                </div>
                                                <div className="p-1.5">
                                                    <p className="truncate text-xs font-medium" title={g.name}>
                                                        {g.name}
                                                    </p>
                                                    <p className="text-muted-foreground text-[11px]">{ukuran(g.size)}</p>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                            Batal
                        </Button>
                        <Button type="button" onClick={pasang} disabled={!dipilih || memasang}>
                            {memasang && <Loader2 className="mr-2 size-4 animate-spin" />}
                            {dipilih ? `Pakai ${dipilih.name}` : 'Pilih satu foto'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
