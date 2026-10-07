import type { CSSProperties, ElementType, ReactNode } from 'react';
import { useEffect, useRef } from 'react';

/**
 * Satu IntersectionObserver untuk seluruh halaman, bukan satu per elemen.
 * Elemen ditandai `data-tampil` sekali saat masuk layar lalu dilepas —
 * animasinya tidak diputar ulang tiap kali digulir bolak-balik.
 */
let pengamat: IntersectionObserver | null = null;

function amati(el: Element): () => void {
    if (typeof IntersectionObserver === 'undefined') {
        el.setAttribute('data-tampil', '');

        return () => {};
    }

    pengamat ??= new IntersectionObserver(
        (entri) => {
            for (const e of entri) {
                if (e.isIntersecting) {
                    e.target.setAttribute('data-tampil', '');
                    pengamat?.unobserve(e.target);
                }
            }
        },
        { rootMargin: '0px 0px -12% 0px', threshold: 0.12 },
    );
    pengamat.observe(el);

    return () => pengamat?.unobserve(el);
}

type MunculProps = {
    as?: ElementType;
    /** `cetak` menyingkap dari atas seperti kertas keluar printer. */
    gaya?: 'foto' | 'cetak';
    tunda?: number;
    className?: string;
    children: ReactNode;
} & Record<string, unknown>;

export function Muncul({
    as: Tag = 'div',
    gaya = 'foto',
    tunda = 0,
    className = '',
    children,
    ...sisa
}: MunculProps) {
    const ref = useRef<HTMLElement>(null);

    useEffect(() => (ref.current ? amati(ref.current) : undefined), []);

    return (
        <Tag
            ref={ref}
            className={`${gaya === 'cetak' ? 'muncul-cetak' : 'muncul'} ${className}`}
            style={{ '--tunda': `${tunda}ms` } as CSSProperties}
            {...sisa}
        >
            {children}
        </Tag>
    );
}
