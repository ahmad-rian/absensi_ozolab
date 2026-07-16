import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Database, FileText, Frame, History, LayoutTemplate, Sparkles } from 'lucide-react';
import { StatCard } from '@/components/dashboard/stat-card';
import { Card, CardContent } from '@/components/ui/card';
import KartuBebasLayout from '@/layouts/kartu-bebas-layout';

type Stats = {
    layouts: number;
    records: number;
    generated: number;
};

type LatestItem = {
    id: string;
    form_name: string | null;
    status: string | null;
    has_file: boolean;
    created_at: string | null;
};

type Props = {
    stats: Stats;
    latest: LatestItem[];
};

const today = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
}).format(new Date());

const shortcuts = [
    { title: 'Data', description: 'Kelola isian kartu', href: '/kartu-bebas/data', icon: Database },
    { title: 'Frame & Bingkai', description: 'Bingkai kartu bebas', href: '/kartu-bebas/frames', icon: Frame },
    { title: 'Layout Kartu', description: 'Desain template kartu', href: '/kartu-bebas/layouts', icon: LayoutTemplate },
    { title: 'Riwayat Kartu', description: 'Kartu yang dihasilkan', href: '/kartu-bebas/riwayat', icon: History },
];

export default function KartuBebasDashboard({ stats, latest }: Props) {
    return (
        <>
            <Head title="Dashboard Kartu Bebas" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6">
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">Kartu Bebas / Haji</p>
                        <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
                    </div>
                    <p className="text-muted-foreground text-sm capitalize">{today}</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <StatCard title="Layout Kartu" value={stats.layouts} icon={<LayoutTemplate className="size-5" />} color="green" />
                    <StatCard title="Data Isian" value={stats.records} icon={<FileText className="size-5" />} color="blue" />
                    <StatCard title="Kartu Dihasilkan" value={stats.generated} icon={<CheckCircle2 className="size-5" />} color="green" />
                </div>

                <div>
                    <h2 className="mb-3 text-sm font-semibold text-emerald-700 dark:text-emerald-400">Pintasan</h2>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        {shortcuts.map(({ title, description, href, icon: Icon }) => (
                            <Link
                                key={href}
                                href={href}
                                className="group bg-card border-border hover:border-emerald-400 flex items-start gap-3 rounded-2xl border p-5 transition"
                            >
                                <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/20 dark:text-emerald-400">
                                    <Icon className="size-5" />
                                </div>
                                <div className="min-w-0">
                                    <p className="font-semibold group-hover:text-emerald-700 dark:group-hover:text-emerald-300">{title}</p>
                                    <p className="text-muted-foreground text-xs">{description}</p>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                <Card>
                    <CardContent className="p-5">
                        <div className="mb-4 flex items-center gap-2">
                            <Sparkles className="size-4 text-emerald-600 dark:text-emerald-400" />
                            <h2 className="text-sm font-semibold">Isian Terbaru</h2>
                        </div>
                        {latest.length === 0 ? (
                            <p className="text-muted-foreground py-6 text-center text-sm">Belum ada isian kartu.</p>
                        ) : (
                            <ul className="divide-border divide-y">
                                {latest.map((item) => (
                                    <li key={item.id} className="flex items-center justify-between gap-3 py-2.5 text-sm">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{item.form_name ?? 'Tanpa layout'}</p>
                                            <p className="text-muted-foreground text-xs">{item.created_at ?? '-'}</p>
                                        </div>
                                        <span
                                            className={
                                                item.has_file || item.status === 'completed'
                                                    ? 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                    : 'text-muted-foreground bg-muted rounded-full px-2 py-0.5 text-xs font-medium'
                                            }
                                        >
                                            {item.has_file || item.status === 'completed' ? 'Selesai' : (item.status ?? 'Menunggu')}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

KartuBebasDashboard.layout = (page: React.ReactNode) => (
    <KartuBebasLayout breadcrumbs={[{ title: 'Dashboard', href: '/kartu-bebas' }]}>{page}</KartuBebasLayout>
);
