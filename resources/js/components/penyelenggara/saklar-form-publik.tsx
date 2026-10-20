import { router } from '@inertiajs/react';
import { LinkFormPublik } from '@/components/shared/link-form-publik';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formPublik } from '@/routes/penyelenggara/kartu';

export type FormPublikProgram = {
    program_id: string;
    program: string;
    arsip: boolean;
    aktif: boolean;
    terbuka: boolean;
    url: string;
};

/**
 * Saklar form pendaftaran publik satu program + link-nya. Pendaftar lewat
 * link tetap tunduk kuota peserta penyelenggara.
 */
export function SaklarFormPublik({ item }: { item: FormPublikProgram }) {
    const ubah = () => {
        const pesan = item.aktif
            ? `Matikan form publik "${item.program}"? Link yang sudah dibagikan tidak bisa dibuka lagi.`
            : `Nyalakan form publik "${item.program}"? Siapa pun yang memegang link bisa mendaftar sebagai peserta.`;

        if (window.confirm(pesan)) {
            router.post(
                formPublik.url(item.program_id),
                { aktif: !item.aktif },
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="grid gap-2 rounded-lg border p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <span className="truncate font-medium">{item.program}</span>
                    {item.arsip ? (
                        <Badge variant="secondary">Arsip</Badge>
                    ) : item.terbuka ? (
                        <Badge className="bg-emerald-600 text-white hover:bg-emerald-600">
                            Terbuka
                        </Badge>
                    ) : (
                        <Badge variant="outline">Tertutup</Badge>
                    )}
                </div>
                {!item.arsip && (
                    <Button
                        type="button"
                        size="sm"
                        variant={item.aktif ? 'outline' : 'default'}
                        onClick={ubah}
                    >
                        {item.aktif
                            ? 'Matikan form publik'
                            : 'Nyalakan form publik'}
                    </Button>
                )}
            </div>
            {item.terbuka ? (
                <LinkFormPublik url={item.url} />
            ) : (
                <p className="text-xs text-muted-foreground">
                    {item.arsip
                        ? 'Program sudah diarsip; pendaftaran publik ditutup.'
                        : 'Nyalakan untuk mendapatkan link pendaftaran yang bisa dibagikan.'}
                </p>
            )}
        </div>
    );
}
