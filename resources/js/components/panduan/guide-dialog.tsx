import { Link } from '@inertiajs/react';
import { BookOpen, CircleHelp } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { panduan } from '@/routes/admin';

type Props = {
    title: string;
    description?: string;
    /** Id topik di guide-registry - dipakai tautan "Buka panduan lengkap". */
    topik: string;
    children: ReactNode;
};

/**
 * Tombol "?" di halaman yang membuka cara pakai halaman itu dalam modal.
 *
 * Isinya komponen topik yang sama dengan halaman Panduan, jadi teksnya hanya
 * ditulis di satu tempat dan tidak bisa berbeda antara modal dan Panduan.
 */
export function GuideDialog({ title, description, topik, children }: Props) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-8 shrink-0 rounded-full"
                    aria-label={`Cara pakai: ${title}`}
                    title="Cara pakai"
                >
                    <CircleHelp className="size-4" />
                </Button>
            </DialogTrigger>
            <DialogContent className="flex max-h-[85vh] flex-col gap-0 p-0 sm:max-w-2xl">
                <DialogHeader className="border-b p-6 pr-12 text-left">
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                <div className="overflow-y-auto p-6">{children}</div>
                <DialogFooter className="border-t p-4">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={panduan({ query: { topik } })}>
                            <BookOpen className="size-4" /> Buka panduan lengkap
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
