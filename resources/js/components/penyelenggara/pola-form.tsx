import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/penyelenggara/jadwal';

export const NAMA_HARI = ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

export type Pola = {
    id: string;
    name: string;
    pengulangan: 'mingguan' | 'rentang' | 'sekali';
    hari: number[];
    tanggal: string | null;
    tanggal_mulai: string | null;
    tanggal_selesai: string | null;
    grup_id: string | null;
    grup: string | null;
    datang_mulai: string;
    datang_selesai: string;
    batas_terlambat: string | null;
    pulang_mulai: string | null;
    pulang_selesai: string | null;
};

type ProgramJadwal = {
    id: string;
    starts_on: string;
    ends_on: string;
    istilah_grup: string;
    fitur: { terlambat: boolean; pulang: boolean };
};

type Props = {
    program: ProgramJadwal;
    grups: { id: string; name: string }[];
    pola?: Pola;
    onSelesai?: () => void;
};

const KELAS_SELECT = 'h-9 rounded-md border bg-background px-2 text-sm';

/**
 * Form pola sesi untuk tambah dan ubah. Saat mengubah, admin ditanya apakah
 * sesi hari ini ikut berubah — sesi hari ini yang sudah ada kehadirannya
 * hanya disentuh bila dijawab ya.
 */
export function PolaForm({ program, grups, pola, onSelesai }: Props) {
    const form = useForm({
        name: pola?.name ?? '',
        pengulangan: pola?.pengulangan ?? 'mingguan',
        hari: pola?.hari ?? [1, 2, 3, 4, 5],
        tanggal: pola?.tanggal ?? '',
        tanggal_mulai: pola?.tanggal_mulai ?? program.starts_on,
        tanggal_selesai: pola?.tanggal_selesai ?? program.ends_on,
        grup_id: pola?.grup_id ?? '',
        datang_mulai: pola?.datang_mulai ?? '07:00',
        datang_selesai: pola?.datang_selesai ?? '08:30',
        batas_terlambat:
            pola?.batas_terlambat ?? (program.fitur.terlambat ? '07:30' : ''),
        pulang_mulai:
            pola?.pulang_mulai ?? (program.fitur.pulang ? '15:00' : ''),
        pulang_selesai:
            pola?.pulang_selesai ?? (program.fitur.pulang ? '17:00' : ''),
        termasuk_hari_ini: false,
    });
    const { data, setData, errors } = form;

    const kirim = (e: React.FormEvent) => {
        e.preventDefault();

        if (!pola) {
            form.post(store.url(program.id), {
                preserveScroll: true,
                onSuccess: () => {
                    form.reset('name');
                    onSelesai?.();
                },
            });

            return;
        }

        const termasukHariIni = window.confirm(
            'Ubah juga sesi hari ini? Pilih OK bila perubahan berlaku mulai hari ini, Batal bila mulai besok.',
        );
        form.transform((d) => ({ ...d, termasuk_hari_ini: termasukHariIni }));
        form.put(update.url(pola.id), {
            preserveScroll: true,
            onSuccess: () => onSelesai?.(),
        });
    };

    const ubahHari = (h: number, pilih: boolean) =>
        setData(
            'hari',
            pilih ? [...data.hari, h] : data.hari.filter((x) => x !== h),
        );

    return (
        <form className="grid gap-3 rounded-md border p-3" onSubmit={kirim}>
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="grid gap-1 sm:col-span-2">
                    <Label htmlFor={`nama-sesi-${pola?.id ?? 'baru'}`}>
                        Nama sesi
                    </Label>
                    <Input
                        id={`nama-sesi-${pola?.id ?? 'baru'}`}
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        placeholder="Manasik Pagi"
                    />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor={`pengulangan-${pola?.id ?? 'baru'}`}>
                        Pengulangan
                    </Label>
                    <select
                        id={`pengulangan-${pola?.id ?? 'baru'}`}
                        className={KELAS_SELECT}
                        value={data.pengulangan}
                        onChange={(e) =>
                            setData(
                                'pengulangan',
                                e.target.value as Pola['pengulangan'],
                            )
                        }
                    >
                        <option value="mingguan">
                            Hari tertentu tiap minggu
                        </option>
                        <option value="rentang">Rentang tanggal</option>
                        <option value="sekali">Sekali</option>
                    </select>
                </div>
            </div>

            {data.pengulangan === 'sekali' ? (
                <div className="grid gap-1 sm:w-48">
                    <Input
                        type="date"
                        min={program.starts_on}
                        max={program.ends_on}
                        value={data.tanggal}
                        onChange={(e) => setData('tanggal', e.target.value)}
                        aria-label="Tanggal"
                    />
                    <InputError message={errors.tanggal} />
                </div>
            ) : (
                <div className="grid gap-2">
                    {data.pengulangan === 'rentang' && (
                        <div className="flex flex-wrap items-center gap-2 text-sm">
                            <Input
                                type="date"
                                className="w-44"
                                min={program.starts_on}
                                max={program.ends_on}
                                value={data.tanggal_mulai}
                                onChange={(e) =>
                                    setData('tanggal_mulai', e.target.value)
                                }
                                aria-label="Tanggal mulai"
                            />
                            sampai
                            <Input
                                type="date"
                                className="w-44"
                                min={program.starts_on}
                                max={program.ends_on}
                                value={data.tanggal_selesai}
                                onChange={(e) =>
                                    setData('tanggal_selesai', e.target.value)
                                }
                                aria-label="Tanggal selesai"
                            />
                            <InputError
                                message={
                                    errors.tanggal_mulai ??
                                    errors.tanggal_selesai
                                }
                            />
                        </div>
                    )}
                    <div className="flex flex-wrap gap-3 text-sm">
                        {[1, 2, 3, 4, 5, 6, 7].map((h) => (
                            <label key={h} className="flex items-center gap-1">
                                <input
                                    type="checkbox"
                                    checked={data.hari.includes(h)}
                                    onChange={(e) =>
                                        ubahHari(h, e.target.checked)
                                    }
                                />
                                {NAMA_HARI[h]}
                            </label>
                        ))}
                        <InputError message={errors.hari} />
                    </div>
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
                <JendelaJam
                    label="Datang"
                    mulai={data.datang_mulai}
                    selesai={data.datang_selesai}
                    onMulai={(v) => setData('datang_mulai', v)}
                    onSelesai={(v) => setData('datang_selesai', v)}
                    error={errors.datang_mulai ?? errors.datang_selesai}
                />
                {program.fitur.terlambat && (
                    <div className="grid gap-1">
                        <Label>Terlambat setelah</Label>
                        <Input
                            type="time"
                            value={data.batas_terlambat}
                            onChange={(e) =>
                                setData('batas_terlambat', e.target.value)
                            }
                            aria-label="Terlambat setelah"
                        />
                        <InputError message={errors.batas_terlambat} />
                    </div>
                )}
                {program.fitur.pulang && (
                    <JendelaJam
                        label="Pulang"
                        mulai={data.pulang_mulai}
                        selesai={data.pulang_selesai}
                        onMulai={(v) => setData('pulang_mulai', v)}
                        onSelesai={(v) => setData('pulang_selesai', v)}
                        error={errors.pulang_mulai ?? errors.pulang_selesai}
                    />
                )}
            </div>

            {grups.length > 0 && (
                <select
                    aria-label={program.istilah_grup}
                    className={`${KELAS_SELECT} sm:w-64`}
                    value={data.grup_id}
                    onChange={(e) => setData('grup_id', e.target.value)}
                >
                    <option value="">
                        Semua {program.istilah_grup.toLowerCase()}
                    </option>
                    {grups.map((g) => (
                        <option key={g.id} value={g.id}>
                            Khusus {g.name}
                        </option>
                    ))}
                </select>
            )}
            <InputError message={errors.grup_id} />

            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    {pola ? 'Simpan perubahan' : 'Tambah sesi'}
                </Button>
                {pola && onSelesai && (
                    <Button type="button" variant="ghost" onClick={onSelesai}>
                        Batal
                    </Button>
                )}
            </div>
        </form>
    );
}

function JendelaJam({
    label,
    mulai,
    selesai,
    onMulai,
    onSelesai,
    error,
}: {
    label: string;
    mulai: string;
    selesai: string;
    onMulai: (v: string) => void;
    onSelesai: (v: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-1">
            <Label>{label}</Label>
            <div className="flex gap-1">
                <Input
                    type="time"
                    value={mulai}
                    onChange={(e) => onMulai(e.target.value)}
                    aria-label={`Mulai ${label.toLowerCase()}`}
                />
                <Input
                    type="time"
                    value={selesai}
                    onChange={(e) => onSelesai(e.target.value)}
                    aria-label={`Akhir ${label.toLowerCase()}`}
                />
            </div>
            <InputError message={error} />
        </div>
    );
}
