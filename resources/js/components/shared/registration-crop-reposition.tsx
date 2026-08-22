import { CheckCircle2, Crop, Loader2, Move, RotateCcw, X, ZoomIn } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import Cropper from 'react-easy-crop';
import type { CropGuide } from '@/components/shared/crop-guide-overlay';
import { CropGuideOverlay } from '@/components/shared/crop-guide-overlay';

/** Normalized crop rect (0..1) relative to the natural image. */
export type CropRect = { sx: number; sy: number; sw: number; sh: number };
export type AutoCrop = CropRect & { natW: number; natH: number; ratio: number };

/*
 | Kotak crop pas foto untuk form pendaftaran.
 |
 | TIDAK DIPAKAI sejak 21 Agustus 2026. Klien meminta croping dibuang dari kedua
 | link pendaftaran: pendaftar cukup mengetik nama berkas dan melihat foto
 | Drive-nya apa adanya. Komponennya dipisah ke berkas ini — bukan dihapus dan
 | bukan dikomentari 226 baris di tengah halaman — supaya menghidupkannya lagi
 | cukup membuka kembali satu blok di `pages/student-register.tsx`.
 |
 | Yang ikut mati bersamanya: endpoint `POST /daftar/crop-preview` dan
 | `PhotoCropService::autoCropRect()`. Keduanya sengaja dibiarkan hidup di server.
 */
