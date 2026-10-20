import { Check, Copy, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const JEDA_TERSALIN_MS = 2000;

/**
 * Link form pendaftaran publik (`/f/{token}`) + tombol salin dan buka.
 * URL-nya selalu dari server (rute), jadi ikut domain dan token terbaru.
 */
export function LinkFormPublik({
    url,
    className,
}: {
    url: string;
    className?: string;
}) {
    const [tersalin, setTersalin] = useState(false);

    const salin = async () => {
        try {
            await navigator.clipboard.writeText(url);
            setTersalin(true);
            setTimeout(() => setTersalin(false), JEDA_TERSALIN_MS);
        } catch {
            window.prompt('Salin link ini:', url);
        }
    };

    return (
        <div className={cn('flex min-w-0 items-center gap-1.5', className)}>
            <code className="min-w-0 flex-1 truncate rounded-md bg-muted px-2 py-1.5 text-xs">
                {url}
            </code>
            <Button
                type="button"
                variant="outline"
                size="icon"
                className="size-8 shrink-0"
                onClick={salin}
                aria-label="Salin link form publik"
                title="Salin link"
            >
                {tersalin ? (
                    <Check className="size-4 text-emerald-600" />
                ) : (
                    <Copy className="size-4" />
                )}
            </Button>
            <Button
                asChild
                variant="outline"
                size="icon"
                className="size-8 shrink-0"
                title="Buka form"
            >
                <a
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    aria-label="Buka form publik"
                >
                    <ExternalLink className="size-4" />
                </a>
            </Button>
        </div>
    );
}
