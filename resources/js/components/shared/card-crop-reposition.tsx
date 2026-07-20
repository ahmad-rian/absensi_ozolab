import { Crop, Move, RotateCcw, X, ZoomIn } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import Cropper from 'react-easy-crop';

/** Normalized crop rect (0..1) relative to the natural image. */
export type CropRect = { sx: number; sy: number; sw: number; sh: number };

/**
 * Canva-style crop for a user-chosen photo File. A fixed portrait (3×4) frame stays
 * put while the image pans + zooms behind it. Emits a scale-independent normalized
 * rect (0..1) suitable for the backend `manual_crop` payload.
 */
export function CardCropReposition({
    imageUrl,
    filename,
    aspect = 3 / 4,
    onChange,
    onClose,
}: {
    imageUrl: string;
    filename?: string;
    aspect?: number;
    onChange: (rect: CropRect) => void;
    onClose?: () => void;
}) {
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [resetKey, setResetKey] = useState(0);
    // Natural size of the current image, kept in a ref so it is always in sync with
    // `imageUrl` (avoids a stale value while a new image loads) without a render.
    const naturalRef = useRef<{ w: number; h: number } | null>(null);

    useEffect(() => {
        naturalRef.current = null;
        const img = new Image();
        img.onload = () => {
            naturalRef.current = { w: img.naturalWidth, h: img.naturalHeight };
        };
        img.src = imageUrl;
    }, [imageUrl]);

    const handleComplete = useCallback(
        (_area: unknown, px: { x: number; y: number; width: number; height: number }) => {
            const natural = naturalRef.current;

            if (!natural) {
                return;
            }

            onChange({
                sx: Math.max(0, Math.min(1, px.x / natural.w)),
                sy: Math.max(0, Math.min(1, px.y / natural.h)),
                sw: Math.max(0, Math.min(1, px.width / natural.w)),
                sh: Math.max(0, Math.min(1, px.height / natural.h)),
            });
        },
        [onChange],
    );

    function reset() {
        setCrop({ x: 0, y: 0 });
        setZoom(1);
        setResetKey((k) => k + 1);
    }

    return (
        <div className="overflow-hidden rounded-xl border-2 border-emerald-300 bg-emerald-50 dark:border-emerald-700 dark:bg-emerald-950">
            <div className="flex items-center justify-between border-b border-emerald-200 px-3 py-2 dark:border-emerald-800">
                <span className="truncate text-sm font-medium text-emerald-800 dark:text-emerald-200">{filename ?? 'Foto'}</span>
                {onClose && (
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-md p-1 text-emerald-600 hover:bg-emerald-200 dark:hover:bg-emerald-800"
                    >
                        <X className="size-4" />
                    </button>
                )}
            </div>
            <div className="p-4">
                <p className="mb-3 text-center text-xs font-medium text-emerald-700 dark:text-emerald-300">
                    Geser &amp; zoom foto untuk atur posisi wajah
                </p>
                <div className="relative mx-auto h-80 w-full max-w-sm overflow-hidden rounded-lg bg-zinc-900">
                    <Cropper
                        key={resetKey}
                        image={imageUrl}
                        crop={crop}
                        zoom={zoom}
                        aspect={aspect}
                        minZoom={1}
                        maxZoom={5}
                        restrictPosition
                        objectFit="contain"
                        onCropChange={setCrop}
                        onZoomChange={setZoom}
                        onCropComplete={handleComplete}
                        showGrid={false}
                    />
                </div>

                {/* Crop guidance */}
                <div className="mt-3 rounded-lg border border-emerald-200 bg-white/70 p-3 dark:border-emerald-800 dark:bg-zinc-900/40">
                    <p className="mb-2 text-xs font-semibold text-emerald-800 dark:text-emerald-200">Cara mengatur foto:</p>
                    <ul className="space-y-1.5 text-xs text-emerald-700 dark:text-emerald-300">
                        <li className="flex items-start gap-2">
                            <Move className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Geser foto:</b> tahan lalu tarik foto untuk atur posisi wajah.
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <ZoomIn className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Perbesar/perkecil:</b> pakai slider <b>Zoom</b> di bawah.
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <Crop className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Pastikan wajah penuh</b> di dalam kotak (frame kartu).
                            </span>
                        </li>
                        <li className="flex items-start gap-2">
                            <RotateCcw className="mt-0.5 size-3.5 shrink-0" />
                            <span>
                                <b>Reset:</b> kembalikan ke posisi awal.
                            </span>
                        </li>
                    </ul>
                </div>

                <div className="mt-3 flex items-center gap-3">
                    <span className="text-muted-foreground text-xs">Zoom</span>
                    <input
                        type="range"
                        min={1}
                        max={5}
                        step={0.05}
                        value={zoom}
                        onChange={(e) => setZoom(Number(e.target.value))}
                        className="h-1.5 flex-1 cursor-pointer accent-emerald-600"
                    />
                    <button
                        type="button"
                        onClick={reset}
                        className="rounded-md border border-emerald-300 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>
    );
}
