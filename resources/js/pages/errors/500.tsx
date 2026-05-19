import { Head } from '@inertiajs/react';
import { ArrowLeft, ServerCrash } from 'lucide-react';

export default function Error500() {
    return (
        <>
            <Head title="500 - Kesalahan Server" />
            <div className="bg-background text-foreground flex min-h-dvh flex-col items-center justify-center gap-6 px-4">
                <div className="text-muted-foreground/30">
                    <ServerCrash className="size-20" strokeWidth={1} />
                </div>
                <div className="text-center">
                    <h1 className="text-6xl font-extrabold tracking-tight">500</h1>
                    <p className="text-muted-foreground mt-3 text-lg">Terjadi kesalahan pada server. Silakan coba lagi nanti.</p>
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
