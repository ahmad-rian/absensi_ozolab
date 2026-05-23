import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, LayoutTemplate, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';

type Layout = {
    id: string;
    name: string;
    type: string;
    is_default: boolean;
    is_active: boolean;
    layout_config: Record<string, unknown>;
};

type Props = { layouts: Layout[] };

const typeLabels: Record<string, string> = {
    osis: 'Kartu OSIS',
    perpustakaan: 'Kartu Perpustakaan',
    identitas: 'Kartu Identitas',
};

export default function CardLayoutsIndex({ layouts }: Props) {
    function handleDelete(layout: Layout) {
        if (!confirm(`Hapus layout "${layout.name}"?`)) return;
        router.delete(`/admin/card-layouts/${layout.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Layout Kartu" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Layout Kartu Siswa</h1>
                        <p className="text-muted-foreground text-sm">Buat dan kelola desain kartu siswa (OSIS, perpustakaan, dll).</p>
                    </div>
                    <Button asChild className="gap-2">
                        <Link href="/admin/card-layouts/create">
                            <Plus className="size-4" /> Buat Layout
                        </Link>
                    </Button>
                </div>

                {layouts.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <LayoutTemplate className="text-muted-foreground mb-4 size-12" />
                            <p className="text-muted-foreground text-sm">Belum ada layout kartu. Buat layout pertama.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {layouts.map((layout) => (
                            <Card key={layout.id} className={!layout.is_active ? 'opacity-50' : ''}>
                                <CardContent className="p-5">
                                    <div className="mb-3 flex items-start justify-between">
                                        <div className="flex items-center gap-2">
                                            <CreditCard className="text-primary size-5" />
                                            <h3 className="font-semibold">{layout.name}</h3>
                                        </div>
                                        <div className="flex gap-1">
                                            {layout.is_default && <Badge className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Default</Badge>}
                                            {!layout.is_active && <Badge variant="secondary">Nonaktif</Badge>}
                                        </div>
                                    </div>
                                    <p className="text-muted-foreground mb-4 text-sm">{typeLabels[layout.type] ?? layout.type}</p>
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" asChild className="flex-1">
                                            <Link href={`/admin/card-layouts/${layout.id}/edit`}>
                                                <Pencil className="mr-1 size-3" /> Edit
                                            </Link>
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => handleDelete(layout)}>
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

CardLayoutsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Layout Kartu', href: '/admin/card-layouts' },
    ],
};
