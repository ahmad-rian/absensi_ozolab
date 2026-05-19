import { Head } from '@inertiajs/react';
import { ArrowLeft, ShieldX } from 'lucide-react';

export default function Error403() {
    return (
        <>
            <Head title="403 - Akses Ditolak" />
            <div className="bg-background text-foreground flex min-h-dvh flex-col items-center justify-center gap-6 px-4">
                <div className="text-muted-foreground/30">
                    <ShieldX className="size-20" strokeWidth={1} />
                </div>
                <div className="text-center">
                    <h1 className="text-6xl font-extrabold tracking-tight">403</h1>
                    <p className="text-muted-foreground mt-3 text-lg">Anda tidak memiliki akses ke halaman ini.</p>
                </div>
                <a
                    href="/admin/dashboard"
                    className="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold transition"
                >
                    <ArrowLeft className="size-4" />
                    Kembali ke Dashboard
                </a>
            </div>
        </>
    );
}
