import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, LayoutTemplate, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';
import { FIELD_TYPE_LABELS, type FormField } from './field-builder';

type Dataset = {
    id: string;
    name: string;
    fields: FormField[];
    layouts_count: number;
    created_at: string | null;
};

type LayoutItem = { id: string; name: string; orientation: string };

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
                            <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">{dataset.name}</h1>
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
                                    <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        {FIELD_TYPE_LABELS[f.type]}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-5">
                        <h2 className="mb-3 text-sm font-semibold">Layout yang memakai format ini</h2>
                        {layouts.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Belum ada layout. Buat di{' '}
                                <Link href="/kartu-bebas/layouts/create" className="text-emerald-600 underline">
                                    Layout Kartu
                                </Link>
                                .
                            </p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {layouts.map((l) => (
                                    <Link
                                        key={l.id}
                                        href={`/kartu-bebas/layouts/${l.id}/edit`}
                                        className="inline-flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-muted"
                                    >
                                        <LayoutTemplate className="size-4 text-emerald-600" /> {l.name}
                                    </Link>
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
