import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import { destroy, edit, generate, index } from '@/routes/kartu-bebas/peserta';
import { participantName, statusLabels } from './types';
import type { Participant } from './types';

export default function ParticipantShow({
    participant,
}: {
    participant: Participant;
}) {
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
            </div>
        </>
    );
}

ParticipantShow.layout = (page: React.ReactNode) => (
    <KartuBebasLayout breadcrumbs={[{ title: 'Peserta', href: index.url() }]}>
        {page}
    </KartuBebasLayout>
);
