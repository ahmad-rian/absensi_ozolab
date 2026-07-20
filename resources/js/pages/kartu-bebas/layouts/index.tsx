import { Head, Link, router } from '@inertiajs/react';
import { Check, Copy, FileText, LayoutTemplate, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';

type CardFormItem = {
    id: string;
    name: string;
    token: string;
    orientation: string;
    is_active: boolean;
    dataset_name: string | null;
    fields_count: number;
    submissions_count: number;
    public_url: string;
    created_at: string | null;
};

type Props = { forms: CardFormItem[] };

export default function KartuBebasLayoutsIndex({ forms }: Props) {
    const [copiedId, setCopiedId] = useState<string | null>(null);

    function handleDelete(form: CardFormItem) {
        if (!confirm(`Hapus layout "${form.name}"? Semua hasil isian juga akan dihapus.`)) return;
        router.delete(`/kartu-bebas/layouts/${form.id}`, { preserveScroll: true });
    }

    async function copyLink(form: CardFormItem) {
        try {
            await navigator.clipboard.writeText(form.public_url);
            setCopiedId(form.id);
            setTimeout(() => setCopiedId((c) => (c === form.id ? null : c)), 1500);
        } catch {
            window.prompt('Salin tautan publik:', form.public_url);
        }
    }

    return (
        <>
            <Head title="Layout Kartu" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">Layout Kartu</h1>
                        <p className="text-muted-foreground text-sm">Buat template kartu dengan field isian dinamis dan desain bebas.</p>
                    </div>
                    <Button asChild className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                        <Link href="/kartu-bebas/layouts/create">
                            <Plus className="size-4" /> Buat Layout
                        </Link>
                    </Button>
                </div>

                {forms.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <LayoutTemplate className="mb-4 size-12 text-emerald-500/70" />
                            <p className="text-muted-foreground text-sm">Belum ada layout kartu. Buat layout pertama Anda.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {forms.map((form) => (
                            <Card key={form.id} className={!form.is_active ? 'opacity-60' : ''}>
                                <CardContent className="p-5">
                                    <div className="mb-3 flex items-start justify-between">
                                        <div className="flex items-center gap-2">
                                            <FileText className="size-5 text-emerald-600" />
                                            <h3 className="font-semibold">{form.name}</h3>
                                        </div>
                                        <div className="flex gap-1">
                                            {!form.is_active && <Badge variant="secondary">Nonaktif</Badge>}
                                        </div>
                                    </div>
                                    <p className="text-muted-foreground mb-1 text-xs">Format: {form.dataset_name ?? '—'}</p>
                                    <div className="text-muted-foreground mb-4 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                        <span>{form.fields_count} field</span>
                                        <span>·</span>
                                        <span className="capitalize">{form.orientation}</span>
                                        <span>·</span>
                                        <span>{form.submissions_count} isian</span>
                                    </div>

                                    <div className="mb-3 flex items-center gap-2">
                                        <code className="text-muted-foreground flex-1 truncate rounded-md bg-emerald-50 px-2 py-1.5 text-[11px] dark:bg-emerald-950/40">
                                            {form.public_url}
                                        </code>
                                        <Button
                                            variant="outline"
                                            size="icon"
                                            className="size-8 shrink-0 border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300"
                                            onClick={() => copyLink(form)}
                                            title="Salin tautan publik"
                                        >
                                            {copiedId === form.id ? <Check className="size-4" /> : <Copy className="size-4" />}
                                        </Button>
                                    </div>

                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" asChild className="flex-1">
                                            <Link href={`/kartu-bebas/layouts/${form.id}/edit`}>
                                                <Pencil className="mr-1 size-3" /> Edit
                                            </Link>
                                        </Button>
                                        <Button variant="ghost" size="sm" onClick={() => handleDelete(form)}>
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

KartuBebasLayoutsIndex.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Layout Kartu', href: '/kartu-bebas/layouts' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
