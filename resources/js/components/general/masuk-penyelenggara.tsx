import { router } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { switchMethod } from '@/routes/penyelenggara';

/**
 * Pilih penyelenggara sebagai konteks lalu buka area penyelenggaranya.
 * `kembali` (opsional) membawa langsung ke halaman tertentu di area itu;
 * server hanya menerima jalur `/penyelenggara/...`.
 */
export function MasukPenyelenggara({
    penyelenggaraId,
    kembali,
    label = 'Masuk',
    size = 'sm',
    variant = 'outline',
}: {
    penyelenggaraId: string;
    kembali?: string;
    label?: string;
    size?: 'sm' | 'default';
    variant?: 'outline' | 'default';
}) {
    return (
        <Button
            size={size}
            variant={variant}
            onClick={() =>
                router.post(switchMethod.url(), {
                    penyelenggara_id: penyelenggaraId,
                    ...(kembali ? { kembali } : {}),
                })
            }
        >
            <LogIn className="size-4" /> {label}
        </Button>
    );
}
