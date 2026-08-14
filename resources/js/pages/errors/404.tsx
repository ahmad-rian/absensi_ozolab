import { Head, usePage } from '@inertiajs/react';
import { ArrowLeft, FileQuestion } from 'lucide-react';

export default function Error404() {
    const { currentSchool } = usePage().props as unknown as {
        currentSchool: { name: string } | null;
    };

    return (
        <>
            <Head title="404 - Halaman Tidak Ditemukan" />
            <div className="bg-background text-foreground flex min-h-dvh flex-col items-center justify-center gap-6 px-4">
                <div className="text-muted-foreground/30">
                    <FileQuestion className="size-20" strokeWidth={1} />
                </div>
                <div className="max-w-md text-center">
                    <h1 className="text-6xl font-extrabold tracking-tight">404</h1>
                    <p className="text-muted-foreground mt-3 text-lg">Halaman yang Anda cari tidak ditemukan.</p>
                    {/* Penyebab paling sering: tautan lama di tab yang tertinggal,
                        sementara sekolah aktif sudah berpindah di tab lain. Sesi
                        dipakai bersama semua tab, jadi menyebut sekolah aktif di
                        sini menghemat banyak tebak-tebakan. */}
                    {currentSchool && (
                        <p className="text-muted-foreground mt-4 text-sm">
                            Anda sedang membuka <span className="text-foreground font-semibold">{currentSchool.name}</span>. Bila data yang
                            dicari milik sekolah lain, ganti sekolahnya dulu lewat pemilih di sidebar.
                        </p>
                    )}
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
