import { Download, ExternalLink } from 'lucide-react';
import { KosongTanpaAnak, PortalPage } from '@/components/orangtua/portal-page';
import type { Anak } from '@/components/orangtua/portal-page';
import { Button } from '@/components/ui/button';
import { download } from '@/routes/orangtua';

type Kartu = {
    id: string;
    name: string;
    url: string | null;
    drive_url: string | null;
};

export default function Galeri({
    student,
    cards,
}: {
    student: Anak | null;
    cards: Kartu[];
}) {
    if (!student) {
        return (
            <PortalPage title="Foto & Kartu" student={null}>
                <KosongTanpaAnak />
            </PortalPage>
        );
    }

    const kosong = !student.photo && cards.length === 0;

    return (
        <PortalPage
            title="Foto & Kartu"
            description={`Pas foto dan kartu OSIS ${student.name}.`}
            student={student}
        >
            {kosong ? (
                <p className="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground">
                    Pas foto dan kartu belum tersedia. Sekolah akan
                    mengunggahnya setelah proses pemotretan selesai.
                </p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {student.photo && (
                        <Berkas
                            judul="Pas Foto"
                            gambar={student.photo}
                            unduh={`${download('foto').url}?anak=${student.id}`}
                            labelUnduh="Unduh pas foto"
                        />
                    )}
                    {cards.map((card) => (
                        <Berkas
                            key={card.id}
                            judul={card.name}
                            gambar={card.url}
                            unduh={card.url}
                            drive={card.drive_url}
                            labelUnduh="Unduh kartu"
                        />
                    ))}
                </div>
            )}
        </PortalPage>
    );
}

function Berkas({
    judul,
    gambar,
    unduh,
    drive,
    labelUnduh,
}: {
    judul: string;
    gambar: string | null;
    unduh: string | null;
    drive?: string | null;
    labelUnduh: string;
}) {
    return (
        <figure className="flex flex-col overflow-hidden rounded-xl border bg-card">
            <div className="flex h-56 items-center justify-center bg-muted/40 p-3">
                {gambar ? (
                    <img
                        src={gambar}
                        alt={judul}
                        className="max-h-full max-w-full object-contain"
                    />
                ) : (
                    <span className="text-sm text-muted-foreground">
                        Pratinjau belum tersedia
                    </span>
                )}
            </div>
            <figcaption className="flex items-center justify-between gap-2 border-t p-3">
                <span className="truncate text-sm font-medium">{judul}</span>
                {unduh ? (
                    <Button asChild size="sm" variant="outline">
                        <a href={unduh} download>
                            <Download className="size-4" />
                            <span className="sr-only sm:not-sr-only">
                                {labelUnduh}
                            </span>
                        </a>
                    </Button>
                ) : drive ? (
                    <Button asChild size="sm" variant="outline">
                        <a href={drive} target="_blank" rel="noreferrer">
                            <ExternalLink className="size-4" />
                            <span className="sr-only sm:not-sr-only">
                                Lihat di Drive
                            </span>
                        </a>
                    </Button>
                ) : null}
            </figcaption>
        </figure>
    );
}