/** Canva-style crop: fixed 16:21 frame, image pans + zooms behind it. */
export function CropReposition({
    imageUrl,
    filename,
    auto,
    guide,
    onChange,
    onClose,
    onReady,
}: {
    imageUrl: string;
    filename: string;
    auto: AutoCrop;
    guide: CropGuide;
    onChange: (rect: CropRect) => void;
    onClose: () => void;
    onReady: (ready: boolean) => void;
}) {
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [resetKey, setResetKey] = useState(0);
    const [showGuide, setShowGuide] = useState(false);
    // Ukuran alami gambar pratinjau — dipakai menormalkan rect hasil geser/zoom.
    const [natural, setNatural] = useState<{ w: number; h: number } | null>(null);
    // Ukuran kotak crop sebenarnya, supaya sketsa panduan berimpit dengannya dan
    // tidak sekadar menebak lewat rasio container.
    const [cropBox, setCropBox] = useState<{ width: number; height: number } | null>(null);
    // react-easy-crop menembakkan onCropComplete sekali SEBELUM menerapkan rect
    // awal, dengan zoom 1 dan seluruh gambar. Kalau emisi itu ikut ditulis, hasil
    // deteksi wajah dari server langsung tertimpa bingkai penuh.
    const initializedRef = useRef(false);

    // `onReady` menjaga tombol Lanjut tetap mati sampai gambarnya benar-benar
    // terlihat — respons crop-preview saja belum cukup, karena berkasnya masih
    // dalam perjalanan ke browser saat itu.
    useEffect(() => {
        setNatural(null);
        initializedRef.current = false;
        onReady(false);
        const img = new Image();
        img.onload = () => {
            setNatural({ w: img.naturalWidth, h: img.naturalHeight });
            onReady(true);
        };
        img.src = imageUrl;
    }, [imageUrl, onReady]);

    // Rect dari server sudah ternormalisasi 0..1, jadi versi persen adalah jalur
    // yang tepat — tidak lewat pembulatan piksel seperti varian *Pixels.
    const initialArea = {
        x: auto.sx * 100,
        y: auto.sy * 100,
        width: auto.sw * 100,
        height: auto.sh * 100,
    };

    const handleComplete = useCallback(
        (_area: unknown, px: { x: number; y: number; width: number; height: number }) => {
            if (!natural || !initializedRef.current) {
                return;
            }

            onChange({
                sx: Math.max(0, Math.min(1, px.x / natural.w)),
                sy: Math.max(0, Math.min(1, px.y / natural.h)),
                sw: Math.max(0, Math.min(1, px.width / natural.w)),
                sh: Math.max(0, Math.min(1, px.height / natural.h)),
            });
        },
        [natural, onChange],
    );

    function reset() {
        initializedRef.current = false;
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setResetKey((k) => k + 1);
    }

    return (
        <div className="overflow-hidden rounded-xl border-2 border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-950">
            <div className="flex items-center justify-between border-b border-green-200 px-3 py-2 dark:border-green-800">
                <span className="flex items-center gap-1.5 text-sm font-medium text-green-800 dark:text-green-200">
                    <CheckCircle2 className="size-4" /> {filename}
                </span>
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-md p-1 text-green-600 hover:bg-green-200 dark:hover:bg-green-800"
                >
                    <X className="size-4" />
                </button>
            </div>
            <div className="p-4">
                <p className="mb-3 text-center text-xs font-medium text-green-700 dark:text-green-300">
                    Geser & zoom foto untuk atur posisi wajah
                </p>
                <div className="relative mx-auto h-80 w-full max-w-sm overflow-hidden rounded-lg bg-zinc-900">
                    {natural ? (
                        <Cropper
                            key={resetKey}
                            image={imageUrl}
                            crop={crop}
                            zoom={zoom}
                            aspect={16 / 21}
                            minZoom={1}
                            maxZoom={5}
                            restrictPosition
                            objectFit="contain"
                            initialCroppedAreaPercentages={initialArea}
                            onCropChange={setCrop}
                            onZoomChange={setZoom}
                            onCropComplete={handleComplete}
                            onCropSizeChange={setCropBox}
                            onMediaLoaded={() => {
                                initializedRef.current = true;
                            }}
                            showGrid={false}
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center">
                            <Loader2 className="size-6 animate-spin text-white/70" />
                        </div>
                    )}
                    {natural && cropBox && (
                        <CropGuideOverlay
                            guide={guide}
                            style={{ width: cropBox.width, height: cropBox.height }}
                            className="top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"
                        />
                    )}
                </div>

                {/* Zoom control — kept right under the cropper */}
                <div className="mt-3 flex items-center gap-3">
                    <span className="text-muted-foreground text-xs">Zoom</span>
                    <input
                        type="range"
                        min={1}
                        max={5}
                        step={0.05}
                        value={zoom}
                        onChange={(e) => setZoom(Number(e.target.value))}
                        className="h-1.5 flex-1 cursor-pointer accent-green-600"
                    />
                    <button
                        type="button"
                        onClick={reset}
                        className="rounded-md border border-green-300 px-2 py-1 text-xs font-medium text-green-700 hover:bg-green-100 dark:border-green-700 dark:text-green-300 dark:hover:bg-green-900"
                    >
                        Reset
                    </button>
                </div>

                {/* Panduan crop */}
                <div className="mt-4 rounded-lg border border-green-200 bg-white/70 p-3 dark:border-green-800 dark:bg-zinc-900/40">
                    <p className="mb-3 text-sm font-bold text-green-900 dark:text-green-100">
                        Mohon croping seperti contoh foto di bawah.
                    </p>
                    <p className="mb-2 text-xs font-semibold text-green-800 dark:text-green-200">Cara mengatur foto:</p>

                    {/* Poster panduan */}
                    <button type="button" onClick={() => setShowGuide(true)} className="mb-3 block w-full" title="Ketuk untuk perbesar">
                        <img
                            src="/images/panduan-foto.webp"
                            alt="Panduan atur foto: geser, zoom in, zoom out"
                            className="w-full rounded-lg border border-green-200 dark:border-green-800"
                            loading="lazy"
                        />
                        <span className="text-muted-foreground mt-1 block text-center text-[11px]">Ketuk gambar untuk perbesar</span>
                    </button>

                    <ul className="space-y-1.5 text-xs text-green-700 dark:text-green-300">
                        <li className="flex items-start gap-2">
                            <Move className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Geser foto:</b> tahan lalu tarik foto untuk atur posisi wajah.
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <ZoomIn className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Perbesar/perkecil:</b> pakai slider <b>Zoom</b> di atas.
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <Crop className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Pastikan wajah penuh</b> di dalam kotak — rasio pas foto 3×4 (16:21). Ikuti garis bantu di atas foto: mata
                                di garis hijau, bahu terlihat.
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <RotateCcw className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Reset:</b> kembalikan ke posisi otomatis.
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            {showGuide && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
                    onClick={() => setShowGuide(false)}
                    role="button"
                    tabIndex={0}
                >
                    <img
                        src="/images/panduan-foto.webp"
                        alt="Panduan atur foto"
                        className="max-h-full max-w-full rounded-lg object-contain"
                    />
                    <button
                        type="button"
                        onClick={() => setShowGuide(false)}
                        className="absolute top-4 right-4 rounded-full bg-white/90 p-2 text-zinc-800 shadow"
                        aria-label="Tutup"
                    >
                        <X className="size-5" />
                    </button>
                </div>
            )}
        </div>
    );
}
