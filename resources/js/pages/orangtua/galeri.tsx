import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { download } from '@/routes/orangtua';
import type { Child } from './anak';
export default function Galeri({
    student,
    cards,
}: {
    student: Child;
    cards: {
        id: string;
        name: string;
        url: string | null;
        drive_url: string | null;
    }[];
}) {
    return (
        <main className="space-y-6 p-6">
            <Head title="Foto dan kartu" />
            <h1 className="text-2xl font-semibold">
                Foto dan kartu {student.name}
            </h1>
            <div className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                {student.photo && (
                    <section className="space-y-4 rounded-xl border p-5">
                        <img
                            src={student.photo}
                            alt={`Pas foto ${student.name}`}
                            className="h-64 w-full object-contain"
                        />
                        <Button asChild variant="outline">
                            <a href={download([student.id, 'foto']).url}>
                                Unduh pas foto
                            </a>
                        </Button>
                    </section>
                )}
                {cards.map((card) => (
                    <section
                        className="space-y-4 rounded-xl border p-5"
                        key={card.id}
                    >
                        <h2 className="font-semibold">{card.name}</h2>
                        {card.url ? (
                            <>
                                <img
                                    src={card.url}
                                    alt={card.name}
                                    className="h-64 w-full object-contain"
                                />
                                <Button asChild variant="outline">
                                    <a href={card.url}>Unduh kartu</a>
                                </Button>
                            </>
                        ) : card.drive_url ? (
                            <a
                                href={card.drive_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Lihat kartu di Google Drive
                            </a>
                        ) : (
                            <p>Berkas belum tersedia.</p>
                        )}
                    </section>
                ))}
            </div>
            {!student.photo && cards.length === 0 && (
                <p>Foto dan kartu belum tersedia.</p>
            )}
        </main>
    );
}
