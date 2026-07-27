import { router, usePage } from '@inertiajs/react';
import { UserRoundCog } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Impersonation = { active: boolean; original_name: string | null };

export function ImpersonationBanner() {
    const { impersonation, auth } = usePage().props as unknown as {
        impersonation?: Impersonation;
        auth: { user: { name: string } | null };
    };

    if (!impersonation?.active) return null;

    return (
        <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950">
            <UserRoundCog className="size-4 shrink-0" />
            <span>
                Anda sedang menyamar sebagai <strong>{auth.user?.name}</strong>
                {impersonation.original_name && <> — akun asli: {impersonation.original_name}</>}
            </span>
            <Button
                size="sm"
                variant="outline"
                className="h-7 border-amber-950/30 bg-amber-50 text-amber-950 hover:bg-amber-100"
                onClick={() => router.post('/admin/stop-impersonate')}
            >
                Kembali ke akun saya
            </Button>
        </div>
    );
}
