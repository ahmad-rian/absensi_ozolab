import { useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import InputError from '@/components/input-error';
import {
    BuktiFoto,
    LABEL_STATUS_IZIN,
    PilihanJenis,
    rentangTanggalIzin,
    WARNA_STATUS_IZIN,
} from '@/components/izin/izin-fields';
import type { JenisIzin } from '@/components/izin/izin-fields';
import { KosongTanpaAnak, PortalPage } from '@/components/orangtua/portal-page';
import type { Anak } from '@/components/orangtua/portal-page';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store } from '@/routes/orangtua/izin';

type Riwayat = {
    id: string;
    jenis: JenisIzin;
    tanggal_mulai: string;
    tanggal_selesai: string;
    alasan: string;
    status: string;
    catatan_review: string | null;
    dikirim: string | null;
};

const hariIni = () => new Date().toLocaleDateString('sv-SE');

/** Orang tua mengajukan izin/sakit; sekolah meninjau dan mengisi absensi. */
export default function IzinOrangTua({
    student,
    riwayat,
    maksHari,
}: {
    student: Anak | null;
    riwayat: Riwayat[];
    maksHari: number;
}) {
    const form = useForm<{
        jenis: JenisIzin | '';
        tanggal_mulai: string;
        tanggal_selesai: string;
        alasan: string;
        bukti: File | null;
    }>({
        jenis: '',
        tanggal_mulai: hariIni(),
        tanggal_selesai: hariIni(),
        alasan: '',
        bukti: null,
    });

    if (!student) {
        return (
            <PortalPage title="Ajukan Izin" student={null}>
                <KosongTanpaAnak />
            </PortalPage>
        );
    }

    const kirim = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`${store.url()}?anak=${student.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <PortalPage
            title="Ajukan Izin"
            description={`Kabari sekolah bila ${student.name} sakit atau berhalangan hadir.`}
            student={student}
        >
            <div className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Pengajuan baru
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={kirim} className="grid gap-5">
                            <div className="grid gap-2">
                                <Label>Jenis</Label>
                                <PilihanJenis
                                    nilai={form.data.jenis}
                                    onUbah={(j) => form.setData('jenis', j)}
                                />
                                <InputError message={form.errors.jenis} />
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <Label htmlFor="tanggal_mulai">
                                        Dari tanggal
                                    </Label>
                                    <Input
                                        id="tanggal_mulai"
                                        type="date"
                                        value={form.data.tanggal_mulai}
                                        onChange={(e) => {
                                            form.setData(
                                                'tanggal_mulai',
                                                e.target.value,
                                            );

                                            if (
                                                form.data.tanggal_selesai <
                                                e.target.value
                                            ) {
                                                form.setData(
                                                    'tanggal_selesai',
                                                    e.target.value,
                                                );
                                            }
                                        }}
                                    />
                                    <InputError
                                        message={form.errors.tanggal_mulai}
                                    />
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="tanggal_selesai">
                                        Sampai tanggal
                                    </Label>
                                    <Input
                                        id="tanggal_selesai"
                                        type="date"
                                        min={form.data.tanggal_mulai}
                                        value={form.data.tanggal_selesai}
                                        onChange={(e) =>
                                            form.setData(
                                                'tanggal_selesai',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.tanggal_selesai}
                                    />
                                </div>
                                <p className="text-xs text-muted-foreground sm:col-span-2">
                                    Maksimal {maksHari} hari sekali ajukan. Hari
                                    libur tidak dihitung.
                                </p>
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="alasan">Alasan</Label>
                                <Textarea
                                    id="alasan"
                                    rows={3}
                                    maxLength={500}
                                    value={form.data.alasan}
                                    onChange={(e) =>
                                        form.setData('alasan', e.target.value)
                                    }
                                    placeholder="Contoh: demam sejak semalam, berobat ke puskesmas."
                                />
                                <InputError message={form.errors.alasan} />
                            </div>

                            <div className="grid gap-1.5">
                                <Label>
                                    Bukti foto{' '}
                                    <span className="font-normal text-muted-foreground">
                                        (opsional)
                                    </span>
                                </Label>
                                <BuktiFoto
                                    berkas={form.data.bukti}
                                    onUbah={(f) => form.setData('bukti', f)}
                                />
                                <InputError message={form.errors.bukti} />
                            </div>

                            <Button
                                type="submit"
                                disabled={form.processing || !form.data.jenis}
                                className="justify-self-start"
                            >
                                <Send className="size-4" /> Kirim pengajuan
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Riwayat pengajuan
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {riwayat.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada pengajuan.
                            </p>
                        ) : (
                            <ul className="grid gap-3">
                                {riwayat.map((r) => (
                                    <li
                                        key={r.id}
                                        className="rounded-lg border p-3 text-sm"
                                    >
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="font-medium">
                                                {r.jenis === 'SAKIT'
                                                    ? 'Sakit'
                                                    : 'Izin'}{' '}
                                                ·{' '}
                                                {rentangTanggalIzin(
                                                    r.tanggal_mulai,
                                                    r.tanggal_selesai,
                                                )}
                                            </span>
                                            <span
                                                className={`rounded-full border px-2 py-0.5 text-xs font-medium ${WARNA_STATUS_IZIN[r.status] ?? ''}`}
                                            >
                                                {LABEL_STATUS_IZIN[r.status] ??
                                                    r.status}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-muted-foreground">
                                            {r.alasan}
                                        </p>
                                        {r.catatan_review && (
                                            <p className="mt-2 rounded-md bg-muted px-2 py-1.5 text-xs">
                                                Catatan sekolah:{' '}
                                                {r.catatan_review}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </PortalPage>
    );
}
