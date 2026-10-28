import { Deferred, Head, Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import { destroy, edit, generate, index } from '@/routes/kartu-bebas/peserta';
import { participantName, statusLabels } from './types';
import type { Participant } from './types';

type BarisKehadiran = {
    tanggal: string;
    status: string;
    masuk: string | null;
    pulang: string | null;
    /** Jumlah scan hari itu (semua scan tercatat, tidak hanya masuk/pulang). */
    scan: number;
    catatan: string | null;
};

const LABEL_KEHADIRAN: Record<string, string> = {
    hadir: 'Hadir',
    izin: 'Izin',
    sakit: 'Sakit',
    alpa: 'Alpa',
};

/** Seberapa sering riwayat kehadiran disegarkan selama halaman terbuka. */
const JEDA_KEHADIRAN_MS = 15_000;

export default function ParticipantShow({
    participant,
    kehadiran,
}: {
    participant: Participant;
    kehadiran?: BarisKehadiran[];
}) {
    // Scan dari gerbang langsung muncul di riwayat tanpa muat ulang manual.
    usePoll(JEDA_KEHADIRAN_MS, { only: ['kehadiran'] });
    const processing = participant.status === 'processing';
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const { start, stop } = usePoll(
        3000,
        { only: ['participant'] },
        { autoStart: false },
    );
    useEffect(() => {
        if (processing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [processing, start, stop]);

    return (
        <>
            <Head title={participantName(participant)} />
            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">
                        {participantName(participant)}
                    </h1>
                    <p>
                        {participant.layout_name} ·{' '}
                        {statusLabels[participant.status]}
                    </p>
                </div>
                {(participant.error || error) && (
                    <p role="alert" className="text-destructive">
                        {error || participant.error}
                    </p>
                )}
                {processing && (
                    <p role="status">
                        Kartu sedang dibuat. Halaman akan diperbarui otomatis.
                    </p>
                )}
                <div className="flex flex-wrap gap-3">
                    {!processing && (
                        <Button asChild variant="outline">
                            <Link href={edit.url(participant.id)}>
                                Edit Data & Foto
                            </Link>
                        </Button>
                    )}
                    <Button
                        disabled={processing || busy}
                        onClick={() => {
                            setBusy(true);
                            setError('');
                            router.post(
                                generate.url(participant.id),
                                {},
                                {
                                    onError: (errors) =>
                                        setError(
                                            Object.values(errors).join(' '),
                                        ),
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    >
                        {processing
                            ? 'Sedang diproses…'
                            : participant.preview_url
                              ? 'Generate Ulang'
                              : 'Generate Kartu'}
                    </Button>
                    {participant.download_url && (
                        <Button asChild variant="outline">
                            <a href={participant.download_url}>Unduh Kartu</a>
                        </Button>
                    )}
                    <Button
                        variant="destructive"
                        disabled={processing || busy}
                        onClick={() => {
                            if (
                                window.confirm(
                                    'Hapus peserta beserta foto, kartu, dan riwayat absensinya? Penghapusan tidak dapat dibatalkan.',
                                )
                            ) {
                                setBusy(true);
                                router.delete(destroy.url(participant.id), {
                                    onFinish: () => setBusy(false),
                                });
                            }
                        }}
                    >
                        Hapus Peserta
                    </Button>
                </div>
                <div className="grid gap-8 md:grid-cols-2">
                    <section className="space-y-4">
                        <h2 className="text-lg font-semibold">Data peserta</h2>
                        {participant.photo_url && (
                            <img
                                src={participant.photo_url}
                                alt="Foto peserta"
                                className="h-48 rounded"
                            />
                        )}
                        <dl className="space-y-3">
                            {participant.fields
                                .filter((field) => field.type !== 'photo')
                                .map((field) => (
                                    <div key={field.key}>
                                        <dt className="text-sm text-muted-foreground">
                                            {field.label}
                                        </dt>
                                        <dd className="break-words">
                                            {participant.data[field.key] ?? '—'}
                                        </dd>
                                    </div>
                                ))}
                        </dl>
                    </section>
                    <section className="space-y-4">
                        <h2 className="text-lg font-semibold">Preview kartu</h2>
                        {participant.preview_url ? (
                            <>
                                {participant.status !== 'completed' && (
                                    <p className="rounded-md bg-muted p-3">
                                        Ini hasil kartu sebelumnya. Perubahan
                                        data atau foto akan tampil setelah
                                        generate berhasil.
                                    </p>
                                )}
                                <img
                                    key={participant.updated_at}
                                    src={`${participant.preview_url}?v=${encodeURIComponent(participant.updated_at)}`}
                                    alt="Hasil kartu peserta"
                                    className="max-h-[650px] max-w-full rounded border"
                                />
                            </>
                        ) : (
                            <p className="text-muted-foreground">
                                Periksa data dan foto, lalu pilih Generate Kartu
                                untuk melihat hasilnya.
                            </p>
                        )}
                    </section>
                </div>

                <section className="rounded-lg border p-4">
                    <h2 className="mb-3 font-semibold">Riwayat kehadiran</h2>
                    <Deferred
                        data="kehadiran"
                        fallback={
                            <p className="text-sm text-muted-foreground">
                                Memuat riwayat…
                            </p>
                        }
                    >
                        {!kehadiran || kehadiran.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Belum ada catatan kehadiran.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr>
                                            <th className="py-2 pr-3 font-medium">
                                                Tanggal
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Status
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Masuk
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Pulang
                                            </th>
                                            <th className="py-2 pr-3 font-medium">
                                                Scan
                                            </th>
                                            <th className="py-2 font-medium">
                                                Catatan
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {kehadiran.map((k) => (
                                            <tr key={k.tanggal}>
                                                <td className="py-2 pr-3 whitespace-nowrap">
                                                    {new Intl.DateTimeFormat(
                                                        'id-ID',
                                                        {
                                                            weekday: 'short',
                                                            day: 'numeric',
                                                            month: 'short',
                                                            year: 'numeric',
                                                        },
                                                    ).format(
                                                        new Date(
                                                            `${k.tanggal}T00:00:00`,
                                                        ),
                                                    )}
                                                </td>
                                                <td className="py-2 pr-3">
                                                    {LABEL_KEHADIRAN[
                                                        k.status
                                                    ] ?? k.status}
                                                </td>
                                                <td className="py-2 pr-3 tabular-nums">
                                                    {k.masuk ?? '—'}
                                                </td>
                                                <td className="py-2 pr-3 tabular-nums">
                                                    {k.pulang ?? '—'}
                                                </td>
                                                <td className="py-2 pr-3 text-muted-foreground tabular-nums">
                                                    {k.scan > 0
                                                        ? `${k.scan}×`
                                                        : '—'}
                                                </td>
                                                <td className="py-2 text-muted-foreground">
                                                    {k.catatan ?? '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Deferred>
                </section>
            </div>
        </>
    );
}

ParticipantShow.layout = (page: React.ReactNode) => (
    <KartuBebasLayout breadcrumbs={[{ title: 'Peserta', href: index.url() }]}>
        {page}
    </KartuBebasLayout>
);
