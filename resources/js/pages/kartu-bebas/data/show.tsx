import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, LayoutTemplate, Pencil, Plus, Trash2 } from 'lucide-react';
import { LinkFormPublik } from '@/components/shared/link-form-publik';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import { FIELD_TYPE_LABELS  } from './field-builder';
import type {FormField} from './field-builder';

type Dataset = {
    id: string;
    name: string;
    fields: FormField[];
    layouts_count: number;
    created_at: string | null;
};

type LayoutItem = {
    id: string;
    name: string;
    orientation: string;
    is_active: boolean;
    publik: boolean;
    public_url: string;
    submissions_count: number;
};

export default function KartuBebasDataShow({ dataset, layouts }: { dataset: Dataset; layouts: LayoutItem[] }) {
    function handleDelete() {
        if (!confirm(`Hapus format "${dataset.name}"?`)) {
            return;
        }

        router.delete(`/kartu-bebas/data/${dataset.id}`);
    }

    return (
        <>
            <Head title={dataset.name} />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Button variant="ghost" size="icon" onClick={() => router.get('/kartu-bebas/data')}>
                            <ArrowLeft className="size-5" />
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-indigo-700 dark:text-indigo-400">{dataset.name}</h1>
                            <p className="text-muted-foreground text-sm">
                                {dataset.fields.length} field · dipakai {dataset.layouts_count} layout
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline" className="gap-2">
                            <Link href={`/kartu-bebas/data/${dataset.id}/edit`}>
                                <Pencil className="size-4" /> Edit
                            </Link>
                        </Button>
                        <Button variant="ghost" onClick={handleDelete}>
                            <Trash2 className="size-4 text-red-500" />
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 text-sm font-semibold">Field</h2>
                        <div className="divide-y divide-border">
                            {dataset.fields.map((f) => (
                                <div key={f.key} className="flex items-center justify-between gap-3 py-2.5">
                                    <div>
                                        <span className="font-medium">{f.label}</span>
                                        {f.required && <span className="ml-1 text-red-500">*</span>}
                                        <span className="text-muted-foreground ml-2 text-xs">
                                            key: <code>{f.key}</code>
                                        </span>
                                    </div>
                                    <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                                        {FIELD_TYPE_LABELS[f.type]}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5">
                        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 className="text-sm font-semibold">Form publik dari format ini</h2>
                                <p className="text-muted-foreground text-xs">
                                    Tiap layout punya link pendaftaran sendiri. Bagikan link ke peserta; isiannya masuk ke layout itu.
                                </p>
                            </div>
                            <Button asChild variant="outline" size="sm" className="gap-1.5">
                                <Link href={`/kartu-bebas/layouts/create?dataset=${dataset.id}`}>
                                    <Plus className="size-4" /> Buat layout dari format ini
                                </Link>
                            </Button>
                        </div>
                        {layouts.length === 0 ? (
                            <p className="text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                                Belum ada layout, jadi belum ada link form publik. Buat layout dulu dari tombol di atas.
                            </p>
                        ) : (
                            <div className="grid gap-3">
                                {layouts.map((l) => (
                                    <div key={l.id} className="grid gap-2 rounded-lg border p-3">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <Link
                                                href={`/kartu-bebas/layouts/${l.id}/edit`}
                                                className="inline-flex items-center gap-1.5 font-medium hover:underline"
                                            >
                                                <LayoutTemplate className="size-4 text-indigo-600" /> {l.name}
                                            </Link>
                                            <div className="flex items-center gap-2 text-xs">
                                                <span className="text-muted-foreground">{l.submissions_count} isian</span>
                                                {l.publik ? (
                                                    <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">Terbuka</Badge>
                                                ) : (
                                                    <Badge variant="outline">Nonaktif</Badge>
                                                )}
                                            </div>
                                        </div>
                                        {l.publik ? (
                                            <LinkFormPublik url={l.public_url} />
                                        ) : (
                                            <p className="text-muted-foreground text-xs">
                                                Layout nonaktif — aktifkan di editor layout supaya link bisa dibuka.
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

KartuBebasDataShow.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Data', href: '/kartu-bebas/data' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
