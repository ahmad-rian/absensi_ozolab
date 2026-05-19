import { Head } from '@inertiajs/react';
import { Construction } from 'lucide-react';
import { dashboard } from '@/routes';

interface ComingSoonPageProps {
    title: string;
    description?: string;
}

export function ComingSoonPage({ title, description }: ComingSoonPageProps) {
    return (
        <>
            <Head title={title} />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-8">
                <div className="bg-muted flex size-16 items-center justify-center rounded-2xl">
                    <Construction className="text-muted-foreground size-8" />
                </div>
                <div className="text-center">
                    <h2 className="text-xl font-bold">{title}</h2>
                    <p className="text-muted-foreground mt-1 text-sm">
                        {description ?? 'Halaman ini sedang dalam pengembangan.'}
                    </p>
                </div>
            </div>
        </>
    );
}

ComingSoonPage.makeBreadcrumbs = (title: string) => [
    { title: 'Dashboard', href: dashboard() },
    { title, href: '#' },
];
