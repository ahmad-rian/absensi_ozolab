import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import { riwayat } from '@/routes/kartu-bebas';
import { create, index, show } from '@/routes/kartu-bebas/peserta';
import { participantName, statusLabels } from './types';
import type { Participant } from './types';

type Props = {
    participants: {
        data: Participant[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    layouts: { id: string; name: string }[];
    filters: { q?: string; layout?: string; status?: string };
    history: boolean;
};
export default function ParticipantIndex({
    participants,
    layouts,
    filters,
    history,
}: Props) {
    const [query, setQuery] = useState(filters.q ?? '');
    const [layout, setLayout] = useState(filters.layout ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    usePoll(5000, { only: ['participants'] });
    const title = history ? 'Riwayat Kartu' : 'Peserta';

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">{title}</h1>
                        <p className="text-muted-foreground">
                            {participants.total} peserta · satu hasil kartu
                            terbaru per peserta.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create.url()}>Tambah Peserta</Link>
                    </Button>
                </div>
                <form
                    className="flex flex-wrap gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            history ? riwayat.url() : index.url(),
                            { q: query, layout, status },
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        className="w-full sm:w-64"
                        aria-label="Cari peserta"
                        placeholder="Cari nama atau data peserta"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                    <select
                        aria-label="Filter layout"
                        className="rounded-md border bg-background p-2"
                        value={layout}
                        onChange={(event) => setLayout(event.target.value)}
                    >
                        <option value="">Semua layout</option>
                        {layouts.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Filter status"
                        className="rounded-md border bg-background p-2"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">Semua status</option>
                        {Object.entries(statusLabels).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline">
                        Cari
                    </Button>
                </form>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Peserta</TableHead>
                            <TableHead>Layout</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Diperbarui</TableHead>
                            <TableHead>Aksi</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {participants.data.map((participant) => (
                            <TableRow key={participant.id}>
                                <TableCell>
                                    <Link
                                        className="flex items-center gap-3 font-medium underline"
                                        href={show.url(participant.id)}
                                    >
                                        {participant.photo_url && (
                                            <img
                                                src={participant.photo_url}
                                                alt=""
                                                className="h-12 w-9 rounded object-cover"
                                            />
                                        )}
                                        {participantName(participant)}
                                    </Link>
                                </TableCell>
                                <TableCell>{participant.layout_name}</TableCell>
                                <TableCell>
                                    {statusLabels[participant.status]}
                                </TableCell>
                                <TableCell>
                                    {new Date(
                                        participant.updated_at,
                                    ).toLocaleString('id-ID')}
                                </TableCell>
                                <TableCell>
                                    <Link
                                        className="underline"
                                        href={show.url(participant.id)}
                                    >
                                        Lihat peserta
                                    </Link>
                                </TableCell>
                            </TableRow>
                        ))}
                        {participants.data.length === 0 && (
                            <TableRow>
                                <TableCell colSpan={5}>
                                    {filters.q ||
                                    filters.layout ||
                                    filters.status
                                        ? 'Tidak ada peserta yang sesuai. Ubah filter atau kata pencarian.'
                                        : 'Belum ada peserta. Pilih Tambah Peserta untuk mengisi data dan foto.'}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
                <div className="flex items-center gap-4">
                    <Button
                        variant="outline"
                        disabled={!participants.prev_page_url}
                        onClick={() =>
                            participants.prev_page_url &&
                            router.visit(participants.prev_page_url)
                        }
                    >
                        Sebelumnya
                    </Button>
                    <span>
                        {participants.current_page} / {participants.last_page}
                    </span>
                    <Button
                        variant="outline"
                        disabled={!participants.next_page_url}
                        onClick={() =>
                            participants.next_page_url &&
                            router.visit(participants.next_page_url)
                        }
                    >
                        Berikutnya
                    </Button>
                </div>
            </div>
        </>
    );
}

ParticipantIndex.layout = (page: React.ReactNode) => (
    <KartuBebasLayout breadcrumbs={[{ title: 'Peserta', href: index.url() }]}>
        {page}
    </KartuBebasLayout>
);
