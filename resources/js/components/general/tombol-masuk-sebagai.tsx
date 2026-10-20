import { router } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { masukSebagai } from '@/routes/general/pengguna';

/**
 * Masuk sebagai akun lain (khusus super admin). Banner kuning muncul selama
 * menyamar; "Kembali ke akun saya" membawa pulang ke halaman Pengguna.
 */
export function TombolMasukSebagai({
    userId,
    nama,
}: {
    userId: string;
    nama: string;
}) {
    const masuk = () => {
        if (
            window.confirm(
                `Masuk sebagai ${nama}? Anda melihat aplikasi persis seperti dia sampai menekan "Kembali ke akun saya". Semua tindakan tercatat.`,
            )
        ) {
            router.post(masukSebagai.url(userId));
        }
    };

    return (
        <Button size="sm" variant="outline" onClick={masuk}>
            <LogIn className="size-4" /> Masuk sebagai
        </Button>
    );
}
