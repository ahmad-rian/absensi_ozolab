import { ArrowLeft, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * Progres dan navigasi antar langkah.
 *
 * Angkanya memakai jumlah topik yang TERLIHAT pembaca, bukan jumlah seluruh
 * topik — supaya guru yang hanya melihat lima modul tidak dibuat merasa ada
 * belasan langkah yang tidak pernah muncul.
 */
export function GuideNav({
    step,
    total,
    onPrev,
    onNext,
}: {
    step: number;
    total: number;
    onPrev: () => void;
    onNext: () => void;
}) {
    const percent = total > 0 ? Math.round((step / total) * 100) : 0;

    return (
        <div className="space-y-4 border-t pt-5">
            <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">
                    Langkah {step} dari {total}
                </span>
                <span className="font-medium tabular-nums">{percent}%</span>
            </div>

            <div
                className="bg-muted h-1.5 w-full overflow-hidden rounded-full"
                role="progressbar"
                aria-valuenow={percent}
                aria-valuemin={0}
                aria-valuemax={100}
            >
                <div
                    className="bg-primary h-full rounded-full transition-[width] duration-300"
                    style={{ width: `${percent}%` }}
                />
            </div>

            <div className="flex items-center justify-between gap-3">
                <Button variant="ghost" onClick={onPrev} disabled={step <= 1}>
                    <ArrowLeft className="mr-1.5 size-4" />
                    Sebelumnya
                </Button>
                <Button onClick={onNext} disabled={step >= total}>
                    Berikutnya
                    <ArrowRight className="ml-1.5 size-4" />
                </Button>
            </div>
        </div>
    );
}
