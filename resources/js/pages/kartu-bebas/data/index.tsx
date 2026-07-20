import { Head, Link, router } from '@inertiajs/react';
import { Database, Pencil, Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import { type FormField } from './field-builder';

type Dataset = {
    id: string;
    name: string;
    fields: FormField[];
    layouts_count: number;
    created_at: string | null;
};

export default function KartuBebasDataIndex({ datasets }: { datasets: Dataset[] }) {
    function handleDelete(dataset: Dataset) {
        if (!confirm(`Hapus format "${dataset.name}"?`)) {
            return;
        }
        router.delete(`/kartu-bebas/data/${dataset.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Format Data" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">Data / Format Data</h1>
                        <p className="text-muted-foreground text-sm">
                            Buat format field kartu (bisa macam-macam). Layout nanti tinggal pilih format ini.
                        </p>
                    </div>
                    <Button asChild className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                        <Link href="/kartu-bebas/data/create">
                            <Plus className="size-4" /> Buat Format
                        </Link>
                    </Button>
                </div>

                {datasets.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <Database className="mb-4 size-12 text-emerald-500/70" />
                            <p className="text-muted-foreground text-sm">Belum ada format data. Buat format dulu.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {datasets.map((dataset) => (
                            <Card key={dataset.id} className="transition hover:border-emerald-300">
                                <CardContent className="p-5">
                                    <Link href={`/kartu-bebas/data/${dataset.id}`} className="block">
                                        <div className="mb-3 flex items-start gap-2">
                                            <Database className="mt-0.5 size-5 shrink-0 text-emerald-600" />
                                            <h3 className="font-semibold break-words">{dataset.name}</h3>
                                        </div>
                                        <div className="text-muted-foreground mb-4 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                            <span>{dataset.fields.length} field</span>
                                            <span>·</span>
                                            <span>{dataset.layouts_count} layout pakai</span>
                                        </div>
                                    </Link>
                                    <div className="flex gap-2">
                                        <Button asChild variant="outline" size="sm" className="flex-1">
                                            <Link href={`/kartu-bebas/data/${dataset.id}/edit`}>
                                                <Pencil className="mr-1 size-3" /> Edit
                                            </Link>
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => handleDelete(dataset)}>
                                            <Trash2 className="size-4 text-red-500" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

KartuBebasDataIndex.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Data', href: '/kartu-bebas/data' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
