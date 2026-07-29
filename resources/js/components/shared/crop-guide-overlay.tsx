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
 * Pergeseran garis ke atas, dalam unit viewBox (= persen tinggi kotak crop).
 *
 * Murni tampilan. `PhotoCropService::HEADROOM_FRACTION` tidak ikut bergeser, jadi
 * hasil crop otomatis tetap sama — jangan disamakan dengan konstanta framing itu.
 */
const GUIDE_LIFT = 3;

/**
 * Sketsa panduan pas foto yang ditumpuk di atas area cropper.
 *
 * Sengaja tinggal satu garis: siluet kepala, sumbu tengah, pita ruang kepala, dan
 * garis bahu terbukti lebih mengganggu daripada menolong saat dipakai memotret.
 * Field panduan lain tetap dikirim server karena dipakai menghitung crop.
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

    // Clamp menjaga garis tetap di dalam kotak walau nilai panduan berubah kecil.
    const headroomY = Math.max(GUIDE_LIFT, guide.headroom * 100 - GUIDE_LIFT);

    return (
        <svg
            viewBox={`0 0 ${w} ${h}`}
            preserveAspectRatio="none"
            style={style}
            className={`pointer-events-none absolute ${style ? '' : 'inset-0 size-full'} ${className}`}
            aria-hidden="true"
            focusable="false"
        >
            <line
                x1={0}
                y1={headroomY}
                x2={w}
                y2={headroomY}
                stroke="rgba(255,255,255,0.7)"
                strokeWidth={0.3}
                strokeDasharray="3 2"
            />
            {/* Kontur gelap di belakang teks supaya terbaca di foto terang maupun gelap. */}
            <text
                x={2.5}
                y={headroomY - 1.6}
                fontSize={3.6}
                fill="rgba(255,255,255,0.95)"
                stroke="rgba(0,0,0,0.6)"
                strokeWidth={1}
                paintOrder="stroke"
                strokeLinejoin="round"
            >
                Ruang di atas kepala
            </text>
        </svg>
    );
}
