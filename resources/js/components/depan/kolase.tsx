import type { CSSProperties } from 'react';

/** Bilangan acak yang selalu sama untuk benih yang sama — sobekan tidak berubah tiap render. */
function acak(benih: number): () => number {
    let s = benih;

    return () => {
        s = (s + 0x6d2b79f5) | 0;
        let t = Math.imul(s ^ (s >>> 15), 1 | s);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/**
 * Tepi atas bergerigi seperti kertas yang disobek tangan, sebagai polygon
 * clip-path. `kasar` = seberapa dalam gerigi (persen tinggi elemen).
 */
function sobekan(benih: number, kasar: number): string {
    const r = acak(benih);
    const titik: string[] = ['0% 100%'];
    let x = 0;

    while (x < 100) {
        titik.push(`${x.toFixed(1)}% ${(r() * kasar).toFixed(1)}%`);
        x += 1.2 + r() * 3.2;
    }

    titik.push(`100% ${(r() * kasar).toFixed(1)}%`, '100% 100%');

    return `polygon(${titik.join(',')})`;
}

type KertasProps = {
    benih: number;
    className?: string;
    style?: CSSProperties;
    kasar?: number;
};

/** Selembar kertas sobek. Warna dan tekstur lewat className (bg-*, .halftone, .bergaris). */
export function KertasSobek({
    benih,
    className = '',
    style,
    kasar = 6,
}: KertasProps) {
    return (
        <div
            aria-hidden="true"
            className={`absolute ${className}`}
            style={{ clipPath: sobekan(benih, kasar), ...style }}
        />
    );
}

/** Awan dari lingkaran bertumpuk, sedikit kabur, dengan bintik halftone. */
export function Awan({ className = '' }: { className?: string }) {
    return (
        <div aria-hidden="true" className={`awan absolute ${className}`}>
            <svg viewBox="0 0 220 90" className="h-full w-full">
                <defs>
                    <filter id="awan-lembut">
                        <feGaussianBlur stdDeviation="2.4" />
                    </filter>
                    <pattern
                        id="awan-bintik"
                        width="5"
                        height="5"
                        patternUnits="userSpaceOnUse"
                    >
                        <circle cx="2.5" cy="2.5" r="0.9" fill="#b9c9da" />
                    </pattern>
                </defs>
                <g filter="url(#awan-lembut)" fill="#fff" opacity="0.92">
                    <circle cx="60" cy="58" r="28" />
                    <circle cx="98" cy="40" r="36" />
                    <circle cx="142" cy="52" r="30" />
                    <circle cx="176" cy="64" r="22" />
                    <rect x="40" y="58" width="150" height="28" rx="14" />
                </g>
                <g opacity="0.55">
                    <circle cx="98" cy="52" r="26" fill="url(#awan-bintik)" />
                    <circle cx="140" cy="62" r="20" fill="url(#awan-bintik)" />
                </g>
            </svg>
        </div>
    );
}
