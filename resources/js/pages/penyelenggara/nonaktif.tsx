import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

type Props = { name: string | null };

export default function PenyelenggaraNonaktif({ name }: Props) {
    return (
        <>
            <Head title="Akun nonaktif" />
            <div className="flex min-h-svh items-center justify-center bg-muted/40 p-4">
                <div className="w-full max-w-md rounded-xl border bg-background p-6 text-center shadow-sm">
                    <h1 className="text-xl font-semibold">
                        Akun sedang nonaktif
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Akses {name ?? 'penyelenggara ini'} sedang
                        dinonaktifkan. Hubungi tim Tyas untuk mengaktifkannya
                        kembali.
                    </p>
                    <Button asChild variant="outline" className="mt-6">
                        <Link href={logout()} method="post" as="button">
                            Keluar
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
