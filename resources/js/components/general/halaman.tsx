import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

/** Navigasi halaman sederhana untuk paginator Laravel. */
export function Halaman({
    prev,
    next,
    total,
    satuan,
}: {
    prev: string | null;
    next: string | null;
    total: number;
    satuan: string;
}) {
    return (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
                {new Intl.NumberFormat('id-ID').format(total)} {satuan}
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!prev}
                    onClick={() =>
                        prev && router.get(prev, {}, { preserveScroll: true })
                    }
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!next}
                    onClick={() =>
                        next && router.get(next, {}, { preserveScroll: true })
                    }
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    );
}
