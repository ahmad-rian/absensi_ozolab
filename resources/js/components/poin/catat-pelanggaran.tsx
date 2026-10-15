import { Form } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cariSiswa, store } from '@/routes/admin/poin';

export type KategoriPoin = { nama: string; poin: number };

type PilihanSiswa = {
    id: string;
    nama: string;
    nis: string;
    kelas: string | null;
};

const JEDA_CARI_MS = 250;

const hariIni = () => {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/** Dialog guru BK: pilih siswa, pilih kategori (poin terisi), tulis alasan. */
export function CatatPelanggaran({
    open,
    onOpenChange,
    kategori,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    kategori: KategoriPoin[];
}) {
    const [cari, setCari] = useState('');
    const [hasil, setHasil] = useState<PilihanSiswa[]>([]);
    const [siswa, setSiswa] = useState<PilihanSiswa | null>(null);
    const [namaKategori, setNamaKategori] = useState(kategori[0]?.nama ?? '');
    const [poin, setPoin] = useState(String(kategori[0]?.poin ?? 5));

    useEffect(() => {
        if (siswa || cari.trim().length < 2) {
            return;
        }

        const pengendali = new AbortController();
        const jeda = setTimeout(() => {
            fetch(cariSiswa.url({ query: { q: cari.trim() } }), {
                headers: { Accept: 'application/json' },
                signal: pengendali.signal,
            })
                .then((r) => (r.ok ? r.json() : []))
                .then((data: PilihanSiswa[]) => setHasil(data))
                .catch(() => undefined);
        }, JEDA_CARI_MS);

        return () => {
            clearTimeout(jeda);
            pengendali.abort();
        };
    }, [cari, siswa]);

    const pilihKategori = (nama: string) => {
        setNamaKategori(nama);
        const k = kategori.find((x) => x.nama === nama);

        if (k) {
            setPoin(String(k.poin));
        }
    };

    const tutup = () => {
        onOpenChange(false);
        setSiswa(null);
        setCari('');
        setHasil([]);
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(o) => (o ? onOpenChange(true) : tutup())}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Catat pelanggaran</DialogTitle>
                    <DialogDescription>
                        Poin langsung masuk ke total semester siswa dan terlihat
                        orang tua di portal.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={tutup}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1.5">
                                <Label htmlFor="cari-siswa">Siswa</Label>
                                {siswa ? (
                                    <div className="flex items-center justify-between gap-2 rounded-md border bg-muted/40 px-3 py-2 text-sm">
                                        <span>
                                            <span className="font-medium">
                                                {siswa.nama}
                                            </span>{' '}
                                            <span className="text-muted-foreground">
                                                · {siswa.nis}
                                                {siswa.kelas
                                                    ? ` · ${siswa.kelas}`
                                                    : ''}
                                            </span>
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setSiswa(null)}
                                        >
                                            Ganti
                                        </Button>
                                    </div>
                                ) : (
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="cari-siswa"
                                            className="pl-9"
                                            placeholder="Ketik nama atau NIS (min. 2 huruf)…"
                                            value={cari}
                                            autoComplete="off"
                                            onChange={(e) =>
                                                setCari(e.target.value)
                                            }
                                        />
                                        {cari.trim().length >= 2 && (
                                            <div className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover shadow-md">
                                                {hasil.length === 0 ? (
                                                    <p className="px-3 py-2 text-sm text-muted-foreground">
                                                        Tidak ada siswa yang
                                                        cocok.
                                                    </p>
                                                ) : (
                                                    hasil.map((s) => (
                                                        <button
                                                            key={s.id}
                                                            type="button"
                                                            className="block w-full px-3 py-2 text-left text-sm hover:bg-muted"
                                                            onClick={() =>
                                                                setSiswa(s)
                                                            }
                                                        >
                                                            <span className="font-medium">
                                                                {s.nama}
                                                            </span>{' '}
                                                            <span className="text-muted-foreground">
                                                                · {s.nis}
                                                                {s.kelas
                                                                    ? ` · ${s.kelas}`
                                                                    : ''}
                                                            </span>
                                                        </button>
                                                    ))
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}
                                <input
                                    type="hidden"
                                    name="student_id"
                                    value={siswa?.id ?? ''}
                                />
                                <InputError message={errors.student_id} />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-[1fr_7rem]">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="kategori">Kategori</Label>
                                    <select
                                        id="kategori"
                                        name="kategori"
                                        className="h-9 rounded-md border bg-background px-2 text-sm"
                                        value={namaKategori}
                                        onChange={(e) =>
                                            pilihKategori(e.target.value)
                                        }
                                    >
                                        {kategori.map((k) => (
                                            <option key={k.nama} value={k.nama}>
                                                {k.nama} ({k.poin})
                                            </option>
                                        ))}
                                        <option value="Lainnya">Lainnya</option>
                                    </select>
                                    <InputError message={errors.kategori} />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="poin">Poin</Label>
                                    <Input
                                        id="poin"
                                        name="poin"
                                        type="number"
                                        min={1}
                                        max={1000}
                                        value={poin}
                                        onChange={(e) =>
                                            setPoin(e.target.value)
                                        }
                                    />
                                    <InputError message={errors.poin} />
                                </div>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="tanggal">
                                    Tanggal kejadian
                                </Label>
                                <Input
                                    id="tanggal"
                                    name="tanggal"
                                    type="date"
                                    defaultValue={hariIni()}
                                    max={hariIni()}
                                />
                                <InputError message={errors.tanggal} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="alasan">
                                    Alasan / kronologi
                                </Label>
                                <Textarea
                                    id="alasan"
                                    name="alasan"
                                    rows={3}
                                    maxLength={500}
                                    required
                                    placeholder="Contoh: keluar kelas tanpa izin saat jam ke-3."
                                />
                                <InputError message={errors.alasan} />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={tutup}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing || !siswa}
                                >
                                    Simpan pelanggaran
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
