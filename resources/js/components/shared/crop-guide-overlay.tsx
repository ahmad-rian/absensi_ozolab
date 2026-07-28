/** Garis panduan framing (pecahan 0..1 tinggi crop) — dihitung server, lihat PhotoCropService::framingGuide(). */
export type CropGuide = {
    headroom: number;
    headTop: number;
    headBottom: number;
    eyeLine: number;
    shoulderLine: number;
    ratio: number;
};

/**
 * Sketsa panduan pas foto yang ditumpuk di atas area cropper.
 *
 * Ukuran kotak crop dikirim lewat `style` oleh pemanggil (react-easy-crop
 * melaporkannya via `onCropSizeChange`). Tanpa itu SVG hanya mengandalkan
 * preserveAspectRatio bawaan, dan sketsanya meleset begitu media hasil
 * `objectFit="contain"` lebih kecil daripada containernya.
 */
export function CropGuideOverlay({
    guide,
    className = '',
    style,
}: {
    guide: CropGuide;
    className?: string;
    style?: React.CSSProperties;
}) {
    // Koordinat viewBox: tinggi 100 unit, jadi setiap pecahan panduan langsung jadi angka.
    const h = 100;
    const w = guide.ratio * 100;
    const headRy = ((guide.headBottom - guide.headTop) / 2) * 100;
    const headCy = guide.headTop * 100 + headRy;
    // Lebar kepala manusia kira-kira 0,72x tingginya.
    const headRx = headRy * 0.72;

    // `guide.eyeLine` sengaja tidak digambar — garisnya jatuh tepat di wajah dan
    // lebih mengganggu daripada menolong. Nilainya tetap dipakai server untuk
    // menghitung framing.
    const lines: { y: number; label: string; accent?: boolean }[] = [
        { y: guide.headroom * 100, label: 'Ruang di atas kepala' },
        { y: guide.shoulderLine * 100, label: 'Bahu terlihat' },
    ];

    return (
        <svg
            viewBox={`0 0 ${w} ${h}`}
            preserveAspectRatio="none"
            style={style}
            className={`pointer-events-none absolute ${style ? '' : 'inset-0 size-full'} ${className}`}
            aria-hidden="true"
            focusable="false"
        >
            {/* Pita ruang kepala — dibuat sangat samar supaya wajah tetap terlihat jelas. */}
            <rect
                x={0}
                y={0}
                width={w}
                height={guide.headroom * 100}
                fill="rgba(255,255,255,0.10)"
            />

            {/* Siluet kepala sebagai target penempatan wajah. */}
            <ellipse
                cx={w / 2}
                cy={headCy}
                rx={headRx}
                ry={headRy}
                fill="rgba(255,255,255,0.06)"
                stroke="rgba(255,255,255,0.55)"
                strokeWidth={0.35}
                strokeDasharray="2 1.6"
            />

            {/* Sumbu tengah untuk meluruskan wajah. */}
            <line
                x1={w / 2}
                y1={0}
                x2={w / 2}
                y2={h}
                stroke="rgba(255,255,255,0.35)"
                strokeWidth={0.25}
                strokeDasharray="1.6 2"
            />

            {lines.map((line) => (
                <g key={line.label}>
                    <line
                        x1={0}
                        y1={line.y}
                        x2={w}
                        y2={line.y}
                        stroke={
                            line.accent
                                ? 'rgba(134,239,172,0.95)'
                                : 'rgba(255,255,255,0.7)'
                        }
                        strokeWidth={line.accent ? 0.45 : 0.3}
                        strokeDasharray={line.accent ? undefined : '3 2'}
                    />
                    {/* Kontur gelap di belakang teks supaya terbaca di foto terang maupun gelap. */}
                    <text
                        x={2.5}
                        y={line.y - 1.6}
                        fontSize={3.6}
                        fill={
                            line.accent
                                ? 'rgb(187,247,208)'
                                : 'rgba(255,255,255,0.95)'
                        }
                        stroke="rgba(0,0,0,0.6)"
                        strokeWidth={1}
                        paintOrder="stroke"
                        strokeLinejoin="round"
                    >
                        {line.label}
                    </text>
                </g>
            ))}
        </svg>
    );
}
