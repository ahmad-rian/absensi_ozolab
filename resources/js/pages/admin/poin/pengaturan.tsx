import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import type { KategoriPoin } from '@/components/poin/catat-pelanggaran';
import { GAYA_LEVEL } from '@/components/poin/level-poin';
import type { AmbangLevel } from '@/components/poin/level-poin';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, pengaturan } from '@/routes/admin/poin';
import { update } from '@/routes/admin/poin/pengaturan';

type Setup = {
    terlambat: number;
    alpa: number;
    kategori: KategoriPoin[];
    level: AmbangLevel;
    semester_mulai: string;
};

const LEVEL = ['kuning', 'orange', 'merah'] as const;

/** Setup poin milik sekolah ini — angka, kategori, ambang level, awal semester. */
export default function PoinPengaturan({ setup }: { setup: Setup }) {
    const form = useForm<Setup>(setup);
    const errors = form.errors as Record<string, string | undefined>;

    const ubahKategori = (i: number, patch: Partial<KategoriPoin>) =>
        form.setData(
            'kategori',
            form.data.kategori.map((k, j) =>
                j === i ? { ...k, ...patch } : k,
            ),
        );

    const simpan = (e: React.FormEvent) => {
        e.preventDefault();
        form.submit(update(), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Atur Poin" />
            <form
                onSubmit={simpan}
                className="flex max-w-3xl flex-col gap-6 p-4 md:p-6"
            >
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Atur Poin
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Berlaku untuk sekolah ini saja. Perubahan angka hanya
                        memengaruhi catatan berikutnya — poin yang sudah
                        tercatat tidak dihitung ulang.
                    </p>
                </div>

                <section className="grid gap-4 rounded-xl border bg-card p-5">
                    <div>
                        <h2 className="font-semibold">Poin otomatis</h2>
                        <p className="text-sm text-muted-foreground">
                            Dicatat sistem tiap jam setelah jendela absen tutup.
                            Isi 0 untuk tidak menghitung.
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="terlambat">Terlambat</Label>
                            <Input
                                id="terlambat"
                                type="number"
                                min={0}
                                max={1000}
                                value={form.data.terlambat}
                                onChange={(e) =>
                                    form.setData(
                                        'terlambat',
                                        Number(e.target.value),
                                    )
                                }
                            />
                            <InputError message={errors.terlambat} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="alpa">
                                Tidak hadir tanpa keterangan
                            </Label>
                            <Input
                                id="alpa"
                                type="number"
                                min={0}
                                max={1000}
                                value={form.data.alpa}
                                onChange={(e) =>
                                    form.setData('alpa', Number(e.target.value))
                                }
                            />
                            <InputError message={errors.alpa} />
                        </div>
                    </div>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-5">
                    <div>
                        <h2 className="font-semibold">Kategori pelanggaran</h2>
                        <p className="text-sm text-muted-foreground">
                            Pilihan saat guru BK mencatat. Poinnya masih bisa
                            diubah per catatan.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        {form.data.kategori.map((k, i) => (
                            <div
                                key={i}
                                className="grid grid-cols-[1fr_6rem_auto] items-start gap-2"
                            >
                                <div>
                                    <Input
                                        aria-label="Nama kategori"
                                        value={k.nama}
                                        maxLength={100}
                                        onChange={(e) =>
                                            ubahKategori(i, {
                                                nama: e.target.value,
                                            })
                                        }
                                    />
                                    <InputError
                                        message={errors[`kategori.${i}.nama`]}
                                    />
                                </div>
                                <div>
                                    <Input
                                        aria-label="Poin"
                                        type="number"
                                        min={1}
                                        max={1000}
                                        value={k.poin}
                                        onChange={(e) =>
                                            ubahKategori(i, {
                                                poin: Number(e.target.value),
                                            })
                                        }
                                    />
                                    <InputError
                                        message={errors[`kategori.${i}.poin`]}
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Hapus kategori"
                                    onClick={() =>
                                        form.setData(
                                            'kategori',
                                            form.data.kategori.filter(
                                                (_, j) => j !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 className="size-4 text-destructive" />
                                </Button>
                            </div>
                        ))}
                        <InputError message={errors.kategori} />
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        className="justify-self-start"
                        disabled={form.data.kategori.length >= 50}
                        onClick={() =>
                            form.setData('kategori', [
                                ...form.data.kategori,
                                { nama: '', poin: 5 },
                            ])
                        }
                    >
                        <Plus className="size-4" /> Tambah kategori
                    </Button>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-5">
                    <div>
                        <h2 className="font-semibold">Level warna</h2>
                        <p className="text-sm text-muted-foreground">
                            Total poin semester mencapai angka ini → siswa masuk
                            level tersebut.
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        {LEVEL.map((l) => (
                            <div key={l} className="grid gap-1.5">
                                <Label
                                    htmlFor={`level-${l}`}
                                    className="flex items-center gap-1.5"
                                >
                                    <span
                                        className={cn(
                                            'size-2.5 rounded-full',
                                            GAYA_LEVEL[l].titik,
                                        )}
                                    />{' '}
                                    {GAYA_LEVEL[l].label} mulai
                                </Label>
                                <Input
                                    id={`level-${l}`}
                                    type="number"
                                    min={1}
                                    value={form.data.level[l]}
                                    onChange={(e) =>
                                        form.setData('level', {
                                            ...form.data.level,
                                            [l]: Number(e.target.value),
                                        })
                                    }
                                />
                                <InputError message={errors[`level.${l}`]} />
                            </div>
                        ))}
                    </div>
                </section>

                <section className="grid gap-4 rounded-xl border bg-card p-5">
                    <div>
                        <h2 className="font-semibold">Periode</h2>
                        <p className="text-sm text-muted-foreground">
                            Total dan level dihitung sejak tanggal ini. Ganti
                            tiap awal semester — riwayat lama tetap tersimpan.
                        </p>
                    </div>
                    <div className="grid max-w-xs gap-1.5">
                        <Label htmlFor="semester_mulai">Semester dimulai</Label>
                        <Input
                            id="semester_mulai"
                            type="date"
                            value={form.data.semester_mulai}
                            onChange={(e) =>
                                form.setData('semester_mulai', e.target.value)
                            }
                        />
                        <InputError message={errors.semester_mulai} />
                    </div>
                </section>

                <div>
                    <Button type="submit" disabled={form.processing}>
                        Simpan pengaturan
                    </Button>
                </div>
            </form>
        </>
    );
}

PoinPengaturan.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Poin Siswa', href: index() },
        { title: 'Atur Poin', href: pengaturan() },
    ],
};
