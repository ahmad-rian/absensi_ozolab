import { Head, Link } from '@inertiajs/react';
import { ArrowRight, LayoutTemplate, Plus, Wand2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';

type LayoutItem = {
    id: string;
    name: string;
    orientation: string;
    dataset_name: string;
    fields_count: number;
};

type Props = { layouts: LayoutItem[] };

export default function KartuBebasGenerateIndex({ layouts }: Props) {
    return (
        <>
            <Head title="Generate Kartu" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">Generate Kartu</h1>
                    <p className="text-muted-foreground text-sm">Pilih layout, isi datanya, dan buat kartu langsung dari sini.</p>
                </div>

                {layouts.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                            <LayoutTemplate className="mb-4 size-12 text-emerald-500/70" />
                            <p className="text-muted-foreground mb-4 text-sm">Belum ada layout kartu. Buat layout terlebih dahulu.</p>
                            <Button asChild className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700">
                                <Link href="/kartu-bebas/layouts/create">
                                    <Plus className="size-4" /> Buat Layout
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {layouts.map((layout) => (
                            <Link key={layout.id} href={`/kartu-bebas/generate/${layout.id}`} className="group">
                                <Card className="h-full transition group-hover:border-emerald-300 group-hover:shadow-md dark:group-hover:border-emerald-800">
                                    <CardContent className="flex h-full flex-col p-5">
                                        <div className="mb-3 flex items-start justify-between gap-2">
                                            <div className="flex items-center gap-2">
                                                <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-950/50">
                                                    <Wand2 className="size-4.5" />
                                                </div>
                                                <h3 className="font-semibold">{layout.name}</h3>
                                            </div>
                                            <Badge variant="secondary" className="capitalize">
                                                {layout.orientation}
                                            </Badge>
                                        </div>
                                        <div className="text-muted-foreground mb-4 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                            <span>{layout.dataset_name}</span>
                                            <span>·</span>
                                            <span>{layout.fields_count} field</span>
                                        </div>
                                        <div className="mt-auto flex items-center gap-1 text-sm font-medium text-emerald-700 dark:text-emerald-400">
                                            Isi &amp; Generate
                                            <ArrowRight className="size-4 transition group-hover:translate-x-0.5" />
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

KartuBebasGenerateIndex.layout = (page: React.ReactNode) => (
    <KartuBebasLayout
        breadcrumbs={[
            { title: 'Dashboard', href: '/kartu-bebas' },
            { title: 'Generate', href: '/kartu-bebas/generate' },
        ]}
    >
        {page}
    </KartuBebasLayout>
);
